<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('subscriptions')) {
            if (!Schema::hasColumn('subscriptions', 'auto_renewal')) {
                Schema::table('subscriptions', function (Blueprint $table) {
                    $table->boolean('auto_renewal')->default(false)->after('status');
                    $table->string('renewal_method', 20)->nullable()->after('auto_renewal')
                        ->comment('wallet or gateway');
                    $table->unsignedBigInteger('renewal_gateway_id')->nullable()->after('renewal_method')
                        ->comment('FK to payment_methods for card-based renewal');
                    $table->string('gateway_customer_token', 255)->nullable()->after('renewal_gateway_id')
                        ->comment('Stripe customer ID, PayPal agreement ID, etc.');
                    $table->string('gateway_payment_method_token', 255)->nullable()->after('gateway_customer_token')
                        ->comment('Stripe payment method ID, etc.');
                    $table->unsignedTinyInteger('renewal_attempts')->default(0)->after('gateway_payment_method_token');
                    $table->timestamp('last_renewal_attempt')->nullable()->after('renewal_attempts');
                    $table->timestamp('renewal_reminder_sent_at')->nullable()->after('last_renewal_attempt');
                });
            }
        }

        // Add wallet top-up tracking to users
        if (Schema::hasTable('users')) {
            if (!Schema::hasColumn('users', 'auto_renewal_preference')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('auto_renewal_preference', 20)->nullable()->after('wallet_balance')
                        ->comment('wallet, gateway, or null (disabled)');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('subscriptions')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $cols = ['auto_renewal', 'renewal_method', 'renewal_gateway_id',
                         'gateway_customer_token', 'gateway_payment_method_token',
                         'renewal_attempts', 'last_renewal_attempt', 'renewal_reminder_sent_at'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('subscriptions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
