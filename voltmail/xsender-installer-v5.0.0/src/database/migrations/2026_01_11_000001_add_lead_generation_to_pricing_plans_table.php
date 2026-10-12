<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if column already exists
        if (Schema::hasColumn('pricing_plans', 'lead_generation')) {
            return;
        }

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->json('lead_generation')->nullable()->after('whatsapp');
        });

        // Set default lead generation settings for existing plans
        DB::table('pricing_plans')->whereNull('lead_generation')->update([
            'lead_generation' => json_encode([
                'is_allowed' => false,
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ]),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pricing_plans', 'lead_generation')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->dropColumn('lead_generation');
            });
        }
    }
};
