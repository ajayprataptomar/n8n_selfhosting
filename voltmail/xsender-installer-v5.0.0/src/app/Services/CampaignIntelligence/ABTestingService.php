<?php

namespace App\Services\CampaignIntelligence;

use App\Models\CampaignAbTest;
use App\Models\CampaignAbVariant;
use App\Models\CampaignMessage;
use App\Models\UnifiedCampaign;
use Illuminate\Support\Collection;

class ABTestingService
{
    /**
     * Create a new A/B test for a campaign
     */
    public function createTest(UnifiedCampaign $campaign, array $data): CampaignAbTest
    {
        $test = CampaignAbTest::create([
            'campaign_id' => $campaign->id,
            'name' => $data['name'] ?? $campaign->name . ' - A/B Test',
            'status' => 'draft',
            'test_percentage' => $data['test_percentage'] ?? 20,
            'winning_metric' => $data['winning_metric'] ?? 'delivered',
            'confidence_level' => $data['confidence_level'] ?? 0.95,
            'auto_select_winner' => $data['auto_select_winner'] ?? true,
            'test_duration_hours' => $data['test_duration_hours'] ?? 24,
        ]);

        return $test;
    }

    /**
     * Add a variant to an A/B test
     */
    public function addVariant(CampaignAbTest $test, CampaignMessage $message, string $label = null): CampaignAbVariant
    {
        // Auto-generate label if not provided (A, B, C, D...)
        if (!$label) {
            $existingCount = $test->variants()->count();
            $label = chr(65 + $existingCount); // A=65 in ASCII
        }

        return CampaignAbVariant::create([
            'ab_test_id' => $test->id,
            'variant_label' => $label,
            'campaign_message_id' => $message->id,
        ]);
    }

    /**
     * Start an A/B test
     */
    public function startTest(CampaignAbTest $test): bool
    {
        if ($test->variants()->count() < 2) {
            return false; // Need at least 2 variants
        }

        $test->start();
        return true;
    }

    /**
     * Pause an A/B test
     */
    public function pauseTest(CampaignAbTest $test): void
    {
        $test->pause();
    }

    /**
     * Resume an A/B test
     */
    public function resumeTest(CampaignAbTest $test): void
    {
        $test->update(['status' => 'running']);
    }

    /**
     * Evaluate A/B test and determine winner
     */
    public function evaluateTest(CampaignAbTest $test): array
    {
        $variants = $test->variants()->get();
        $results = [];

        foreach ($variants as $variant) {
            $results[$variant->variant_label] = [
                'variant' => $variant,
                'contact_count' => $variant->contact_count,
                'sent_count' => $variant->sent_count ?? 0,
                'delivered_count' => $variant->delivered_count,
                'opened_count' => $variant->opened_count,
                'clicked_count' => $variant->clicked_count,
                'replied_count' => $variant->replied_count,
                'delivery_rate' => $variant->getDeliveryRate(),
                'open_rate' => $variant->getOpenRate(),
                'click_rate' => $variant->getClickRate(),
                'reply_rate' => $variant->getReplyRate(),
                'score' => $variant->getMetricValue($test->winning_metric),
            ];
        }

        // Sort by score
        uasort($results, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'variants' => $results,
            'has_winner' => $test->hasWinner(),
            'winning_variant' => $test->winningVariant,
            'can_select_winner' => $this->canSelectWinner($test),
        ];
    }

    /**
     * Check if winner can be selected
     */
    public function canSelectWinner(CampaignAbTest $test): bool
    {
        // Check if test has been running long enough
        if (!$test->isRunning()) {
            return false;
        }

        // Check if minimum contacts have been processed
        $totalSent = $test->variants()->sum('sent_count');
        if ($totalSent < 50) {
            return false;
        }

        // Check for statistical significance
        $variants = $test->variants;
        if ($variants->count() < 2) {
            return false;
        }

        $rates = $variants->map(fn($v) => $v->getMetricValue($test->winning_metric))->sort()->values();
        $difference = $rates->last() - $rates->first();

        return $difference >= 3; // At least 3% difference
    }

