<?php

namespace App\Http\Controllers\User\CampaignIntelligence;

use App\Http\Controllers\Controller;
use App\Models\UnifiedCampaign;
use App\Models\CampaignInsight;
use App\Models\ContactEngagement;
use App\Services\CampaignIntelligence\InsightsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class InsightsController extends Controller
{
    protected InsightsService $insightsService;

    public function __construct()
    {
        $this->insightsService = new InsightsService();
    }

    /**
     * Check if user has access to AI Intelligence features
     */
    protected function checkAccess(): bool
    {
        $user = auth()->user();
        $planAccess = planAccess($user);

        if (!isset($planAccess['ai_intelligence']) || !($planAccess['ai_intelligence']['is_allowed'] ?? false)) {
            return false;
        }

        return true;
    }

    /**
     * Check specific feature access
     */
    protected function hasFeatureAccess(string $feature): bool
    {
        $user = auth()->user();
        $planAccess = planAccess($user);

        if (!isset($planAccess['ai_intelligence']) || !($planAccess['ai_intelligence']['is_allowed'] ?? false)) {
            return false;
        }

        return $planAccess['ai_intelligence'][$feature] ?? false;
    }

    /**
     * Campaign Intelligence Dashboard
     */
    public function index()
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Your plan does not include AI Campaign Intelligence. Please upgrade your plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        if (!$this->hasFeatureAccess('campaign_insights')) {
            $notify[] = ['error', translate('Campaign Insights is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        Session::put("menu_active", true);
        $title = translate("Campaign Intelligence");
        $user = auth()->user();

        // Get recent campaigns with insights
        $campaigns = UnifiedCampaign::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'running'])
            ->with('insight')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Backfill missing CampaignInsight rows for this user's completed
        // campaigns. Without this, a member who never clicked into a specific
        // campaign's insight page would see Avg Open Rate = 0.0% forever,
        // because the dashboard averages `campaign_insights.open_rate` and
        // insights are only generated lazily on show(). Admin has the same
        // pattern but reads directly from ContactEngagement, so admin doesn't
        // hit this. Generation is cheap: pure aggregation over CampaignDispatch.
        try {
            UnifiedCampaign::where('user_id', $user->id)
                ->where('status', 'completed')
                ->whereDoesntHave('insight')
                ->limit(50)
                ->get()
                ->each(function ($campaign) {
                    try { $this->insightsService->generateInsights($campaign); }
                    catch (\Throwable $e) { /* skip malformed campaign */ }
                });
        } catch (\Throwable $e) { /* schema / relation missing on old install */ }

        // Get top performing campaigns
        $topCampaigns = UnifiedCampaign::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereHas('insight')
            ->with('insight')
            ->get()
            ->sortByDesc(function ($campaign) {
                return $campaign->insight->delivery_rate ?? 0;
            })
            ->take(5);

        // Get overall stats
        $stats = [
            'total_campaigns' => UnifiedCampaign::where('user_id', $user->id)->count(),
            'completed_campaigns' => UnifiedCampaign::where('user_id', $user->id)->where('status', 'completed')->count(),
            'avg_delivery_rate' => CampaignInsight::whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->avg('delivery_rate') ?? 0,
            'avg_open_rate' => CampaignInsight::whereHas('campaign', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })->avg('open_rate') ?? 0,
        ];

        return view('user.campaign_intelligence.insights.index', compact('title', 'campaigns', 'topCampaigns', 'stats'));
    }

    /**
     * Show detailed insights for a specific campaign
     */
    public function show($id)
    {
        if (!$this->checkAccess() || !$this->hasFeatureAccess('campaign_insights')) {
            $notify[] = ['error', translate('Campaign Insights is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        $user = auth()->user();
        $campaign = UnifiedCampaign::where('user_id', $user->id)->findOrFail($id);

        // Always regenerate — a cached CampaignInsight row from before the
        // cumulative-stats fix would show permanent 0% Delivery / 0% Open.
        // Regenerating on every view keeps the numbers live and honest and
        // matches the admin controller's behavior.
        $insight = $this->insightsService->generateInsights($campaign);

        $title = translate("Campaign Insights") . " - " . $campaign->name;
        $realTimeStats = $this->insightsService->getRealTimeStats($campaign);

        return view('user.campaign_intelligence.insights.show', compact('title', 'campaign', 'insight', 'realTimeStats'));
    }

    /**
     * Send Time Optimization page
     */
    public function sendTime(Request $request)
    {
        if (!$this->checkAccess() || !$this->hasFeatureAccess('send_time_optimizer')) {
            $notify[] = ['error', translate('Send Time Optimizer is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        Session::put("menu_active", true);
        $title = translate("Send Time Optimizer");
        $user = auth()->user();
        $channel = $request->get('channel', 'email');

        // Defensive: each data source wrapped so a single bad row can't 500 the page.
        $engagementStats = collect();
        try {
            $engagementStats = ContactEngagement::where('user_id', $user->id)
                ->selectRaw('channel, COUNT(*) as total_contacts, AVG(engagement_score) as avg_score, SUM(total_sent) as total_sent, SUM(total_opened) as total_opened')
                ->groupBy('channel')
                ->get()
                // channel is cast to CampaignChannel enum; keyBy('channel') would stringify it.
                ->keyBy(fn ($r) => is_object($r->channel) ? $r->channel->value : (string) $r->channel);
        } catch (\Throwable $e) {
            \Log::warning('User SendTime: engagementStats query failed', ['error' => $e->getMessage()]);
        }

        $optimalHours = array_fill(0, 24, 0);
        try {
            $raw = $this->insightsService->getOptimalSendHours($user->id, $channel);
            if (is_array($raw) && count($raw) === 24) {
                $optimalHours = $raw;
            }
        } catch (\Throwable $e) {
            \Log::warning('User SendTime: getOptimalSendHours failed', ['error' => $e->getMessage()]);
        }

        $optimalDays = array_fill(0, 7, 0);
        try {
            $raw = $this->insightsService->getOptimalSendDays($user->id, $channel);
            if (is_array($raw) && count($raw) === 7) {
                $optimalDays = $raw;
            }
        } catch (\Throwable $e) {
            \Log::warning('User SendTime: getOptimalSendDays failed', ['error' => $e->getMessage()]);
        }

        return view('user.campaign_intelligence.insights.send_time', compact('title', 'channel', 'engagementStats', 'optimalHours', 'optimalDays'));
    }

    /**
     * Compare campaigns
     */
    public function compare(Request $request)
    {
        if (!$this->checkAccess() || !$this->hasFeatureAccess('campaign_insights')) {
            $notify[] = ['error', translate('Campaign Insights is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        Session::put("menu_active", true);
        $title = translate("Compare Campaigns");
        $user = auth()->user();

        $availableCampaigns = UnifiedCampaign::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'running'])
            ->orderBy('created_at', 'desc')
            ->get();

        $campaignIds = $request->get('campaigns', []);
        $comparison = [];

        if (!empty($campaignIds)) {
            $campaigns = UnifiedCampaign::where('user_id', $user->id)
                ->whereIn('id', $campaignIds)
                ->with('insight')
                ->get();

            foreach ($campaigns as $campaign) {
                $comparison[$campaign->id] = $this->insightsService->getCampaignComparisonData($campaign);
            }
        }

        return view('user.campaign_intelligence.insights.compare', compact('title', 'availableCampaigns', 'comparison', 'campaignIds'));
    }

    /**
     * Refresh insights for a campaign
     */
    public function refresh($id)
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Access denied')], 403);
        }

        $user = auth()->user();
        $campaign = UnifiedCampaign::where('user_id', $user->id)->findOrFail($id);

        $insight = $this->insightsService->generateInsights($campaign);

        return response()->json([
            'success' => true,
            'message' => translate('Insights refreshed successfully'),
            'insight' => $insight
        ]);
    }
}
