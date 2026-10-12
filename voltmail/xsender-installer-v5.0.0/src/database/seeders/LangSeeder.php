<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('languages')->insert([
            [
                'id' => 1,
                'uid' => "d6db80bc-0aee-434c-845a-826b0ed8fe84",
                'name' => "English",
                'code' => "us",
                'is_default' => "1",
                'status' => "1",
                'ltr' => "1",
                'created_at' => "2024-07-13 00:40:48",
                'updated_at' => "2024-07-13 00:40:48",
            ],
        ]);
    }
}