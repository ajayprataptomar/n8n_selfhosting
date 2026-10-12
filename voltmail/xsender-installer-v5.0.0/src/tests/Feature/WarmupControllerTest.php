<?php

namespace Tests\Feature;

use App\Models\Gateway;
use App\Models\User;
use App\Models\WarmupSchedule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarmupControllerTest extends TestCase
{
    protected User $tenantA;
    protected User $tenantB;
    protected Gateway $gatewayA;
    protected Gateway $gatewayB;

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

        \App\Models\Language::create([
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
            $table->timestamps();
        });

        $this->tenantA = User::create(['email' => 'feature_a@voltmail.test']);
        $this->tenantB = User::create(['email' => 'feature_b@voltmail.test']);

        $plan = \App\Models\PricingPlan::create([
            'name'   => 'Pro Plan',
            'type'   => '0',
            'status' => '1',
            'email'  => json_encode(['credits' => 10000, 'gateway_limit' => 10]),
        ]);

        \App\Models\Subscription::create([
            'user_id'      => $this->tenantA->id,
            'plan_id'      => $plan->id,
            'status'       => 1,
            'expired_date' => now()->addMonth(),
        ]);

        \App\Models\Subscription::create([
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

        $schedMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000003_create_warmup_schedules_table.php';
        $schedMigration->up();

        $dispMigration = require __DIR__ . '/../../database/migrations/2026_10_12_000004_create_warmup_dispatches_table.php';
        $dispMigration->up();

        $this->gatewayA = Gateway::create([
            'user_id' => $this->tenantA->id,
            'channel' => 'email',
            'type'    => 'smtp',
            'name'    => 'Gateway A',
            'address' => 'a@domain.com',
            'meta_data' => [
                'host'       => 'smtp.domain.com',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user_a',
                'password'   => 'pass_a',
            ],
            'status'  => 'active',
        ]);

        $this->gatewayB = Gateway::create([
            'user_id' => $this->tenantB->id,
            'channel' => 'email',
            'type'    => 'smtp',
            'name'    => 'Gateway B',
            'address' => 'b@domain.com',
            'meta_data' => [
                'host'       => 'smtp.domain.com',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'user_b',
                'password'   => 'pass_b',
            ],
            'status'  => 'active',
        ]);
    }

    public function test_index_displays_warmup_schedules()
    {
        $response = $this->actingAs($this->tenantA)->get(route('user.warmup.index'));
        $response->assertStatus(200);
        $response->assertViewHas('schedules');
    }

    public function test_create_displays_eligible_gateways_for_authenticated_tenant()
    {
        $response = $this->actingAs($this->tenantA)->get(route('user.warmup.create'));
        $response->assertStatus(200);
        $response->assertViewHas('eligibleGateways', function ($gateways) {
            return $gateways->count() === 1 && $gateways->first()->id === $this->gatewayA->id;
        });
    }

    public function test_tenant_a_can_store_warmup_schedule_reusing_gateway()
    {
        $response = $this->actingAs($this->tenantA)->post(route('user.warmup.store'), [
            'gateway_id'         => $this->gatewayA->id,
            'daily_limit'        => 60,
            'rampup_increment'   => 4,
            'starting_quota'     => 10,
            'reply_rate_percent' => 30,
        ]);

        $response->assertRedirect(route('user.warmup.index'));
        $this->assertDatabaseHas('warmup_schedules', [
            'user_id'     => $this->tenantA->id,
            'gateway_id'  => $this->gatewayA->id,
            'daily_limit' => 60,
        ]);
    }

    public function test_tenant_b_cannot_attach_warmup_to_tenant_a_gateway()
    {
        $response = $this->actingAs($this->tenantB)->post(route('user.warmup.store'), [
            'gateway_id'         => $this->gatewayA->id, // Tenant A's gateway!
            'daily_limit'        => 50,
            'rampup_increment'   => 2,
        ]);

        // Should return back with error notify
        $this->assertDatabaseMissing('warmup_schedules', [
            'user_id'    => $this->tenantB->id,
            'gateway_id' => $this->gatewayA->id,
        ]);
    }

    public function test_tenant_can_toggle_warmup_status()
    {
        $schedule = WarmupSchedule::create([
            'user_id'             => $this->tenantA->id,
            'gateway_id'          => $this->gatewayA->id,
            'daily_limit'         => 40,
            'rampup_increment'    => 2,
            'current_daily_quota' => 5,
            'status'              => 'active',
        ]);

        $response = $this->actingAs($this->tenantA)->post(route('user.warmup.toggle-status', $schedule->id));
        $response->assertRedirect();

        $schedule->refresh();
        $this->assertEquals('paused', $schedule->status);
    }

    public function test_tenant_can_delete_warmup_schedule()
    {
        $schedule = WarmupSchedule::create([
            'user_id'             => $this->tenantA->id,
            'gateway_id'          => $this->gatewayA->id,
            'daily_limit'         => 40,
            'rampup_increment'    => 2,
            'current_daily_quota' => 5,
            'status'              => 'active',
        ]);

        $response = $this->actingAs($this->tenantA)->delete(route('user.warmup.destroy', $schedule->id));
        $response->assertRedirect(route('user.warmup.index'));

        $this->assertDatabaseMissing('warmup_schedules', ['id' => $schedule->id]);
    }
}
