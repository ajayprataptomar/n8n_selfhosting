<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Services\System\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * v4.9 — Admin System Health page + JSON API.
 *
 * Renders a full-system diagnostic (composed by SystemHealthService — which
 * delegates to existing services, no new probe logic) using the same UI
 * design tokens as partials/campaign/health_banner.blade.php.
 */
class SystemHealthController extends Controller
{
    public function __construct(private SystemHealthService $service) {}

    /**
     * Render the admin System Health page (blade).
     */
    public function index(): View
    {
        Session::put('menu_active', true);
        return view('admin.setting.system_health', [
            'title'          => translate('System Health'),
            // Route name includes the outer group's `system.` prefix — full name
            // resolves to `admin.system.setting.system.health.data`.
            'diagnosticUrl'  => route('admin.system.setting.system.health.data'),
        ]);
    }

    /**
     * JSON diagnostic feed. Consumed by the page above AND by external
     * monitoring (UptimeRobot, StatusCake, etc.) via the public status
     * endpoint at /status.
     */
    public function data(): JsonResponse
    {
        return response()->json($this->service->diagnose());
    }
}
