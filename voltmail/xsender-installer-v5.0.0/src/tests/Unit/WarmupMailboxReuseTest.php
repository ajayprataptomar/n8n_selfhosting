<?php

namespace Tests\Unit;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\WarmupMailboxService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupMailboxReuseTest extends TestCase
{
    protected WarmupMailboxService $service;
    protected User $tenantA;
    protected User $tenantB;
    protected Gateway $gatewayA;

    protected function setUp(): void
    {
        parent::setUp();

        // Prepare tables in in-memory SQLite
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

        $this->service = new WarmupMailboxService();

        $this->tenantA = User::create(['email' => 'tenant_a@voltmail.test']);
        $this->tenantB = User::create(['email' => 'tenant_b@voltmail.test']);

        // Configure an existing email gateway for Tenant A with saved SMTP credentials
        $this->gatewayA = Gateway::create([
            'user_id' => $this->tenantA->id,
            'channel' => 'email',
            'type'    => 'smtp',
            'name'    => 'Tenant A Sales SMTP',
            'address' => 'sales@tenanta.com',
            'meta_data' => [
                'host'       => 'smtp.mailtrap.io',
                'driver'     => 'SMTP',
                'port'       => 2525,
                'encryption' => 'tls',
                'username'   => 'smtp_user_123',
                'password'   => 'super_secret_smtp_pass',
            ],
            'status'  => 'active',
        ]);
    }

    public function test_existing_configured_mailbox_can_be_selected_and_reused_without_reentering_smtp_credentials()
    {
        // Tenant A selects their existing gateway for warmup
        // No SMTP passwords or credentials passed in input
        $schedule = $this->service->attachWarmupToGateway(
            userId: $this->tenantA->id,
            gatewayId: $this->gatewayA->id,
            scheduleSettings: [
                'daily_limit'         => 50,
                'rampup_increment'    => 3,
                'starting_quota'      => 10,
                'reply_rate_percent'  => 35,
            ]
        );

        $this->assertInstanceOf(WarmupSchedule::class, $schedule);
        $this->assertEquals($this->tenantA->id, $schedule->user_id);
        $this->assertEquals($this->gatewayA->id, $schedule->gateway_id);
        $this->assertEquals(50, $schedule->daily_limit);
        $this->assertEquals(3, $schedule->rampup_increment);
        $this->assertEquals(10, $schedule->current_daily_quota);

        // Verify gateway was NOT duplicated or overwritten
        $this->assertEquals(1, Gateway::count());
        $gatewayReloaded = Gateway::find($this->gatewayA->id);
        $this->assertEquals('smtp.mailtrap.io', $gatewayReloaded->meta_data['host']);
        $this->assertEquals('super_secret_smtp_pass', $gatewayReloaded->meta_data['password']);
    }

    public function test_missing_imap_configuration_can_be_supplemented_without_asking_for_smtp_passwords()
    {
        $this->assertFalse($this->service->hasImapConfig($this->gatewayA));

        // Supplement only missing IMAP info
        $imapData = [
            'host'       => 'imap.mailtrap.io',
            'port'       => 993,
            'encryption' => 'ssl',
            'username'   => 'imap_user_123',
            'password'   => 'imap_secret_pass',
        ];

        $schedule = $this->service->attachWarmupToGateway(
            userId: $this->tenantA->id,
            gatewayId: $this->gatewayA->id,
            scheduleSettings: [
                'daily_limit' => 40,
            ],
            imapSettings: $imapData
        );

        $this->gatewayA->refresh();
        $this->assertTrue($this->service->hasImapConfig($this->gatewayA));

        // Verify SMTP was preserved
        $this->assertEquals('super_secret_smtp_pass', $this->gatewayA->meta_data['password']);

        // Verify IMAP password is encrypted in meta_data
        $this->assertNotEquals('imap_secret_pass', $this->gatewayA->meta_data['imap']['password']);

        // Verify decrypted IMAP credentials
        $decryptedImap = $this->service->getImapConfig($this->gatewayA);
        $this->assertEquals('imap.mailtrap.io', $decryptedImap['host']);
        $this->assertEquals(993, $decryptedImap['port']);
        $this->assertEquals('imap_user_123', $decryptedImap['username']);
        $this->assertEquals('imap_secret_pass', $decryptedImap['password']);
    }

