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
        if (Schema::hasColumn('pricing_plans', 'automation')) {
            return;
        }

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->json('automation')->nullable()->after('lead_generation');
        });

        // Set default values for existing plans
        DB::table('pricing_plans')->whereNull('automation')->update([
            'automation' => json_encode([
                'is_allowed' => false,
                'workflow_limit' => 0,
                'execution_limit' => 0,
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('pricing_plans', 'automation')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->dropColumn('automation');
            });
        }
    }
};
