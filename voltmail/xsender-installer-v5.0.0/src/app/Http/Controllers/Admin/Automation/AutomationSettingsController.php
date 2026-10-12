<?php

namespace App\Http\Controllers\Admin\Automation;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Automation\WorkflowTemplate;

class AutomationSettingsController extends Controller
{
    /**
     * Display automation settings page
     */
    public function index()
    {
        $title = translate('Automation Settings');
        $breadcrumbs = [
            ['name' => translate('Home'), 'url' => route('admin.dashboard')],
            ['name' => translate('Automation'), 'url' => route('admin.automation.index')],
            ['name' => translate('Settings')],
        ];

        // Get automation settings
        $settings = $this->getSettings();

        // Get templates stats
        $templateStats = [
            'total' => WorkflowTemplate::count(),
            'active' => WorkflowTemplate::where('is_active', true)->count(),
            'total_usage' => WorkflowTemplate::sum('usage_count'),
        ];

        // Get queue status
        $queueStatus = $this->getQueueStatus();

        return view('admin.automation.settings', compact(
            'title',
            'breadcrumbs',
            'settings',
            'templateStats',
            'queueStatus'
        ));
    }

    /**
     * Update automation settings
     */
    public function update(Request $request)
    {
        $request->validate([
            'automation_enabled' => 'boolean',
            'max_workflows_per_user' => 'required|integer|min:1|max:1000',
            'max_nodes_per_workflow' => 'required|integer|min:5|max:100',
            'execution_timeout_minutes' => 'required|integer|min:1|max:1440',
            'retry_failed_actions' => 'boolean',
            'retry_attempts' => 'required|integer|min:0|max:10',
            'notify_on_failure' => 'boolean',
        ]);

        $settings = [
            'automation_enabled' => $request->boolean('automation_enabled'),
            'max_workflows_per_user' => $request->max_workflows_per_user,
            'max_nodes_per_workflow' => $request->max_nodes_per_workflow,
            'execution_timeout_minutes' => $request->execution_timeout_minutes,
            'retry_failed_actions' => $request->boolean('retry_failed_actions'),
            'retry_attempts' => $request->retry_attempts,
            'notify_on_failure' => $request->boolean('notify_on_failure'),
        ];

        foreach ($settings as $key => $value) {
            $this->saveSetting($key, $value);
        }

        return back()->with('success', translate('Automation settings updated successfully'));
    }

    /**
     * Seed default templates
     */
    public function seedTemplates()
    {
        try {
            $seeder = new \Database\Seeders\WorkflowTemplateSeeder();
            $seeder->setCommand(new \Illuminate\Console\Command());
            $seeder->run();

            return response()->json([
                'status' => true,
                'message' => translate('Templates seeded successfully'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all automation settings
     */
    private function getSettings(): array
    {
        $defaults = [
            'automation_enabled' => true,
            'max_workflows_per_user' => 10,
            'max_nodes_per_workflow' => 20,
            'execution_timeout_minutes' => 60,
            'retry_failed_actions' => true,
            'retry_attempts' => 3,
            'notify_on_failure' => true,
        ];

        $settings = [];
        foreach ($defaults as $key => $default) {
            $value = DB::table('automation_settings')
                ->where('key', $key)
                ->value('value');

            $settings[$key] = $value !== null ? $value : $default;

            // Cast booleans
            if (is_bool($default)) {
                $settings[$key] = filter_var($settings[$key], FILTER_VALIDATE_BOOLEAN);
            }
        }

        return $settings;
    }

    /**
     * Save a setting
     */
    private function saveSetting(string $key, $value): void
    {
        DB::table('automation_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => is_bool($value) ? ($value ? '1' : '0') : $value, 'updated_at' => now()]
        );
    }

    /**
     * Get queue worker status info
     */
    private function getQueueStatus(): array
    {
        // Check if queue worker is running by looking at jobs table
        $pendingJobs = DB::table('jobs')->where('queue', 'automation')->count();
        $failedJobs = DB::table('failed_jobs')->where('queue', 'automation')->count();

        return [
            'pending_jobs' => $pendingJobs,
            'failed_jobs' => $failedJobs,
            'queue_driver' => config('queue.default'),
            'is_sync' => config('queue.default') === 'sync',
        ];
    }

    /**
     * List all templates (API endpoint)
     */
    public function listTemplates()
    {
        $templates = WorkflowTemplate::where('is_active', true)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $templates,
        ]);
    }
}
