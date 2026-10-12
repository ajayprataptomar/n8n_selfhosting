<?php

namespace App\Http\Controllers\Admin\CampaignIntelligence;

use App\Http\Controllers\Controller;
use App\Models\CampaignAbTest;
use App\Models\CampaignAbVariant;
use App\Models\CampaignMessage;
use App\Models\UnifiedCampaign;
use App\Services\CampaignIntelligence\ABTestingService;
use App\Enums\Campaign\AbTestStatus;
use App\Enums\Campaign\AbTestWinningMetric;
use Illuminate\Http\Request;

class ABTestController extends Controller
{
    protected ABTestingService $abTestingService;

    public function __construct(ABTestingService $abTestingService)
    {
        $this->abTestingService = $abTestingService;
    }

    /**
     * Display a listing of A/B tests
     */
    public function index(Request $request)
    {
        $title = translate('A/B Tests');

        $tests = CampaignAbTest::with(['campaign', 'variants', 'winningVariant'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('campaign', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->latest()
            ->paginate(paginateNumber());

        return view('admin.campaign_intelligence.ab_testing.index', compact('title', 'tests'));
    }

    /**
     * Show the form for creating a new A/B test
     */
    public function create(Request $request)
    {
        $title = translate('Create A/B Test');

        $campaign = null;
        if ($request->campaign_id) {
            $campaign = UnifiedCampaign::findOrFail($request->campaign_id);
        }

        $campaigns = UnifiedCampaign::where('status', 'draft')
            ->orWhere('status', 'scheduled')
            ->latest()
            ->get();

        $winningMetrics = AbTestWinningMetric::cases();

        return view('admin.campaign_intelligence.ab_testing.create', compact(
            'title',
            'campaign',
            'campaigns',
            'winningMetrics'
        ));
    }

    /**
     * Store a newly created A/B test
     */
    public function store(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:unified_campaigns,id',
            'name' => 'required|string|max:255',
            'test_percentage' => 'required|integer|min:5|max:50',
            'winning_metric' => 'required|in:delivered,opened,clicked,replied',
            'test_duration_hours' => 'required|integer|min:1|max:168',
            'auto_select_winner' => 'boolean',
        ]);

        $campaign = UnifiedCampaign::findOrFail($request->campaign_id);

        $test = $this->abTestingService->createTest($campaign, [
            'name' => $request->name,
            'test_percentage' => $request->test_percentage,
            'winning_metric' => $request->winning_metric,
            'test_duration_hours' => $request->test_duration_hours,
            'auto_select_winner' => $request->boolean('auto_select_winner'),
        ]);

        $notify[] = ['success', translate('A/B test created successfully')];
        return redirect()->route('admin.campaign.intelligence.ab-test.edit', $test->id)
            ->withNotify($notify);
    }

    /**
     * Display the specified A/B test
     */
    public function show($id)
    {
        $test = CampaignAbTest::with(['campaign', 'variants.campaignMessage', 'winningVariant'])
            ->findOrFail($id);

        $title = translate('A/B Test') . ': ' . $test->name;
        $evaluation = $this->abTestingService->evaluateTest($test);
        $summary = $this->abTestingService->getTestSummary($test);

        return view('admin.campaign_intelligence.ab_testing.show', compact(
            'title',
            'test',
            'evaluation',
            'summary'
        ));
    }

    /**
     * Show the form for editing the A/B test
     */
    public function edit($id)
    {
        $test = CampaignAbTest::with(['campaign.messages', 'variants.campaignMessage'])
            ->findOrFail($id);

        $title = translate('Edit A/B Test') . ': ' . $test->name;
        $winningMetrics = AbTestWinningMetric::cases();

        // Get available messages for adding variants
        $availableMessages = $test->campaign->messages ?? collect();

        return view('admin.campaign_intelligence.ab_testing.edit', compact(
            'title',
            'test',
            'winningMetrics',
            'availableMessages'
        ));
    }

    /**
     * Update the specified A/B test
     */
    public function update(Request $request, $id)
    {
        $test = CampaignAbTest::findOrFail($id);

        if ($test->isRunning()) {
            $notify[] = ['error', translate('Cannot update a running test')];
            return back()->withNotify($notify);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'test_percentage' => 'required|integer|min:5|max:50',
            'winning_metric' => 'required|in:delivered,opened,clicked,replied',
            'test_duration_hours' => 'required|integer|min:1|max:168',
            'auto_select_winner' => 'boolean',
        ]);

        $test->update([
            'name' => $request->name,
            'test_percentage' => $request->test_percentage,
            'winning_metric' => $request->winning_metric,
            'test_duration_hours' => $request->test_duration_hours,
            'auto_select_winner' => $request->boolean('auto_select_winner'),
        ]);

        $notify[] = ['success', translate('A/B test updated successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Add a variant to the test
     */
    public function addVariant(Request $request, $id)
    {
        $test = CampaignAbTest::findOrFail($id);

        if ($test->isRunning()) {
            return response()->json(['error' => translate('Cannot add variants to a running test')], 400);
        }

        $request->validate([
            'campaign_message_id' => 'required|exists:campaign_messages,id',
            'variant_label' => 'nullable|string|max:1',
        ]);

        $message = CampaignMessage::findOrFail($request->campaign_message_id);
        $variant = $this->abTestingService->addVariant($test, $message, $request->variant_label);

        return response()->json([
            'success' => true,
            'variant' => $variant,
            'message' => translate('Variant added successfully'),
        ]);
    }

    /**
     * Remove a variant from the test
     */
    public function removeVariant($id, $variantId)
    {
        $test = CampaignAbTest::findOrFail($id);
        $variant = CampaignAbVariant::where('ab_test_id', $id)->findOrFail($variantId);

        if ($test->isRunning()) {
            return response()->json(['error' => translate('Cannot remove variants from a running test')], 400);
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => translate('Variant removed successfully'),
        ]);
    }

    /**
     * Start the A/B test
     */
    public function start($id)
    {
        $test = CampaignAbTest::findOrFail($id);

        if (!$this->abTestingService->startTest($test)) {
            $notify[] = ['error', translate('A/B test requires at least 2 variants')];
            return back()->withNotify($notify);
        }

        $notify[] = ['success', translate('A/B test started successfully')];
        return redirect()->route('admin.campaign.intelligence.ab-test.show', $id)
            ->withNotify($notify);
    }

    /**
     * Pause the A/B test
     */
    public function pause($id)
    {
        $test = CampaignAbTest::findOrFail($id);
        $this->abTestingService->pauseTest($test);

        $notify[] = ['success', translate('A/B test paused')];
        return back()->withNotify($notify);
    }

    /**
     * Resume the A/B test
     */
    public function resume($id)
    {
        $test = CampaignAbTest::findOrFail($id);
        $this->abTestingService->resumeTest($test);

        $notify[] = ['success', translate('A/B test resumed')];
        return back()->withNotify($notify);
    }

    /**
     * Manually select a winner
     */
    public function selectWinner(Request $request, $id)
    {
        $test = CampaignAbTest::findOrFail($id);

        $request->validate([
            'variant_id' => 'required|exists:campaign_ab_variants,id',
        ]);

        $variant = CampaignAbVariant::where('ab_test_id', $id)
            ->findOrFail($request->variant_id);

        $this->abTestingService->selectWinner($test, $variant);

        $notify[] = ['success', translate('Winner selected successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Apply winner to main campaign
     */
    public function applyWinner($id)
    {
        $test = CampaignAbTest::findOrFail($id);

        if (!$test->hasWinner()) {
            $notify[] = ['error', translate('No winner has been selected')];
            return back()->withNotify($notify);
        }

        if ($this->abTestingService->applyWinnerToMainCampaign($test)) {
            $notify[] = ['success', translate('Winner applied to campaign successfully')];
        } else {
            $notify[] = ['error', translate('Failed to apply winner to campaign')];
        }

        return back()->withNotify($notify);
    }

    /**
     * Delete the A/B test
     */
    public function destroy($id)
    {
        $test = CampaignAbTest::findOrFail($id);

        if ($test->isRunning()) {
            $notify[] = ['error', translate('Cannot delete a running test')];
            return back()->withNotify($notify);
        }

        $test->variants()->delete();
        $test->delete();

        $notify[] = ['success', translate('A/B test deleted successfully')];
        return redirect()->route('admin.campaign.intelligence.ab-test.index')
            ->withNotify($notify);
    }

    /**
     * Get test statistics via AJAX
     */
    public function getStats($id)
    {
        $test = CampaignAbTest::with('variants')->findOrFail($id);
        $evaluation = $this->abTestingService->evaluateTest($test);
        $summary = $this->abTestingService->getTestSummary($test);

        return response()->json([
            'evaluation' => $evaluation,
            'summary' => $summary,
        ]);
    }
}
