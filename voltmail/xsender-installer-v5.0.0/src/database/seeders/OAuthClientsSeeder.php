<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OAuthClientsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('oauth_clients')->insert([
            [
                'id' => 974,
                'user_id' => null,
                'name' => 'xsender',
                'secret' => 'sVI4taykksvLiUXz01w3lTjq0Ao5vfTIhTXXD6I1',
                'provider' => null,
                'redirect' => 'http://localhost',
                'personal_access_client' => 1,
                'password_client' => 0,
                'revoked' => 0,
                'created_at' => '2024-09-17 13:50:56',
                'updated_at' => '2024-09-17 13:50:56',
            ],
        ]);
    }
}