    /**
     * Manually select a winner
     */
    public function selectWinner(CampaignAbTest $test, CampaignAbVariant $winner): void
    {
        // Reset any previous winner
        $test->variants()->update(['is_winner' => false]);

        $winner->update(['is_winner' => true]);
        $test->update([
            'winning_variant_id' => $winner->id,
            'winner_selected_at' => now(),
            'status' => 'winner_selected',
        ]);
    }

    /**
     * Auto-select winner based on metrics
     */
    public function autoSelectWinner(CampaignAbTest $test): ?CampaignAbVariant
    {
        return $test->evaluateAndSelectWinner();
    }

    /**
     * Get contacts for a specific variant
     */
    public function getVariantContacts(CampaignAbTest $test, CampaignAbVariant $variant): int
    {
        $totalTestContacts = $test->getTestContactsCount();
        $variantCount = $test->variants()->count();

        return (int) floor($totalTestContacts / $variantCount);
    }

    /**
     * Distribute contacts across variants
     */
    public function distributeContacts(CampaignAbTest $test, Collection $contacts): array
    {
        $variants = $test->variants()->orderBy('variant_label')->get();
        $variantCount = $variants->count();

        if ($variantCount === 0) {
            return [];
        }

        $distribution = [];
        $contactsArray = $contacts->values()->all();
        $perVariant = (int) ceil(count($contactsArray) / $variantCount);

        foreach ($variants as $index => $variant) {
            $start = $index * $perVariant;
            $variantContacts = array_slice($contactsArray, $start, $perVariant);

            $distribution[$variant->id] = [
                'variant' => $variant,
                'contacts' => $variantContacts,
                'count' => count($variantContacts),
            ];

            // Update variant contact count
            $variant->update(['contact_count' => count($variantContacts)]);
        }

        return $distribution;
    }

    /**
     * Get all running tests that need evaluation
     */
    public function getTestsNeedingEvaluation(): Collection
    {
        return CampaignAbTest::readyForEvaluation()->get();
    }

    /**
     * Process auto-winner selection for all eligible tests
     */
    public function processAutoWinnerSelection(): int
    {
        $tests = $this->getTestsNeedingEvaluation();
        $processed = 0;

        foreach ($tests as $test) {
            if ($this->canSelectWinner($test)) {
                $this->autoSelectWinner($test);
                $processed++;
            }
        }

        return $processed;
    }

    /**
     * Get A/B test summary statistics
     */
    public function getTestSummary(CampaignAbTest $test): array
    {
        $variants = $test->variants;

        return [
            'test' => $test,
            'total_contacts' => $variants->sum('contact_count'),
            'total_sent' => $variants->sum('sent_count'),
            'total_delivered' => $variants->sum('delivered_count'),
            'total_opened' => $variants->sum('opened_count'),
            'total_clicked' => $variants->sum('clicked_count'),
            'total_replied' => $variants->sum('replied_count'),
            'avg_delivery_rate' => $variants->avg(fn($v) => $v->getDeliveryRate()),
            'avg_open_rate' => $variants->avg(fn($v) => $v->getOpenRate()),
            'avg_click_rate' => $variants->avg(fn($v) => $v->getClickRate()),
            'variant_count' => $variants->count(),
            'has_winner' => $test->hasWinner(),
            'time_remaining' => $test->isRunning()
                ? max(0, $test->test_duration_hours - $test->created_at->diffInHours(now()))
                : 0,
        ];
    }

    /**
     * Clone winning variant message to be the main campaign message
     */
    public function applyWinnerToMainCampaign(CampaignAbTest $test): bool
    {
        $winner = $test->winningVariant;

        if (!$winner || !$winner->campaignMessage) {
            return false;
        }

        $winnerMessage = $winner->campaignMessage;
        $campaign = $test->campaign;

        // Find the original message for this channel and update it
        $originalMessage = $campaign->getMessageForChannel($winnerMessage->channel->value);

        if ($originalMessage) {
            $originalMessage->update([
                'subject' => $winnerMessage->subject,
                'content' => $winnerMessage->content,
                'template_id' => $winnerMessage->template_id,
            ]);
        }

        return true;
    }
}
