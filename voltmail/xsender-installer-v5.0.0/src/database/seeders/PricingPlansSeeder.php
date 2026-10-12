<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PricingPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('pricing_plans')->insert([
            [
                'id' => 1,
                'name' => 'Free',
                'type' => '1',
                'carry_forward' => '0',
                'whatsapp' => '{"is_allowed":true,"gateway_limit":2,"credits":10,"credits_per_day":2}',
                'email' => '{"is_allowed":true,"credits":10,"credits_per_day":2}',
                'sms' => '{"is_allowed":true,"credits":10,"credits_per_day":2,"android":{"is_allowed":true}}',
                'amount' => 0.00000000,
                'duration' => 10,
                'status' => '1',
                'recommended_status' => '0',
                'created_at' => '2024-07-12 12:56:01',
                'updated_at' => '2024-07-12 13:01:57',
                'description' => 'This plan is to demonstrate the onboard bonus',
            ],
            [
                'id' => 2,
                'name' => 'Basic',
                'type' => '0',
                'carry_forward' => '1',
                'whatsapp' => '{"is_allowed":true,"gateway_limit":3,"credits":50,"credits_per_day":5}',
                'email' => '{"is_allowed":true,"gateway_limit":5,"allowed_gateways":{"smtp":1,"sendgrid":1,"aws":1,"mailjet":1,"mailgun":1},"credits":50,"credits_per_day":5}',
                'sms' => '{"is_allowed":true,"gateway_limit":10,"allowed_gateways":{"NEXMO":1,"TWILIO":1,"MESSAGE_BIRD":1,"TEXT_MAGIC":1,"CLICKA_TELL":1,"INFOBIP":1,"SMS_BROADCAST":1,"MIM_SMS":1,"AJURA_SMS":1,"MSG91":1},"credits":50,"credits_per_day":5,"android":{"is_allowed":true,"gateway_limit":3}}',
                'amount' => 15.00000000,
                'duration' => 5,
                'status' => '1',
                'recommended_status' => '0',
                'created_at' => '2024-07-12 12:59:28',
                'updated_at' => '2024-07-12 13:01:57',
                'description' => 'This plan demonstrates the following rules',
            ],
            [
                'id' => 3,
                'name' => 'Standard',
                'type' => '1',
                'carry_forward' => '1',
                'whatsapp' => '{"is_allowed":true,"gateway_limit":0,"credits":-1,"credits_per_day":0}',
                'email' => '{"is_allowed":true,"credits":-1,"credits_per_day":0}',
                'sms' => '{"is_allowed":true,"credits":-1,"credits_per_day":0,"android":{"is_allowed":true}}',
                'amount' => 24.99000000,
                'duration' => 20,
                'status' => '1',
                'recommended_status' => '1',
                'created_at' => '2024-07-12 13:00:32',
                'updated_at' => '2024-07-12 13:01:57',
                'description' => 'This plan demonstrates the following rules',
            ],
            [
                'id' => 4,
                'name' => 'Premium',
                'type' => '0',
                'carry_forward' => '1',
                'whatsapp' => '{"is_allowed":true,"gateway_limit":5,"credits":-1,"credits_per_day":0}',
                'email' => '{"is_allowed":true,"gateway_limit":25,"allowed_gateways":{"smtp":5,"sendgrid":5,"aws":5,"mailjet":5,"mailgun":5},"credits":-1,"credits_per_day":0}',
                'sms' => '{"is_allowed":true,"gateway_limit":50,"allowed_gateways":{"NEXMO":5,"TWILIO":5,"MESSAGE_BIRD":5,"TEXT_MAGIC":5,"CLICKA_TELL":5,"INFOBIP":5,"SMS_BROADCAST":5,"MIM_SMS":5,"AJURA_SMS":5,"MSG91":5},"credits":-1,"credits_per_day":0,"android":{"is_allowed":true,"gateway_limit":10}}',
                'amount' => 39.99000000,
                'duration' => 30,
                'status' => '1',
                'recommended_status' => '0',
                'created_at' => '2024-07-12 13:01:50',
                'updated_at' => '2024-07-12 13:01:57',
                'description' => 'This plan demonstrates the following rules',
            ],
        ]);
    }
}