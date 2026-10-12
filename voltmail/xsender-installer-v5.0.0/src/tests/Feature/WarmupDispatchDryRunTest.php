<?php

namespace Tests\Feature;

use App\Jobs\SendWarmupEmailJob;
use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupDispatchDryRunTest extends TestCase
{
    protected User $user;
    protected Gateway $gateway;
    protected WarmupSchedule $schedule;
    protected ContentResource $contentResource;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('content_generation_histories');
        Schema::dropIfExists('content_resources');
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
            $table->softDeletes();
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

        $crMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000001_create_content_resources_table.php';
        $crMigration->up();

        $cghMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000002_create_content_generation_histories_table.php';
        $cghMigration->up();

        $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
        $schedMigration->up();

        $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
        $dispMigration->up();

        $this->user = User::create(['email' => 'dryrun_user@voltmail.test']);

        $this->gateway = Gateway::create([
            'user_id'   => $this->user->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'DryRun Gateway',
            'address'   => 'dryrun@voltmail.test',
            'meta_data' => [
                'host'       => 'smtp.voltmail.test',
                'driver'     => 'SMTP',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'test_user',
                'password'   => 'test_pass',
            ],
            'status'    => 'active',
        ]);

        $this->schedule = WarmupSchedule::create([
            'user_id'             => $this->user->id,
            'gateway_id'          => $this->gateway->id,
            'status'              => 'active',
            'daily_limit'         => 40,
            'rampup_increment'    => 2,
            'current_day'         => 1,
            'current_daily_quota' => 10,
        ]);

        $this->contentResource = ContentResource::create([
            'user_id'             => $this->user->id,
            'name'                => 'DryRun Business Profile',
            'company_name'        => 'Voltmail SaaS',
            'business_type'       => 'Email Platform',
            'company_description' => 'Automated deliverability and sender warmup architecture.',
            'industry'            => 'Enterprise Tech',
            'target_audience'     => 'Digital founders and growth leaders',
            'key_products'        => [['name' => 'Warmup Engine', 'benefit' => 'Automated sender reputation building']],
            'status'              => 'active',
            'is_default'          => true,
        ]);
    }

    public function test_dry_run_reports_when_no_slots_are_due()
    {
        $exitCode = Artisan::call('warmup:dispatch', ['--dry-run' => true]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY RUN] No warmup slots currently due for dispatch', $output);
    }

    public function test_dry_run_inspects_due_slots_without_modifying_database_or_queues()
    {
        Queue::fake();

        // Create 3 pending due slots
        $slot1 = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'recipient1@voltmail.test',
            'subject'            => 'Pending Slot 1',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'scheduled_at'       => Carbon::now()->subMinutes(10),
            'status'             => WarmupDispatch::STATUS_PENDING,
        ]);

        $slot2 = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'recipient2@voltmail.test',
            'subject'            => 'Pending Slot 2',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 2,
            'scheduled_at'       => Carbon::now()->subMinutes(5),
            'status'             => WarmupDispatch::STATUS_PENDING,
        ]);

        $historyCountBefore = ContentGenerationHistory::count();

        // Execute warmup:dispatch with --dry-run
        $exitCode = Artisan::call('warmup:dispatch', ['--dry-run' => true, '--batch' => 10]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY RUN] Found 2 due warmup slot(s) eligible for dispatch', $output);
        $this->assertStringContainsString('recipient1@voltmail.test', $output);
        $this->assertStringContainsString('recipient2@voltmail.test', $output);
        $this->assertStringContainsString('DryRun Gateway', $output);
        $this->assertStringContainsString('[DRY RUN] Read-only inspection complete. Zero database updates, queue jobs, or emails generated', $output);

        // 1. Verify database slots are 100% UNCHANGED
        $slot1->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_PENDING, $slot1->status);
        $this->assertNull($slot1->claimed_at);
        $this->assertNull($slot1->claimed_by);
        $this->assertNull($slot1->sent_at);

        $slot2->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_PENDING, $slot2->status);
        $this->assertNull($slot2->claimed_at);
        $this->assertNull($slot2->claimed_by);
        $this->assertNull($slot2->sent_at);

        // 2. Verify NO ContentGenerationHistory records were created
        $this->assertEquals($historyCountBefore, ContentGenerationHistory::count());

        // 3. Verify NO Queue jobs were published
        Queue::assertNothingPushed();
    }

    public function test_dry_run_respects_batch_limit()
    {
        // Create 5 pending due slots
        for ($i = 1; $i <= 5; $i++) {
            WarmupDispatch::create([
                'warmup_schedule_id' => $this->schedule->id,
                'user_id'            => $this->user->id,
                'gateway_id'         => $this->gateway->id,
                'recipient_email'    => "batch_peer{$i}@voltmail.test",
                'subject'            => "Pending Slot {$i}",
                'dispatch_date'      => '2026-10-12',
                'slot_number'        => $i,
                'scheduled_at'       => Carbon::now()->subMinutes(10 - $i),
                'status'             => WarmupDispatch::STATUS_PENDING,
            ]);
        }

        $exitCode = Artisan::call('warmup:dispatch', ['--dry-run' => true, '--batch' => 3]);
        $output = Artisan::output();

        $this->assertEquals(0, $exitCode);
        $this->assertStringContainsString('[DRY RUN] Found 3 due warmup slot(s) eligible for dispatch', $output);

        // All 5 must remain pending
        $this->assertEquals(5, WarmupDispatch::where('status', WarmupDispatch::STATUS_PENDING)->count());
    }
}
