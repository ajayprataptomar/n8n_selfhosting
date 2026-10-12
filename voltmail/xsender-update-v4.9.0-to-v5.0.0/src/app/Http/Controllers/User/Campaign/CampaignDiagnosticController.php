<?php

namespace App\Http\Controllers\User\Campaign;

use App\Http\Controllers\Controller;
use App\Models\UnifiedCampaign;
use App\Services\Campaign\CampaignDiagnosticService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class CampaignDiagnosticController extends Controller
{
    public function __construct(private CampaignDiagnosticService $service) {}

    public function diagnose(int $id): JsonResponse
    {
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json($this->service->diagnose($campaign));
    }

    public function retryOrphans(int $id): RedirectResponse
    {
        $campaign = UnifiedCampaign::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $resetCount = $this->service->retryOrphans($campaign);

        return back()->with('success', translate("Reset {$resetCount} stuck dispatches to PENDING. They will be retried on next cron tick."));
    }
}
