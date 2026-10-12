<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupSchedulerService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupSchedulerTest extends TestCase
{
    protected WarmupSchedulerService $scheduler;
    protected User $user;
    protected Gateway $gateway;
    protected WarmupSchedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('warmup_dispatches');
        Schema::dropIfExists('warmup_schedules');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('users');

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uid', 100)->nullable();
            $table->string('name')->default('Test User');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->enum('status', ['0', '1'])->default('1');
            $table->timestamps();
        });

        Schema::create('gateways', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->autoIncrement();
            $table->string('uid', 32)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('type', 255)->nullable();
            $table->string('channel', 50)->default('email');
            $table->string('name', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->json('meta_data')->nullable();
            $table->string('status', 50)->default('active');
            $table->tinyInteger('is_default')->default(0);
            $table->timestamps();
        });

        $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
        $schedMigration->up();

        $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
        $dispMigration->up();

        $this->scheduler = new WarmupSchedulerService();

        $this->user = User::create(['email' => 'scheduler_test@voltmail.test']);
        $this->gateway = Gateway::create([
            'user_id'   => $this->user->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'Sender Gateway',
            'address'   => 'sender@voltmail.test',
            'meta_data' => ['host' => 'smtp.test', 'port' => 587, 'driver' => 'SMTP', 'username' => 'u', 'password' => 'p'],
            'status'    => 'active',
        ]);

        $this->schedule = WarmupSchedule::create([
            'user_id'             => $this->user->id,
            'gateway_id'          => $this->gateway->id,
            'status'              => 'active',
            'daily_limit'         => 30,
            'rampup_increment'    => 3,
            'current_day'         => 1,
            'current_daily_quota' => 5,
            'start_date'          => '2026-10-12',
        ]);
    }

    public function test_daily_quotas_and_rampup_progression()
    {
        // Day 1: starting quota is 5
        $slotsDay1 = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-12');
        $this->assertCount(5, $slotsDay1);
        $this->schedule->refresh();
        $this->assertEquals(1, $this->schedule->current_day);
        $this->assertEquals(5, $this->schedule->current_daily_quota);

        // Day 3: (Day 3 - 1) * 3 = 6 + 5 = 11 quota
        $slotsDay3 = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-14');
        $this->assertCount(11, $slotsDay3);
        $this->schedule->refresh();
        $this->assertEquals(3, $this->schedule->current_day);
        $this->assertEquals(11, $this->schedule->current_daily_quota);

        // Day 20: capped at daily_limit = 30
        $slotsDay20 = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-31');
        $this->assertCount(30, $slotsDay20);
        $this->schedule->refresh();
        $this->assertEquals(20, $this->schedule->current_day);
        $this->assertEquals(30, $this->schedule->current_daily_quota);
    }

    public function test_slots_distribution_enforces_rate_limits_across_business_hours()
    {
        $slots = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-12');

        $this->assertNotEmpty($slots);
        $firstSlot = $slots[0];
        $lastSlot  = end($slots);

        // First slot is in early morning window (>= 08:00)
        $this->assertGreaterThanOrEqual('07:55:00', $firstSlot->scheduled_at->format('H:i:s'));
        // Last slot is scheduled later than first slot
        $this->assertTrue($lastSlot->scheduled_at->greaterThan($firstSlot->scheduled_at));
    }

    public function test_idempotent_scheduling_prevents_duplicate_slot_generation()
    {
        // First generation for today
        $firstGen = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-12');
        $this->assertCount(5, $firstGen);
        $this->assertEquals(5, WarmupDispatch::count());

        // Second generation call for the exact same date
        $secondGen = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-12');
        $this->assertEmpty($secondGen);
        // Dispatch count must not increase
        $this->assertEquals(5, WarmupDispatch::count());
    }

    public function test_recipient_selection_ensures_sender_never_warms_up_with_self()
    {
        $slots = $this->scheduler->generateSlotsForSchedule($this->schedule, '2026-10-12');

        foreach ($slots as $dispatch) {
            $this->assertNotEquals($this->gateway->address, $dispatch->recipient_email);
        }
    }

    public function test_calculate_quota_handles_null_values_with_safe_defaults()
    {
        $schedule = new WarmupSchedule([
            'daily_limit'      => null,
            'rampup_increment' => null,
        ]);

        // Default starting quota 5, default increment 2, default limit 40
        $this->assertEquals(5, $schedule->calculateQuotaForDay(1, 5));
        $this->assertEquals(7, $schedule->calculateQuotaForDay(2, 5));
        $this->assertEquals(13, $schedule->calculateQuotaForDay(5, 5));
        // Capped at default daily limit (40)
        $this->assertEquals(40, $schedule->calculateQuotaForDay(30, 5));
    }

    public function test_calculate_quota_handles_normal_values_and_rampup_progression()
    {
        $schedule = new WarmupSchedule([
            'daily_limit'      => 30,
            'rampup_increment' => 3,
        ]);

        // Day 1: startingQuota (5)
        $this->assertEquals(5, $schedule->calculateQuotaForDay(1, 5));
        // Day 2: 5 + (1 * 3) = 8
        $this->assertEquals(8, $schedule->calculateQuotaForDay(2, 5));
        // Day 5: 5 + (4 * 3) = 17
        $this->assertEquals(17, $schedule->calculateQuotaForDay(5, 5));
        // Day 9: 5 + (8 * 3) = 29
        $this->assertEquals(29, $schedule->calculateQuotaForDay(9, 5));
        // Day 10: 5 + (9 * 3) = 32 -> capped at 30
        $this->assertEquals(30, $schedule->calculateQuotaForDay(10, 5));
        // Day 25: capped at 30
        $this->assertEquals(30, $schedule->calculateQuotaForDay(25, 5));
    }

    public function test_calculate_quota_handles_boundary_cases_and_caps()
    {
        $schedule = new WarmupSchedule([
            'daily_limit'      => 50,
            'rampup_increment' => 0, // Flat progression
        ]);

        // Increment 0 should return flat quota
        $this->assertEquals(10, $schedule->calculateQuotaForDay(1, 10));
        $this->assertEquals(10, $schedule->calculateQuotaForDay(15, 10));

        // Day index 0 or negative must be treated safely as Day 1
        $this->assertEquals(10, $schedule->calculateQuotaForDay(0, 10));
        $this->assertEquals(10, $schedule->calculateQuotaForDay(-5, 10));

        // Daily limit of 0 enforces 0 quota
        $zeroLimitSchedule = new WarmupSchedule(['daily_limit' => 0, 'rampup_increment' => 2]);
        $this->assertEquals(0, $zeroLimitSchedule->calculateQuotaForDay(1, 5));
    }

    public function test_calculate_quota_rejects_invalid_negative_configuration()
    {
        // Negative starting quota
        $schedule = new WarmupSchedule(['daily_limit' => 20, 'rampup_increment' => 2]);
        $this->expectException(\InvalidArgumentException::class);
        $schedule->calculateQuotaForDay(1, -1);
    }

    public function test_calculate_quota_rejects_negative_daily_limit()
    {
        $schedule = new WarmupSchedule(['daily_limit' => -10, 'rampup_increment' => 2]);
        $this->expectException(\InvalidArgumentException::class);
        $schedule->calculateQuotaForDay(1, 5);
    }

    public function test_calculate_quota_rejects_negative_rampup_increment()
    {
        $schedule = new WarmupSchedule(['daily_limit' => 20, 'rampup_increment' => -3]);
        $this->expectException(\InvalidArgumentException::class);
        $schedule->calculateQuotaForDay(1, 5);
    }
}
