<?php

namespace Tests\Unit;

use App\Http\Utility\SendMail;
use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Content\DeterministicContentEngine;
use App\Services\Warmup\WarmupOutboxDispatcher;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupOutboxDispatcherTest extends TestCase
{
    protected WarmupOutboxDispatcher $dispatcher;
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

        $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
        $schedMigration->up();

        $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
        $dispMigration->up();

        $crMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000001_create_content_resources_table.php';
        $crMigration->up();

        $cghMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000002_create_content_generation_histories_table.php';
        $cghMigration->up();

        $this->user = User::create(['email' => 'dispatcher_user@voltmail.test']);

        $this->gateway = Gateway::create([
            'user_id'   => $this->user->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'Dispatcher Gateway',
            'address'   => 'outbox@voltmail.test',
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
            'name'                => 'Main SaaS Profile',
            'company_name'        => 'Voltmail SaaS',
            'business_type'       => 'Email Platform',
            'company_description' => 'Automated deliverability and sender warmup architecture.',
            'industry'            => 'Enterprise Tech',
            'target_audience'     => 'Digital founders and growth leaders',
            'key_products'        => [['name' => 'Warmup Engine', 'benefit' => 'Automated sender reputation building']],
            'status'              => 'active',
            'is_default'          => true,
        ]);

        $this->dispatcher = new WarmupOutboxDispatcher();
    }

    public function test_concurrent_slot_claiming_is_atomic()
    {
        // Seed 5 pending slots
        for ($i = 1; $i <= 5; $i++) {
            WarmupDispatch::create([
                'warmup_schedule_id' => $this->schedule->id,
                'user_id'            => $this->user->id,
                'gateway_id'         => $this->gateway->id,
                'recipient_email'    => "peer{$i}@voltmail.test",
                'subject'            => 'Subject',
                'dispatch_date'      => '2026-10-12',
                'slot_number'        => $i,
                'scheduled_at'       => Carbon::now()->subMinute(),
                'status'             => WarmupDispatch::STATUS_PENDING,
            ]);
        }

        $dispatcherA = new WarmupOutboxDispatcher();
        $dispatcherB = new WarmupOutboxDispatcher();

        // Worker A claims 3 slots
        $claimedA = $dispatcherA->claimDueSlots(3, 'worker_A');
        // Worker B claims remaining 3 slots
        $claimedB = $dispatcherB->claimDueSlots(3, 'worker_B');

        $this->assertCount(3, $claimedA);
        $this->assertCount(2, $claimedB);

        // Disjoint sets: Worker A and Worker B must have claimed completely different slots
        $idsA = $claimedA->pluck('id')->all();
        $idsB = $claimedB->pluck('id')->all();
        $this->assertEmpty(array_intersect($idsA, $idsB));

        // No pending slots left
        $this->assertEquals(0, WarmupDispatch::where('status', WarmupDispatch::STATUS_PENDING)->count());
    }

    public function test_worker_crash_and_stale_lease_recovery()
    {
        // Slot stuck in 'claimed' 10 minutes ago due to crashed worker
        $staleDispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@voltmail.test',
            'subject'            => 'Subject',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'scheduled_at'       => Carbon::now()->subMinutes(15),
            'claimed_at'         => Carbon::now()->subMinutes(10),
            'claimed_by'         => 'crashed_worker_pid_999',
            'status'             => WarmupDispatch::STATUS_CLAIMED,
        ]);

        $dispatcher = new WarmupOutboxDispatcher();
        $recovered = $dispatcher->recoverStaleLeases(5);

        $this->assertEquals(1, $recovered);
        $staleDispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_PENDING, $staleDispatch->status);
        $this->assertNull($staleDispatch->claimed_at);
        $this->assertNull($staleDispatch->claimed_by);
    }

    public function test_dispatch_integrates_content_engine_and_records_history()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@voltmail.test',
            'subject'            => 'Pending',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'scheduled_at'       => Carbon::now()->subMinute(),
            'status'             => WarmupDispatch::STATUS_CLAIMED,
        ]);

        // Mock SendMail returning success
        $mockSendMail = $this->createMock(SendMail::class);
        $mockSendMail->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $dispatcher = new WarmupOutboxDispatcher(null, $mockSendMail);
        $result = $dispatcher->dispatchSlot($dispatch);

        $this->assertTrue($result['success']);
        $this->assertEquals(WarmupDispatch::STATUS_SENT, $result['status']);

        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_SENT, $dispatch->status);
        $this->assertNotNull($dispatch->message_id);
        $this->assertStringContainsString('@voltmail.test', $dispatch->message_id);
        $this->assertNotNull($dispatch->sent_at);

        // Verify history tracking
        $this->assertDatabaseHas('content_generation_histories', [
            'user_id'             => $this->user->id,
            'content_resource_id' => $this->contentResource->id,
            'dispatch_reference'  => $dispatch->uid,
        ]);
    }

    public function test_smtp_ambiguous_outcome_transitions_to_ambiguous_without_duplicate_send()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@voltmail.test',
            'subject'            => 'Pending',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 2,
            'scheduled_at'       => Carbon::now()->subMinute(),
            'status'             => WarmupDispatch::STATUS_CLAIMED,
        ]);

        // Mock SendMail throwing network timeout
        $mockSendMail = $this->createMock(SendMail::class);
        $mockSendMail->method('send')
            ->willThrowException(new Exception('Connection timed out waiting for server response'));

        $dispatcher = new WarmupOutboxDispatcher(null, $mockSendMail);
        $result = $dispatcher->dispatchSlot($dispatch);

        $this->assertFalse($result['success']);
        $this->assertEquals(WarmupDispatch::STATUS_AMBIGUOUS, $result['status']);

        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_AMBIGUOUS, $dispatch->status);
        $this->assertStringContainsString('timed out', $dispatch->error_message);
        $this->assertEquals('421', $dispatch->response_code);
    }

    public function test_smtp_definitive_failure_transitions_to_failed()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@voltmail.test',
            'subject'            => 'Pending',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 3,
            'scheduled_at'       => Carbon::now()->subMinute(),
            'status'             => WarmupDispatch::STATUS_CLAIMED,
        ]);

        // Mock SendMail failing with 550 User unknown
        $mockSendMail = $this->createMock(SendMail::class);
        $mockSendMail->method('getLastError')->willReturn('550 5.1.1 User unknown / mailbox rejected');
        $mockSendMail->method('send')->willReturn(false);

        $dispatcher = new WarmupOutboxDispatcher(null, $mockSendMail);
        $result = $dispatcher->dispatchSlot($dispatch);

        $this->assertFalse($result['success']);
        $this->assertEquals(WarmupDispatch::STATUS_FAILED, $result['status']);

        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_FAILED, $dispatch->status);
        $this->assertStringContainsString('550', $dispatch->error_message);
    }

    public function test_get_due_slots_preview_is_strictly_read_only()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'preview_peer@voltmail.test',
            'subject'            => 'Pending',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 4,
            'scheduled_at'       => Carbon::now()->subMinute(),
            'status'             => WarmupDispatch::STATUS_PENDING,
        ]);

        $dueSlots = $this->dispatcher->getDueSlotsPreview(10);

        $this->assertCount(1, $dueSlots);
        $this->assertEquals($dispatch->id, $dueSlots->first()->id);

        // State in database MUST remain strictly PENDING without claims or lease modification
        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_PENDING, $dispatch->status);
        $this->assertNull($dispatch->claimed_at);
        $this->assertNull($dispatch->claimed_by);
    }

    public function test_preview_slot_does_not_modify_state_or_persist_history()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'preview_peer2@voltmail.test',
            'subject'            => 'Pending',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 5,
            'scheduled_at'       => Carbon::now()->subMinute(),
            'status'             => WarmupDispatch::STATUS_PENDING,
        ]);

        $historyCountBefore = ContentGenerationHistory::count();

        $preview = $this->dispatcher->previewSlot($dispatch);

        $this->assertEquals($dispatch->id, $preview['slot_id']);
        $this->assertEquals('preview_peer2@voltmail.test', $preview['recipient_email']);
        $this->assertTrue($preview['gateway_active']);
        $this->assertNotEmpty($preview['subject']);
        $this->assertNotEmpty($preview['message_id']);

        // Assert 0 database mutations
        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_PENDING, $dispatch->status);
        $this->assertEquals($historyCountBefore, ContentGenerationHistory::count());
    }
}
