<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddAiIntelligenceToPricingPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasColumn('pricing_plans', 'ai_intelligence')) {
            return;
        }

        Schema::table('pricing_plans', function (Blueprint $table) {
            $table->json('ai_intelligence')->nullable()->after('automation');
        });

        // Set default values for existing plans (disabled by default)
        DB::table('pricing_plans')->whereNull('ai_intelligence')->update([
            'ai_intelligence' => json_encode([
                'is_allowed' => false,
                'campaign_insights' => false,
                'ab_testing' => false,
                'ab_test_limit' => 0,
                'send_time_optimizer' => false,
            ])
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('pricing_plans', 'ai_intelligence')) {
            Schema::table('pricing_plans', function (Blueprint $table) {
                $table->dropColumn('ai_intelligence');
            });
        }
    }
}
