<?php

namespace Database\Seeders;

use App\Models\Automation\WorkflowTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WorkflowTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = WorkflowTemplate::getDefaultTemplates();

        foreach ($templates as $template) {
            WorkflowTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                array_merge($template, [
                    'uid' => Str::random(32),
                    'is_active' => true,
                    'usage_count' => 0,
                ])
            );
        }

        $this->command->info('Workflow templates seeded successfully!');
    }
}
