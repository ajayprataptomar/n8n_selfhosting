<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use App\Services\System\AutomationService;

class SystemController extends Controller
{
    /**
     * Clear all application caches
     * Uses comprehensive cache clearing for maximum compatibility
     * Works on servers without terminal access
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cacheClear() {
        $errors = [];

        // Method 1: Use our comprehensive optimize_clear helper
        try {
            if (function_exists('optimize_clear')) {
                $result = optimize_clear();
                if (!$result) {
                    $errors[] = 'optimize_clear returned false';
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'optimize_clear: ' . $e->getMessage();
        }

        // Method 2: Try Artisan command as backup
        try {
            Artisan::call('optimize:clear');
        } catch (\Exception $e) {
            $errors[] = 'artisan: ' . $e->getMessage();
        }

        // Log any issues for debugging
        if (!empty($errors)) {
            \Log::warning('Cache clear had some issues', ['errors' => $errors]);
        } else {
            \Log::info('Cache cleared successfully via admin panel');
        }

        $notify[] = ['success', translate('Cache cleared successfully')];
        return back()->withNotify($notify);
    }

    /**
     * @return \Illuminate\Http\RedirectResponse
     *
     */
    public function systemInfo() {

        Session::put("menu_active", true);
        $title = translate("System Information");

        $systemInfo = [
            'laravelversion' => app()->version(),
            'serverdetail'   => $_SERVER,
            'phpversion'     => phpversion(),
        ];
        return view('admin.system_info',compact('title','systemInfo'));
    }

    /**
     * Set automation mode
     * Prevents double execution when multiple automation methods are configured
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setAutomationMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:auto,cron_url,scheduler,supervisor'
        ]);

        try {
            AutomationService::setMode($request->input('mode'));

            \Log::info('Automation mode changed', [
                'mode' => $request->input('mode'),
                'changed_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => translate('Automation mode saved successfully'),
                'mode' => $request->input('mode')
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to set automation mode', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => translate('Failed to save automation mode: ') . $e->getMessage()
            ], 500);
        }
    }
}
