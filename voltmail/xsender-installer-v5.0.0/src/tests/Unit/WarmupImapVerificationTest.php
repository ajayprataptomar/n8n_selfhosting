<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\ImapClient;
use App\Services\Warmup\WarmupImapService;
use App\Services\Warmup\WarmupMailboxService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupImapVerificationTest extends TestCase
{
    protected WarmupImapService $imapService;
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

        $this->user = User::create(['email' => 'imap_user@voltmail.test']);

        $this->gateway = Gateway::create([
            'user_id'   => $this->user->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'IMAP Gateway',
            'address'   => 'inbox@voltmail.test',
            'meta_data' => [
                'host'       => 'smtp.test',
                'driver'     => 'SMTP',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user',
                'password'   => 'pass',
                'imap'       => [
                    'host'       => 'imap.test',
                    'port'       => 993,
                    'encryption' => 'ssl',
                    'username'   => 'imap_user',
                    'password'   => Crypt::encryptString('imap_pass'),
                ],
            ],
            'status'    => 'active',
        ]);

        $this->schedule = WarmupSchedule::create([
            'user_id'             => $this->user->id,
            'gateway_id'          => $this->gateway->id,
            'status'              => 'active',
            'daily_limit'         => 30,
            'rampup_increment'    => 2,
            'current_day'         => 1,
            'current_daily_quota' => 5,
        ]);

        $this->imapService = new WarmupImapService();
    }

    public function test_imap_verifies_inbox_placement_by_message_id()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@test.com',
            'subject'            => 'Test email',
            'message_id'         => '<warmup-msg-12345@voltmail.test>',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'status'             => WarmupDispatch::STATUS_SENT,
            'sent_at'            => Carbon::now()->subMinutes(10),
        ]);

        // Mock IMAP client with transport returning Message-ID match in INBOX
        $mockClient = new ImapClient(
            customTransport: function ($action, $args) use ($dispatch) {
                if ($action === 'connect' || $action === 'login') return true;
                if ($action === 'selectFolder') {
                    return ['folder' => $args['folder'], 'uidvalidity' => 5001, 'exists' => 10];
                }
                if ($action === 'searchMessageId') {
                    // Match found in INBOX
                    return ($args['messageId'] === $dispatch->message_id) ? [101] : [];
                }
                return true;
            }
        );

        $result = $this->imapService->verifyDispatchesForSchedule($this->schedule, $mockClient);

        $this->assertEquals('success', $result['status']);
        $this->assertEquals(1, $result['verified_inbox']);
        $this->assertEquals(0, $result['verified_spam']);

        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_DELIVERED, $dispatch->status);
        $this->assertEquals(WarmupDispatch::PLACEMENT_INBOX, $dispatch->inbox_placement);
        $this->assertNotNull($dispatch->verified_at);
    }

    public function test_imap_verifies_spam_placement_when_found_in_junk_folder()
    {
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@test.com',
            'subject'            => 'Test email',
            'message_id'         => '<warmup-spam-999@voltmail.test>',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 2,
            'status'             => WarmupDispatch::STATUS_SENT,
            'sent_at'            => Carbon::now()->subMinutes(20),
        ]);

        $currentFolder = 'INBOX';
        $mockClient = new ImapClient(
            customTransport: function ($action, $args) use (&$currentFolder, $dispatch) {
                if ($action === 'connect' || $action === 'login') return true;
                if ($action === 'selectFolder') {
                    $currentFolder = $args['folder'];
                    return ['folder' => $currentFolder, 'uidvalidity' => 5001, 'exists' => 5];
                }
                if ($action === 'searchMessageId') {
                    // Found only in Spam folder
                    if (str_contains(strtolower($currentFolder), 'spam') && $args['messageId'] === $dispatch->message_id) {
                        return [202];
                    }
                    return [];
                }
                return true;
            }
        );

        $result = $this->imapService->verifyDispatchesForSchedule($this->schedule, $mockClient);

        $this->assertEquals(0, $result['verified_inbox']);
        $this->assertEquals(1, $result['verified_spam']);

        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::STATUS_DELIVERED, $dispatch->status);
        $this->assertEquals(WarmupDispatch::PLACEMENT_SPAM, $dispatch->inbox_placement);
    }

    public function test_uidvalidity_change_safely_resets_watermark()
    {
        // 1. Initial valid watermark
        $this->imapService->validateAndPersistWatermark($this->schedule, 'INBOX', 12345);
        $this->schedule->refresh();
        $this->assertEquals(12345, $this->schedule->imap_watermark['INBOX']['uidvalidity']);

        // 2. Mailbox recreated on server: UIDVALIDITY changed to 99999
        $persisted = $this->imapService->validateAndPersistWatermark($this->schedule, 'INBOX', 99999);
        $this->assertFalse($persisted); // indicates reset

        $this->schedule->refresh();
        $this->assertEquals(99999, $this->schedule->imap_watermark['INBOX']['uidvalidity']);
        $this->assertEquals(0, $this->schedule->imap_watermark['INBOX']['last_uid']);
        $this->assertNotNull($this->schedule->imap_watermark['INBOX']['reset_at']);
    }

    public function test_unobserved_stale_messages_are_marked_unknown_not_invented()
    {
        // Message sent 30 hours ago, not found in INBOX or Spam
        $dispatch = WarmupDispatch::create([
            'warmup_schedule_id' => $this->schedule->id,
            'user_id'            => $this->user->id,
            'gateway_id'         => $this->gateway->id,
            'recipient_email'    => 'peer@test.com',
            'subject'            => 'Stale email',
            'message_id'         => '<warmup-stale-000@voltmail.test>',
            'dispatch_date'      => '2026-10-10',
            'slot_number'        => 1,
            'status'             => WarmupDispatch::STATUS_SENT,
            'sent_at'            => Carbon::now()->subHours(30),
        ]);

        $mockClient = new ImapClient(
            customTransport: function ($action, $args) {
                if ($action === 'connect' || $action === 'login') return true;
                if ($action === 'selectFolder') return ['folder' => $args['folder'], 'uidvalidity' => 5001];
                if ($action === 'searchMessageId') return []; // Not found
                return true;
            }
        );

        $result = $this->imapService->verifyDispatchesForSchedule($this->schedule, $mockClient);

        $this->assertEquals(1, $result['marked_unknown']);
        $dispatch->refresh();
        $this->assertEquals(WarmupDispatch::PLACEMENT_UNKNOWN, $dispatch->inbox_placement);
        $this->assertNotNull($dispatch->verified_at);
    }
}
