<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FrontendSectionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     * @throws \Exception
     */
    public function run()
    {
        $sqlFilePath = resource_path('database/frontend_sections.sql');
        
        if (!File::exists($sqlFilePath)) {
            throw new \Exception("SQL file not found at: {$sqlFilePath}");
        }

        try {

            $sql = File::get($sqlFilePath);
            DB::unprepared($sql);
            $this->command->info('Frontend sections seeded successfully.');

        } catch (\Exception $e) {

            $this->command->error('Failed to seed frontend sections: ' . $e->getMessage());
            throw $e;
        }
    }
}