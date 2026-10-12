<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add max_sending_domains and max_tracking_domains to existing plans' email JSON.
     */
    public function up(): void
    {
        $plans = DB::table('pricing_plans')->get();

        foreach ($plans as $plan) {
            $email = json_decode($plan->email, true) ?? [];

            if (!isset($email['max_sending_domains'])) {
                $email['max_sending_domains'] = 3;
            }
            if (!isset($email['max_tracking_domains'])) {
                $email['max_tracking_domains'] = 2;
            }

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update(['email' => json_encode($email)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $plans = DB::table('pricing_plans')->get();

        foreach ($plans as $plan) {
            $email = json_decode($plan->email, true) ?? [];

            unset($email['max_sending_domains']);
            unset($email['max_tracking_domains']);

            DB::table('pricing_plans')
                ->where('id', $plan->id)
                ->update(['email' => json_encode($email)]);
        }
    }
};
