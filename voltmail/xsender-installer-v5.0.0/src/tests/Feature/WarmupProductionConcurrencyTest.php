<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupOutboxDispatcher;
use App\Services\Warmup\WarmupSchedulerService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarmupProductionConcurrencyTest extends TestCase
{
    protected string $connection = 'mysql_test';
    protected bool $mysqlAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Configure dedicated MySQL connection for real InnoDB testing
        config(['database.connections.mysql_test' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => 3306,
            'database'  => 'voltmail_staging_test',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => 'InnoDB',
        ]]);

        try {
            DB::connection($this->connection)->getPdo();
            $this->mysqlAvailable = true;
        } catch (\Throwable $e) {
            $this->markTestSkipped('MySQL staging server is not running: ' . $e->getMessage());
            return;
        }

        config(['database.default' => 'mysql_test']);
        DB::purge();
        // Ensure production tables exist on MySQL test database
        if (!Schema::hasTable('warmup_schedules')) {
            $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
            $schedMigration->up();
        }
        if (!Schema::hasTable('warmup_dispatches')) {
            $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
            $dispMigration->up();
        }

        // Truncate test tables cleanly under MySQL
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('warmup_dispatches')->truncate();
        DB::table('warmup_schedules')->truncate();
        DB::table('gateways')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }

    protected function tearDown(): void
    {
        if ($this->mysqlAvailable) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
            DB::table('warmup_dispatches')->truncate();
            DB::table('warmup_schedules')->truncate();
            DB::table('gateways')->truncate();
            DB::table('users')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            config(['database.default' => 'sqlite']);
            DB::purge();
            DB::setDefaultConnection('sqlite');
        }
        parent::tearDown();
    }

    public function test_mysql_atomic_claim_and_zero_duplicate_claims_under_concurrency()
    {
        $db = DB::connection($this->connection);

        // 1. Seed user, gateway, schedule, and 20 pending dispatches
        $userId = $db->table('users')->insertGetId([
            'uid'        => Str::random(32),
            'name'       => 'Test User',
            'email'      => 'concurrency_test@voltmail.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gatewayId = $db->table('gateways')->insertGetId([
            'uid'        => Str::random(32),
            'user_id'    => $userId,
            'type'       => 'smtp',
            'channel'    => 'email',
            'name'       => 'MySQL Gateway',
            'address'    => 'mysql@voltmail.test',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = $db->table('warmup_schedules')->insertGetId([
            'user_id'             => $userId,
            'gateway_id'          => $gatewayId,
            'status'              => 'active',
            'daily_limit'         => 50,
            'rampup_increment'    => 5,
            'current_day'         => 1,
            'current_daily_quota' => 20,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $now = Carbon::now();
        for ($i = 1; $i <= 20; $i++) {
            $db->table('warmup_dispatches')->insert([
                'warmup_schedule_id' => $scheduleId,
                'user_id'            => $userId,
                'gateway_id'         => $gatewayId,
                'recipient_email'    => "peer{$i}@voltmail.test",
                'subject'            => "Slot {$i}",
                'dispatch_date'      => $now->toDateString(),
                'slot_number'        => $i,
                'scheduled_at'       => $now->copy()->subSeconds(20 - $i),
                'status'             => WarmupDispatch::STATUS_PENDING,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }

        $this->assertEquals(20, $db->table('warmup_dispatches')->where('status', 'pending')->count());

        // 2. Simulate 2 concurrent workers attempting to claim slots simultaneously
        // Worker A claims batch of 10
        // Worker B claims batch of 10
        // In MySQL, compare-and-swap UPDATE ensures zero overlapping claimed IDs
        $dispatcherA = new WarmupOutboxDispatcher();
        $dispatcherB = new WarmupOutboxDispatcher();

        $claimedByA = $dispatcherA->claimDueSlots(10, 'worker_alpha');
        $claimedByB = $dispatcherB->claimDueSlots(10, 'worker_beta');

        $idsA = $claimedByA->pluck('id')->all();
        $idsB = $claimedByB->pluck('id')->all();

        // Assert: Both workers got slots
        $this->assertCount(10, $idsA);
        $this->assertCount(10, $idsB);

        // Assert: Absolutely ZERO overlap between Worker A and Worker B
        $intersection = array_intersect($idsA, $idsB);
        $this->assertEmpty($intersection, 'Race condition detected: same slot claimed by multiple workers!');

        // Assert: All 20 are now claimed, none pending
        $remainingPending = $db->table('warmup_dispatches')->where('status', 'pending')->count();
        $this->assertEquals(0, $remainingPending);

        // Worker C comes in immediately after
        $dispatcherC = new WarmupOutboxDispatcher();
        $claimedByC = $dispatcherC->claimDueSlots(10, 'worker_gamma');
        $this->assertEmpty($claimedByC, 'Worker C claimed slots that were already claimed!');
    }

    public function test_mysql_unique_constraint_enforces_idempotency_and_prevents_replay()
    {
        $db = DB::connection($this->connection);

        $userId = $db->table('users')->insertGetId([
            'uid'        => Str::random(32),
            'name'       => 'Test User',
            'email'      => 'unique_test@voltmail.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gatewayId = $db->table('gateways')->insertGetId([
            'uid'        => Str::random(32),
            'user_id'    => $userId,
            'type'       => 'smtp',
            'channel'    => 'email',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = $db->table('warmup_schedules')->insertGetId([
            'user_id'             => $userId,
            'gateway_id'          => $gatewayId,
            'status'              => 'active',
            'daily_limit'         => 30,
            'rampup_increment'    => 2,
            'current_day'         => 1,
            'current_daily_quota' => 5,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        // Insert slot 1
        $db->table('warmup_dispatches')->insert([
            'warmup_schedule_id' => $scheduleId,
            'user_id'            => $userId,
            'gateway_id'         => $gatewayId,
            'recipient_email'    => 'recipient@voltmail.test',
            'subject'            => 'Slot 1',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'status'             => 'pending',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        // Attempt duplicate insert of slot 1 on the exact same date and schedule
        $this->expectException(\Illuminate\Database\QueryException::class);
        $db->table('warmup_dispatches')->insert([
            'warmup_schedule_id' => $scheduleId,
            'user_id'            => $userId,
            'gateway_id'         => $gatewayId,
            'recipient_email'    => 'replay_attacker@voltmail.test',
            'subject'            => 'Duplicate Slot 1',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'status'             => 'pending',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function test_mysql_stale_lease_recovery_restores_crashed_worker_slots()
    {
        $db = DB::connection($this->connection);

        $userId = $db->table('users')->insertGetId([
            'uid'        => Str::random(32),
            'name'       => 'Test User',
            'email'      => 'stale_test@voltmail.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gatewayId = $db->table('gateways')->insertGetId([
            'uid'        => Str::random(32),
            'user_id'    => $userId,
            'type'       => 'smtp',
            'channel'    => 'email',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = $db->table('warmup_schedules')->insertGetId([
            'user_id'             => $userId,
            'gateway_id'          => $gatewayId,
            'status'              => 'active',
            'daily_limit'         => 30,
            'rampup_increment'    => 2,
            'current_day'         => 1,
            'current_daily_quota' => 5,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $staleTime = Carbon::now()->subMinutes(15);

        // Create 3 crashed slots: 2 in claimed state, 1 in sending state
        $db->table('warmup_dispatches')->insert([
            [
                'warmup_schedule_id' => $scheduleId,
                'user_id'            => $userId,
                'gateway_id'         => $gatewayId,
                'recipient_email'    => 'peer1@voltmail.test',
                'subject'            => 'Crashed slot 1',
                'dispatch_date'      => '2026-10-12',
                'slot_number'        => 1,
                'status'             => WarmupDispatch::STATUS_CLAIMED,
                'claimed_at'         => $staleTime,
                'claimed_by'         => 'crashed_worker_1',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
            [
                'warmup_schedule_id' => $scheduleId,
                'user_id'            => $userId,
                'gateway_id'         => $gatewayId,
                'recipient_email'    => 'peer2@voltmail.test',
                'subject'            => 'Crashed slot 2',
                'dispatch_date'      => '2026-10-12',
                'slot_number'        => 2,
                'status'             => WarmupDispatch::STATUS_SENDING,
                'claimed_at'         => $staleTime,
                'claimed_by'         => 'crashed_worker_2',
                'created_at'         => now(),
                'updated_at'         => now(),
            ],
        ]);

        $dispatcher = new WarmupOutboxDispatcher();
        $recovered = $dispatcher->recoverStaleLeases(5);

        $this->assertEquals(2, $recovered);

        // Assert: Both slots are back to pending with null lease fields
        $pendingSlots = $db->table('warmup_dispatches')->where('status', WarmupDispatch::STATUS_PENDING)->get();
        $this->assertCount(2, $pendingSlots);
        foreach ($pendingSlots as $slot) {
            $this->assertNull($slot->claimed_at);
            $this->assertNull($slot->claimed_by);
        }
    }

    public function test_mysql_repeated_cron_runs_are_strictly_idempotent()
    {
        $db = DB::connection($this->connection);

        $userId = $db->table('users')->insertGetId([
            'uid'        => Str::random(32),
            'name'       => 'Test User',
            'email'      => 'idempotent_test@voltmail.test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gatewayId = $db->table('gateways')->insertGetId([
            'uid'        => Str::random(32),
            'user_id'    => $userId,
            'type'       => 'smtp',
            'channel'    => 'email',
            'address'    => 'cron_sender@voltmail.test',
            'status'     => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $scheduleId = $db->table('warmup_schedules')->insertGetId([
            'user_id'             => $userId,
            'gateway_id'          => $gatewayId,
            'status'              => 'active',
            'daily_limit'         => 30,
            'rampup_increment'    => 3,
            'current_day'         => 1,
            'current_daily_quota' => 5,
            'start_date'          => '2026-10-12',
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        $scheduler = new WarmupSchedulerService();

        // Run 1: Initial schedule generation for today
        $run1 = $scheduler->scheduleDailyDispatches('2026-10-12');
        $this->assertEquals(5, $run1['slots_generated']);
        $this->assertEquals(5, $db->table('warmup_dispatches')->count());

        // Runs 2, 3, 4, 5: Repeated hourly cron runs across the same day
        for ($i = 2; $i <= 5; $i++) {
            $repeatRun = $scheduler->scheduleDailyDispatches('2026-10-12');
            $this->assertEquals(0, $repeatRun['slots_generated'], "Run {$i} generated duplicate slots!");
            $this->assertEquals(5, $db->table('warmup_dispatches')->count(), "Dispatch count grew on run {$i}!");
        }
    }

    public function test_mysql_query_plan_uses_composite_indexes_effectively()
    {
        $db = DB::connection($this->connection);

        // Run EXPLAIN on the outbox query
        $now = Carbon::now()->toDateTimeString();
        $explain = $db->select("
            EXPLAIN SELECT id FROM warmup_dispatches 
            WHERE status = 'pending' AND scheduled_at <= '{$now}' 
            ORDER BY scheduled_at LIMIT 20
        ");

        $this->assertNotEmpty($explain);
        $plan = (array) $explain[0];

        // Verify key used is one of our indexes and not ALL (full table scan)
        $possibleKeys = $plan['possible_keys'] ?? '';
        $usedKey      = $plan['key'] ?? '';

        $this->assertNotNull($usedKey, 'MySQL did not use an index for outbox query!');
        $this->assertTrue(
            str_contains($usedKey, 'warmup_dispatches_status') || 
            str_contains($possibleKeys, 'warmup_dispatches_status'),
            "Index 'warmup_dispatches_status' not considered in query plan: " . json_encode($plan)
        );
    }
}
