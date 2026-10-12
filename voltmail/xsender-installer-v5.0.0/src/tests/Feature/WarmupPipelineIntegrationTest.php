<?php

namespace Tests\Feature;

use App\Http\Utility\SendMail;
use App\Models\ContentGenerationHistory;
use App\Models\ContentResource;
use App\Models\Gateway;
use App\Models\Language;
use App\Models\PricingPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use App\Services\Warmup\ImapClient;
use App\Services\Warmup\WarmupImapService;
use App\Services\Warmup\WarmupOutboxDispatcher;
use App\Services\Warmup\WarmupSchedulerService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupPipelineIntegrationTest extends TestCase
{
    protected User $tenantA;
    protected User $tenantB;
    protected Gateway $gatewayA;
    protected Gateway $gatewayB;
    protected WarmupSchedule $scheduleA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            \App\Http\Middleware\SoftwareVerification::class,
            \App\Http\Middleware\PurchaseValidation::class,
            \App\Http\Middleware\CheckUserStatus::class,
            \App\Http\Middleware\AuthorizationMiddleware::class,
            \App\Http\Middleware\VersionUpgrade::class,
            \App\Http\Middleware\Sanitization::class,
        ]);

        Schema::dropIfExists('content_generation_histories');
        Schema::dropIfExists('content_resources');
        Schema::dropIfExists('warmup_dispatches');
        Schema::dropIfExists('warmup_schedules');
        Schema::dropIfExists('gateways');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('pricing_plans');
        Schema::dropIfExists('users');
        Schema::dropIfExists('languages');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('support_tickets');

        $langMigration = require __DIR__ . '/../../database/migrations/2025_04_30_071022_create_languages_table.php';
        $langMigration->up();

        $settingsMigration = require __DIR__ . '/../../database/migrations/2025_04_30_071152_create_settings_table.php';
        $settingsMigration->up();

        $ticketMigration = require __DIR__ . '/../../database/migrations/2025_04_30_071222_create_support_tickets_table.php';
        $ticketMigration->up();

        $planMigration = require __DIR__ . '/../../database/migrations/2025_04_30_071146_create_pricing_plans_table.php';
        $planMigration->up();

        $subMigration = require __DIR__ . '/../../database/migrations/2025_04_30_071204_create_subscriptions_table.php';
        $subMigration->up();

        $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
        $schedMigration->up();

        $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
        $dispMigration->up();

        $crMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000001_create_content_resources_table.php';
        $crMigration->up();

        $cghMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000002_create_content_generation_histories_table.php';
        $cghMigration->up();

        Language::create([
            'name'       => 'English',
            'code'       => 'en',
            'is_default' => '1',
            'status'     => '1',
        ]);

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

        $this->tenantA = User::create(['email' => 'pipeline_a@voltmail.test']);
        $this->tenantB = User::create(['email' => 'pipeline_b@voltmail.test']);

        $plan = PricingPlan::create([
            'name'   => 'Pro Plan',
            'type'   => '0',
            'status' => '1',
            'email'  => json_encode(['credits' => 10000, 'gateway_limit' => 10]),
        ]);

        Subscription::create([
            'user_id'      => $this->tenantA->id,
            'plan_id'      => $plan->id,
            'status'       => 1,
            'expired_date' => now()->addMonth(),
        ]);

        Subscription::create([
            'user_id'      => $this->tenantB->id,
            'plan_id'      => $plan->id,
            'status'       => 1,
            'expired_date' => now()->addMonth(),
        ]);

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

        $this->gatewayA = Gateway::create([
            'user_id'   => $this->tenantA->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'Gateway A',
            'address'   => 'sales@tenanta.com',
            'meta_data' => [
                'host'       => 'smtp.tenanta.com',
                'driver'     => 'SMTP',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user_a',
                'password'   => 'pass_a',
                'imap'       => [
                    'host'       => 'imap.tenanta.com',
                    'port'       => 993,
                    'encryption' => 'ssl',
                    'username'   => 'user_a',
                    'password'   => Crypt::encryptString('pass_a'),
                ],
            ],
            'status'    => 'active',
        ]);

        $this->gatewayB = Gateway::create([
            'user_id'   => $this->tenantB->id,
            'channel'   => 'email',
            'type'      => 'smtp',
            'name'      => 'Gateway B',
            'address'   => 'sales@tenantb.com',
            'meta_data' => [
                'host'       => 'smtp.tenantb.com',
                'driver'     => 'SMTP',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user_b',
                'password'   => 'pass_b',
            ],
            'status'    => 'active',
        ]);

        $this->scheduleA = WarmupSchedule::create([
            'user_id'             => $this->tenantA->id,
            'gateway_id'          => $this->gatewayA->id,
            'status'              => 'active',
            'daily_limit'         => 20,
            'rampup_increment'    => 2,
            'current_day'         => 1,
            'current_daily_quota' => 5,
            'start_date'          => '2026-10-12',
        ]);

        ContentResource::create([
            'user_id'             => $this->tenantA->id,
            'name'                => 'Tenant A Profile',
            'company_name'        => 'Acme Cloud',
            'business_type'       => 'Cloud Host',
            'company_description' => 'Fast, secure VPS and cluster solutions.',
            'industry'            => 'Hosting',
            'target_audience'     => 'Engineers',
            'status'              => 'active',
            'is_default'          => true,
        ]);
    }

    public function test_full_pipeline_schedule_dispatch_and_imap_verification()
    {
        // 1. Stage 1: Scheduler generates slots for today
        $scheduler = new WarmupSchedulerService();
        $summary = $scheduler->scheduleDailyDispatches('2026-10-12');

        $this->assertEquals(1, $summary['schedules_processed']);
        $this->assertEquals(5, $summary['slots_generated']);

        // Verify outbox has 5 pending slots
        $this->assertEquals(5, WarmupDispatch::where('user_id', $this->tenantA->id)->where('status', WarmupDispatch::STATUS_PENDING)->count());

        // 2. Stage 2: Outbox Dispatcher claims and sends slots
        $mockSendMail = $this->createMock(SendMail::class);
        $mockSendMail->method('send')->willReturn(true);

        $dispatcher = new WarmupOutboxDispatcher(null, $mockSendMail);
        $claimed = $dispatcher->claimDueSlots(5, 'test_worker');
        $this->assertCount(5, $claimed);

        foreach ($claimed as $dispatch) {
            $res = $dispatcher->dispatchSlot($dispatch);
            $this->assertTrue($res['success']);
            $this->assertEquals(WarmupDispatch::STATUS_SENT, $res['status']);
        }

        // Verify all 5 are in SENT state with RFC 2822 Message-ID
        $sentDispatches = WarmupDispatch::where('user_id', $this->tenantA->id)->where('status', WarmupDispatch::STATUS_SENT)->get();
        $this->assertCount(5, $sentDispatches);
        foreach ($sentDispatches as $d) {
            $this->assertStringStartsWith('<warmup-', $d->message_id);
            $this->assertStringEndsWith('@tenanta.com>', $d->message_id);
        }

        // Verify content generation history was tracked
        $this->assertEquals(5, ContentGenerationHistory::where('user_id', $this->tenantA->id)->count());

        // 3. Stage 3: IMAP Verification
        $matchedMsgId = $sentDispatches->first()->message_id;

        $mockImapClient = new ImapClient(
            customTransport: function ($action, $args) use ($matchedMsgId) {
                if ($action === 'connect' || $action === 'login') return true;
                if ($action === 'selectFolder') {
                    return ['folder' => $args['folder'], 'uidvalidity' => 7777, 'exists' => 20];
                }
                if ($action === 'searchMessageId') {
                    // Match the first message in INBOX
                    return ($args['messageId'] === $matchedMsgId) ? [55] : [];
                }
                return true;
            }
        );

        $imapService = new WarmupImapService(null, $mockImapClient);
        $imapResult = $imapService->verifyDispatchesForSchedule($this->scheduleA);

        $this->assertEquals('success', $imapResult['status']);
        $this->assertEquals(1, $imapResult['verified_inbox']);

        // Check verified dispatch
        $verified = WarmupDispatch::where('message_id', $matchedMsgId)->first();
        $this->assertEquals(WarmupDispatch::STATUS_DELIVERED, $verified->status);
        $this->assertEquals(WarmupDispatch::PLACEMENT_INBOX, $verified->inbox_placement);
        $this->assertNotNull($verified->verified_at);
    }

    public function test_tenant_isolation_prevents_tenant_b_accessing_tenant_a_pipeline()
    {
        // Generate slots for Tenant A
        $scheduler = new WarmupSchedulerService();
        $scheduler->scheduleDailyDispatches('2026-10-12');

        $tenantADispatch = WarmupDispatch::where('user_id', $this->tenantA->id)->first();
        $this->assertNotNull($tenantADispatch);

        // Tenant B cannot access Tenant A's dispatch via route/controller
        $response = $this->actingAs($this->tenantB)->get(route('user.warmup.show', $this->scheduleA->id));
        $response->assertRedirect(route('user.warmup.index')); // Tenant B denied access
    }
}
