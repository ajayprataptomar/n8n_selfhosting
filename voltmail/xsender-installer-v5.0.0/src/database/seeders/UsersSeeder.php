<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        try {
            DB::table('users')->insert([
                [
                    'uid' => '5HjURhie-FgnsqgeobhbBoc-zFp7ANQe',
                    'name' => 'Xsender Demo',
                    'email' => 'xsender@demo.test',
                    'google_id' => null,
                    'sms_credit' => 10000,
                    'email_credit' => 10000,
                    'whatsapp_credit' => '10000',
                    'api_sms_method' => '1',
                    'webhook_token' => '###',
                    'contact_meta_data' => null,
                    'address' => '{"address":null,"city":null,"state":null,"zip":null}',
                    'image' => '67de83a579af61742635941.webp',
                    'password' => '$2y$10$f6.dXIMZ8Eu/5G36O.iTs.3rUiTh0z4b0Ee9cQv.QOtEKdrZ90H.C',
                    'status' => '1',
                    'api_key' => null,
                    'gateway_credentials' => null,
                    'email_verified_send_at' => null,
                    'created_at' => '2024-09-17 13:51:46',
                    'updated_at' => '2025-03-22 03:32:21',
                    'email_verified_status' => '1',
                    'email_verified_code' => null,
                    'email_verified_at' => '2024-09-17 13:51:46',
                ],
            ]);

            $this->command->info('Users seeded successfully.');
        } catch (\Exception $e) {
            $this->command->error('Failed to seed users: ' . $e->getMessage());
            throw $e;
        }
    }
}