<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OAuthPersonalAccessClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('oauth_personal_access_clients')->insert([
            [
                'id' => 1,
                'client_id' => 974,
                'created_at' => '2022-09-19 20:35:57',
                'updated_at' => '2022-09-19 20:35:57',
            ],
        ]);
    }
}