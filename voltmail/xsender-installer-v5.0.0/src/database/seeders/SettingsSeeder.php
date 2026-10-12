<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Enums\StatusEnum;
class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = config('site_settings');
        $existingKeys = Setting::pluck('key')->toArray();

        foreach ($settings as $key => $value) {
            if (!in_array($key, $existingKeys)) {
                // Only insert NEW settings — never overwrite user-customized values
                Setting::insertOrIgnore([
                    'key'        => $key,
                    'value'      => $value,
                    'uid'        => str_unique(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        optimize_clear();
    }
}