    public function test_tenant_b_cannot_use_or_warmup_tenant_a_mailbox()
    {
        $this->expectException(AuthorizationException::class);

        // Tenant B attempts to attach Tenant A's gateway
        $this->service->attachWarmupToGateway(
            userId: $this->tenantB->id,
            gatewayId: $this->gatewayA->id,
            scheduleSettings: [
                'daily_limit' => 30,
            ]
        );
    }

    public function test_tenant_b_cannot_modify_or_detach_tenant_a_warmup_schedule()
    {
        $schedule = $this->service->attachWarmupToGateway(
            userId: $this->tenantA->id,
            gatewayId: $this->gatewayA->id,
            scheduleSettings: ['daily_limit' => 40]
        );

        $this->expectException(AuthorizationException::class);

        // Tenant B attempts to update Tenant A's schedule
        $this->service->updateSchedule($this->tenantB->id, $schedule->id, ['daily_limit' => 100]);
    }

    public function test_outbox_slot_uniqueness_guarantees_idempotency()
    {
        $schedule = $this->service->attachWarmupToGateway(
            userId: $this->tenantA->id,
            gatewayId: $this->gatewayA->id,
            scheduleSettings: ['daily_limit' => 40]
        );

        // First dispatch in slot 1
        WarmupDispatch::create([
            'warmup_schedule_id' => $schedule->id,
            'user_id'            => $this->tenantA->id,
            'gateway_id'         => $this->gatewayA->id,
            'recipient_email'    => 'peer1@warmup.test',
            'subject'            => 'Checking in',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'status'             => 'sent',
        ]);

        $this->assertEquals(1, WarmupDispatch::count());

        // Attempt duplicate insertion into the exact same slot on same date
        $this->expectException(QueryException::class);

        WarmupDispatch::create([
            'warmup_schedule_id' => $schedule->id,
            'user_id'            => $this->tenantA->id,
            'gateway_id'         => $this->gatewayA->id,
            'recipient_email'    => 'peer2@warmup.test',
            'subject'            => 'Duplicate slot attempt',
            'dispatch_date'      => '2026-10-12',
            'slot_number'        => 1,
            'status'             => 'sent',
        ]);
    }

    public function test_get_eligible_gateways_returns_only_authenticated_tenant_gateways()
    {
        // Create a gateway for Tenant B
        Gateway::create([
            'user_id' => $this->tenantB->id,
            'channel' => 'email',
            'type'    => 'smtp',
            'name'    => 'Tenant B SMTP',
            'address' => 'b@tenantb.com',
            'meta_data' => ['host' => 'smtp.tenantb.com', 'port' => 587, 'username' => 'b', 'password' => 'p'],
            'status'  => 'active',
        ]);

        $eligibleA = $this->service->getEligibleGateways($this->tenantA->id);
        $this->assertCount(1, $eligibleA);
        $this->assertEquals($this->gatewayA->id, $eligibleA->first()->id);

        $eligibleB = $this->service->getEligibleGateways($this->tenantB->id);
        $this->assertCount(1, $eligibleB);
        $this->assertEquals('Tenant B SMTP', $eligibleB->first()->name);
    }

    public function test_sendmail_service_accepts_existing_gateway_directly_without_duplicate_transport()
    {
        $sendMail = new \App\Http\Utility\SendMail();
        $creds = $sendMail->getCredentials(\App\Enums\System\ChannelTypeEnum::EMAIL, $this->gatewayA->type, $this->gatewayA);

        $this->assertIsArray($creds);
        $this->assertEquals('smtp.mailtrap.io', $creds['host']);
        $this->assertEquals('smtp_user_123', $creds['username']);
        $this->assertEquals('super_secret_smtp_pass', $creds['password']);
    }
}

