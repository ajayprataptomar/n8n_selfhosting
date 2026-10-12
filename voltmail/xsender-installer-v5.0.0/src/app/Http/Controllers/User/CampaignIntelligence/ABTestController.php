<?php

namespace App\Http\Controllers\User\CampaignIntelligence;

use App\Http\Controllers\Controller;
use App\Models\UnifiedCampaign;
use App\Models\CampaignAbTest;
use App\Models\CampaignAbVariant;
use App\Models\CampaignMessage;
use App\Enums\Campaign\AbTestStatus;
use App\Enums\Campaign\AbTestWinningMetric;
use App\Services\CampaignIntelligence\ABTestingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class ABTestController extends Controller
{
    protected ABTestingService $abTestingService;

    public function __construct()
    {
        $this->abTestingService = new ABTestingService();
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

        return $planAccess['ai_intelligence']['ab_testing'] ?? false;
    }

    /**
     * Get user's A/B test limit from plan
     */
    protected function getMonthlyLimit(): int
    {
        $user = auth()->user();
        $planAccess = planAccess($user);

        return $planAccess['ai_intelligence']['ab_test_limit'] ?? 0;
    }

    /**
     * Check if user can create more A/B tests this month
     */
    protected function canCreateTest(): bool
    {
        $limit = $this->getMonthlyLimit();
        if ($limit === 0) {
            return true; // 0 = unlimited
        }

        $user = auth()->user();
        $thisMonthCount = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->whereMonth('created_at', now()->month)
          ->whereYear('created_at', now()->year)
          ->count();

        return $thisMonthCount < $limit;
    }

    /**
     * List all A/B tests for the user
     */
    public function index(Request $request)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('A/B Testing is not available in your current plan. Please upgrade.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        Session::put("menu_active", true);
        $title = translate("A/B Tests");
        $user = auth()->user();

        $query = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['campaign', 'variants']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('campaign', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $tests = $query->orderBy('created_at', 'desc')->paginate(15);

        // Get monthly usage stats
        $monthlyLimit = $this->getMonthlyLimit();
        $usedThisMonth = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->whereMonth('created_at', now()->month)
          ->whereYear('created_at', now()->year)
          ->count();
        $canCreate = $this->canCreateTest();

        return view('user.campaign_intelligence.ab_testing.index', compact('title', 'tests', 'monthlyLimit', 'usedThisMonth', 'canCreate'));
    }

    /**
     * Show create A/B test form
     */
    public function create(Request $request)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('A/B Testing is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        if (!$this->canCreateTest()) {
            $notify[] = ['error', translate('You have reached your monthly A/B test limit. Please upgrade your plan or wait until next month.')];
            return redirect()->route('user.campaign.intelligence.ab-test.index')->withNotify($notify);
        }

        Session::put("menu_active", true);
        $title = translate("Create A/B Test");
        $user = auth()->user();

        // Get eligible campaigns (draft or scheduled)
        $campaigns = UnifiedCampaign::where('user_id', $user->id)
            ->whereIn('status', ['draft', 'scheduled'])
            ->orderBy('created_at', 'desc')
            ->get();

        $campaign = null;
        if ($request->filled('campaign_id')) {
            $campaign = UnifiedCampaign::where('user_id', $user->id)
                ->where('id', $request->campaign_id)
                ->first();
        }

        $winningMetrics = AbTestWinningMetric::cases();

        return view('user.campaign_intelligence.ab_testing.create', compact('title', 'campaigns', 'campaign', 'winningMetrics'));
    }

    /**
     * Store a new A/B test
     */
    public function store(Request $request)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('A/B Testing is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        if (!$this->canCreateTest()) {
            $notify[] = ['error', translate('You have reached your monthly A/B test limit.')];
            return redirect()->route('user.campaign.intelligence.ab-test.index')->withNotify($notify);
        }

        $user = auth()->user();

        $validated = $request->validate([
            'campaign_id' => 'required|exists:unified_campaigns,id',
            'name' => 'required|string|max:255',
            'test_percentage' => 'required|integer|min:5|max:50',
            'test_duration_hours' => 'required|integer|min:1|max:168',
            'winning_metric' => 'required|string',
            'auto_select_winner' => 'nullable|boolean',
        ]);

        // Verify campaign belongs to user
        $campaign = UnifiedCampaign::where('user_id', $user->id)
            ->where('id', $validated['campaign_id'])
            ->firstOrFail();

        // Check if campaign already has an active test
        $existingTest = CampaignAbTest::where('campaign_id', $campaign->id)
            ->whereNotIn('status', ['completed', 'winner_selected'])
            ->first();

        if ($existingTest) {
            $notify[] = ['error', translate('This campaign already has an active A/B test.')];
            return back()->withNotify($notify)->withInput();
        }

        $test = CampaignAbTest::create([
            'uid' => Str::uuid(),
            'campaign_id' => $campaign->id,
            'name' => $validated['name'],
            'status' => AbTestStatus::DRAFT->value,
            'test_percentage' => $validated['test_percentage'],
            'test_duration_hours' => $validated['test_duration_hours'],
            'winning_metric' => $validated['winning_metric'],
            'auto_select_winner' => $request->boolean('auto_select_winner', true),
        ]);

        $notify[] = ['success', translate('A/B Test created. Now add at least 2 variants to start testing.')];
        return redirect()->route('user.campaign.intelligence.ab-test.edit', $test->id)->withNotify($notify);
    }

    /**
     * Show A/B test details and results
     */
    public function show($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('A/B Testing is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['campaign', 'variants.campaignMessage', 'winningVariant'])
          ->findOrFail($id);

        $title = translate("A/B Test Results") . " - " . $test->name;
        $evaluation = $this->abTestingService->evaluateTest($test);
        $summary = $this->abTestingService->getTestSummary($test);

        return view('user.campaign_intelligence.ab_testing.show', compact('title', 'test', 'evaluation', 'summary'));
    }

    /**
     * Edit A/B test (add variants)
     */
    public function edit($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('A/B Testing is not available in your current plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with(['campaign', 'variants.campaignMessage'])
          ->findOrFail($id);

        if ($test->status !== AbTestStatus::DRAFT->value) {
            $notify[] = ['error', translate('Only draft tests can be edited.')];
            return redirect()->route('user.campaign.intelligence.ab-test.show', $test->id)->withNotify($notify);
        }

        $title = translate("Edit A/B Test") . " - " . $test->name;
        $winningMetrics = AbTestWinningMetric::cases();

        // Get available messages for this campaign that aren't already variants
        $usedMessageIds = $test->variants->pluck('campaign_message_id')->toArray();
        $availableMessages = CampaignMessage::where('campaign_id', $test->campaign_id)
            ->whereNotIn('id', $usedMessageIds)
            ->get();

        return view('user.campaign_intelligence.ab_testing.edit', compact('title', 'test', 'winningMetrics', 'availableMessages'));
    }

    /**
     * Update A/B test configuration
     */
    public function update(Request $request, $id)
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Access denied')], 403);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        if ($test->status !== AbTestStatus::DRAFT->value) {
            $notify[] = ['error', translate('Only draft tests can be updated.')];
            return back()->withNotify($notify);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'test_percentage' => 'required|integer|min:5|max:50',
            'test_duration_hours' => 'required|integer|min:1|max:168',
            'winning_metric' => 'required|string',
            'auto_select_winner' => 'nullable|boolean',
        ]);

        $test->update([
            'name' => $validated['name'],
            'test_percentage' => $validated['test_percentage'],
            'test_duration_hours' => $validated['test_duration_hours'],
            'winning_metric' => $validated['winning_metric'],
            'auto_select_winner' => $request->boolean('auto_select_winner', true),
        ]);

        $notify[] = ['success', translate('A/B Test updated successfully.')];
        return back()->withNotify($notify);
    }

    /**
     * Add a variant to the test
     */
    public function addVariant(Request $request, $id)
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Access denied')], 403);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        if ($test->status !== AbTestStatus::DRAFT->value) {
            return response()->json(['error' => translate('Cannot add variants to a running test')], 400);
        }

        $validated = $request->validate([
            'campaign_message_id' => 'required|exists:campaign_messages,id',
            'variant_label' => 'nullable|string|max:1',
        ]);

        // Verify message belongs to the test's campaign
        $message = CampaignMessage::where('campaign_id', $test->campaign_id)
            ->where('id', $validated['campaign_message_id'])
            ->firstOrFail();

        // Check if message is already a variant
        $existingVariant = CampaignAbVariant::where('ab_test_id', $test->id)
            ->where('campaign_message_id', $message->id)
            ->first();

        if ($existingVariant) {
            return response()->json(['error' => translate('This message is already a variant')], 400);
        }

        // Auto-generate label if not provided
        $label = $validated['variant_label'] ?? chr(65 + $test->variants->count());

        $variant = CampaignAbVariant::create([
            'ab_test_id' => $test->id,
            'campaign_message_id' => $message->id,
            'variant_label' => strtoupper($label),
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('Variant added successfully'),
            'variant' => $variant->load('campaignMessage')
        ]);
    }

    /**
     * Remove a variant from the test
     */
    public function removeVariant($testId, $variantId)
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Access denied')], 403);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($testId);

        if ($test->status !== AbTestStatus::DRAFT->value) {
            return response()->json(['error' => translate('Cannot remove variants from a running test')], 400);
        }

        $variant = CampaignAbVariant::where('ab_test_id', $test->id)
            ->where('id', $variantId)
            ->firstOrFail();

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Variant removed successfully')
        ]);
    }

    /**
     * Start the A/B test
     */
    public function start($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Access denied')];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->with('variants')->findOrFail($id);

        if ($test->variants->count() < 2) {
            $notify[] = ['error', translate('You need at least 2 variants to start an A/B test.')];
            return back()->withNotify($notify);
        }

        if ($test->status !== AbTestStatus::DRAFT->value) {
            $notify[] = ['error', translate('This test cannot be started.')];
            return back()->withNotify($notify);
        }

        $this->abTestingService->startTest($test);

        $notify[] = ['success', translate('A/B Test started successfully!')];
        return redirect()->route('user.campaign.intelligence.ab-test.show', $test->id)->withNotify($notify);
    }

    /**
     * Pause the A/B test
     */
    public function pause($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Access denied')];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        if ($test->status !== AbTestStatus::RUNNING->value) {
            $notify[] = ['error', translate('Only running tests can be paused.')];
            return back()->withNotify($notify);
        }

        $test->update(['status' => AbTestStatus::PAUSED->value]);

        $notify[] = ['success', translate('A/B Test paused.')];
        return back()->withNotify($notify);
    }

    /**
     * Resume a paused test
     */
    public function resume($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Access denied')];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        if ($test->status !== AbTestStatus::PAUSED->value) {
            $notify[] = ['error', translate('Only paused tests can be resumed.')];
            return back()->withNotify($notify);
        }

        $test->update(['status' => AbTestStatus::RUNNING->value]);

        $notify[] = ['success', translate('A/B Test resumed.')];
        return back()->withNotify($notify);
    }

    /**
     * Manually select a winner
     */
    public function selectWinner(Request $request, $id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Access denied')];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'variant_id' => 'required|exists:campaign_ab_variants,id',
        ]);

        $variant = CampaignAbVariant::where('ab_test_id', $test->id)
            ->where('id', $validated['variant_id'])
            ->firstOrFail();

        $this->abTestingService->selectWinner($test, $variant);

        $notify[] = ['success', translate('Winner selected successfully!')];
        return redirect()->route('user.campaign.intelligence.ab-test.show', $test->id)->withNotify($notify);
    }

    /**
     * Delete an A/B test
     */
    public function destroy($id)
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Access denied')];
            return back()->withNotify($notify);
        }

        $user = auth()->user();
        $test = CampaignAbTest::whereHas('campaign', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        })->findOrFail($id);

        if ($test->status === AbTestStatus::RUNNING->value) {
            $notify[] = ['error', translate('Cannot delete a running test. Please pause it first.')];
            return back()->withNotify($notify);
        }

        $test->variants()->delete();
        $test->delete();

        $notify[] = ['success', translate('A/B Test deleted successfully.')];
        return redirect()->route('user.campaign.intelligence.ab-test.index')->withNotify($notify);
    }
}
