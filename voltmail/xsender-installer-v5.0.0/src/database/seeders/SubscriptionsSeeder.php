<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('subscriptions')->insert([
            [
                'id' => 1,
                'user_id' => 1,
                'plan_id' => 1,
                'amount' => 0.00000000,
                'expired_date' => '2035-12-31 13:51:46',
                'trx_number' => 'CN9GPHB90I',
                'status' => 1,
                'created_at' => '2024-09-17 13:51:46',
                'updated_at' => '2024-09-17 13:51:46',
            ],
            [
                'id' => 2,
                'user_id' => 2,
                'plan_id' => 1,
                'amount' => 0.00000000,
                'expired_date' => '2025-05-10 00:47:33',
                'trx_number' => 'RZS4I2SFOA',
                'status' => 1,
                'created_at' => '2025-04-30 00:47:33',
                'updated_at' => '2025-04-30 00:47:33',
            ],
        ]);
    }
}