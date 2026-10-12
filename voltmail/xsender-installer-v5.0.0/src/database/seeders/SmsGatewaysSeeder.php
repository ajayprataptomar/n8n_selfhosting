<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SmsGatewaysSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sms_gateways')->insert([
            [
                'id' => 1,
                'gateway_code' => '101NEXMO',
                'name' => 'nexmo',
                'credential' => '{"api_key":"#12335","api_secret":"#","sender_id":"#"}',
                'status' => 2,
                'created_at' => '2022-09-09 14:33:40',
                'updated_at' => '2023-11-16 23:35:24',
            ],
            [
                'id' => 2,
                'gateway_code' => '102TWILIO',
                'name' => 'twilio',
                'credential' => '{"account_sid":"#","auth_token":"#","from_number":"#","sender_id":"#"}',
                'status' => 1,
                'created_at' => '2022-09-09 14:33:40',
                'updated_at' => '2023-10-25 20:10:21',
            ],
            [
                'id' => 3,
                'gateway_code' => '103MESSAGE_BIRD',
                'name' => 'message Bird',
                'credential' => '{"access_key":"jhkhjutrghdf","sender_id":"123426"}',
                'status' => 1,
                'created_at' => '2022-09-09 12:00:00',
                'updated_at' => '2023-11-18 19:34:20',
            ],
            [
                'id' => 4,
                'gateway_code' => '104TEXT_MAGIC',
                'name' => 'Text Magic',
                'credential' => '{"api_key":"#","text_magic_username":"#","sender_id":"#"}',
                'status' => 1,
                'created_at' => '2022-09-09 14:33:40',
                'updated_at' => '2023-08-29 22:58:14',
            ],
            [
                'id' => 5,
                'gateway_code' => '105CLICKA_TELL',
                'name' => 'Clickatell',
                'credential' => '{"clickatell_api_key":"#","sender_id":"#"}',
                'status' => 1,
                'created_at' => '2022-09-09 14:20:14',
                'updated_at' => '2023-10-27 00:15:56',
            ],
            [
                'id' => 6,
                'gateway_code' => '106INFOBIP',
                'name' => 'InfoBip',
                'credential' => '{"infobip_base_url":"ejr1q3.api.infobip.com","infobip_api_key":"cf92d0da252958d69dc19f6d8bf4efc4-58719726-9d3d-4f43-a0c0-e1d50ea0a7b6","sender_id":"igen"}',
                'status' => 1,
                'created_at' => '2022-09-09 14:20:14',
                'updated_at' => '2023-11-19 07:05:17',
            ],
            [
                'id' => 7,
                'gateway_code' => '107SMS_BROADCAST',
                'name' => 'SMS Broadcast',
                'credential' => '{"sms_broadcast_username":"#","sms_broadcast_password":"#","sender_id":"#"}',
                'status' => 1,
                'created_at' => '2022-09-09 14:20:14',
                'updated_at' => '2023-10-28 01:42:41',
            ],
            [
                'id' => 8,
                'gateway_code' => '108MIM_SMS',
                'name' => 'MiM SMS',
                'credential' => '{"api_url":"#","api_key":"##","sender_id":"#"}',
                'status' => 1,
                'created_at' => '2023-02-18 14:20:14',
                'updated_at' => '2023-08-13 06:46:10',
            ],
            [
                'id' => 9,
                'gateway_code' => '109AJURA_SMS',
                'name' => 'Ajura SMS (Reve System)',
                'credential' => '{"api_url":"https://smpp.ajuratech.com:7790/sendtext","api_key":"##","secret_key":"#","sender_id":"0000"}',
                'status' => 1,
                'created_at' => '2023-02-18 14:20:14',
                'updated_at' => '2023-09-07 01:39:57',
            ],
            [
                'id' => 10,
                'gateway_code' => '110MSG91',
                'name' => 'MSG91',
                'credential' => '{"api_url":"https://control.msg91.com/api/v5/flow/","auth_key":"##","flow_id":"##","sender_id":"0000"}',
                'status' => 2,
                'created_at' => '2023-02-18 14:20:14',
                'updated_at' => '2023-11-08 22:35:41',
            ],
        ]);
    }
}