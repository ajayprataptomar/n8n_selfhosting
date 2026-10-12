<?php

namespace App\Services\System\Communication;

use App\Enums\StatusEnum;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use OpenAI;

/**
 * v5.0-S5 — AI Reply Copilot for the Unified Inbox.
 *
 * Given a conversation, returns 3 short suggested replies the agent can
 * click to auto-fill the reply box. Reuses the app's existing multi-provider
 * AI wiring (site_settings('ai_models') → OpenAI / DeepSeek / Groq /
 * Together AI via custom base_url) — same pattern as
 * App\Http\Controllers\Admin\AiContentController::generateText, so any
 * provider the admin already configured for AI Text Generation works here
 * with zero extra setup.
 *
 * Each suggestion is intentionally distinct in tone:
 *   1. Formal / professional
 *   2. Friendly / conversational
 *   3. Concise / one-liner
 *
 * Returns an array of {label, text}. Errors are returned as
 * ['ok' => false, 'reason' => …] so the controller can surface them without
 * throwing at the browser.
 */
class InboxAiCopilotService
{
    /**
     * Number of most-recent messages to feed as context. Small enough to
     * fit any provider's token limit, large enough to include the customer's
     * latest question + agent's tone from the last few turns.
     */
    private const CONTEXT_MESSAGE_LIMIT = 12;

    /**
     * Generate 3 suggested replies for a conversation.
     *
     * @return array {
     *   ok: bool,
     *   reason?: string,       // populated on failure
     *   suggestions?: array<array{label:string,text:string}>
     * }
     */
    public function suggest(Conversation $conversation, ?User $currentUser = null): array
    {
        $aiConfig = $this->resolveAiConfig();
        if (!$aiConfig['enabled']) {
            return ['ok' => false, 'reason' => translate('AI Reply Copilot is not enabled. Admin can enable it in Settings → AI.')];
        }

        $context = $this->buildContext($conversation);
        if ($context['message_count'] === 0) {
            return ['ok' => false, 'reason' => translate('This conversation has no messages yet — send a first message to give the copilot something to reply to.')];
        }

        try {
            $client = $this->openAiClient($aiConfig);
            $prompt = $this->buildPrompt($conversation, $context, $currentUser);

            $result = $client->chat()->create([
                'model' => $aiConfig['model'] ?: 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemInstructions()],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 400,
            ]);

            $raw = (string) Arr::get($result, 'choices.0.message.content', '');
            $suggestions = $this->parseSuggestions($raw);

            if (count($suggestions) < 1) {
                return ['ok' => false, 'reason' => translate('The AI returned an empty response. Try again.')];
            }

            return ['ok' => true, 'suggestions' => $suggestions];

        } catch (\Throwable $e) {
            Log::warning('InboxAiCopilotService failure', [
                'conversation_id' => $conversation->id,
                'error'           => $e->getMessage(),
            ]);
            return ['ok' => false, 'reason' => translate('AI request failed: ') . $e->getMessage()];
        }
    }

    // ── internal ─────────────────────────────────────────────────────────

    /**
     * Read the customer's AI configuration from site_settings. Same schema
     * as AiContentController::generateText — one source of truth.
     */
    protected function resolveAiConfig(): array
    {
        $aiModels = json_decode((string) site_settings('ai_models'), true) ?: [];
        return [
            'enabled'  => Arr::get($aiModels, 'open_ai_text.status', StatusEnum::FALSE->status()) != StatusEnum::FALSE->status(),
            'key'      => (string) Arr::get($aiModels, 'open_ai_text.key', ''),
            'model'    => (string) Arr::get($aiModels, 'open_ai_text.model', 'gpt-4o-mini'),
            'base_url' => (string) Arr::get($aiModels, 'open_ai_text.base_url', ''),
        ];
    }

    /**
     * Build an OpenAI client, supporting custom base URLs for DeepSeek,
     * Groq, Together AI, and other OpenAI-compatible providers.
     */
    protected function openAiClient(array $cfg)
    {
        if ($cfg['base_url'] !== '') {
            return OpenAI::factory()
                ->withApiKey($cfg['key'])
                ->withBaseUri($cfg['base_url'])
                ->make();
        }
        return OpenAI::client($cfg['key']);
    }

    /**
     * Distill the recent thread into a compact context payload.
     */
    protected function buildContext(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->with('participants.participantable')
            ->orderByDesc('created_at')
            ->limit(self::CONTEXT_MESSAGE_LIMIT)
            ->get()
            ->reverse()
            ->values();

        $turns = [];
        foreach ($messages as $m) {
            $isAgent = false;
            foreach (($m->participants ?? []) as $p) {
                if (($p->role ?? '') === 'sender'
                    && str_contains((string) ($p->participantable_type ?? ''), 'User')) {
                    $isAgent = true;
                    break;
                }
            }
            $turns[] = [
                'from' => $isAgent ? 'agent' : 'customer',
                'body' => trim((string) $m->message),
            ];
        }

        return [
            'message_count' => $messages->count(),
            'turns'         => $turns,
        ];
    }

    protected function systemInstructions(): string
    {
        $siteName = (string) (site_settings('site_name') ?: 'the business');
        return "You are the customer support assistant for {$siteName}. "
            . "Produce exactly 3 reply suggestions to the customer's latest message, distinct in tone: "
            . "(1) formal and professional, (2) friendly and conversational, (3) concise one-liner. "
            . "Keep every suggestion under 40 words. Never include placeholder text like [NAME] — "
            . "if you don't know a name, address the customer neutrally. "
            . 'Respond ONLY with valid JSON of the exact shape: '
            . '{"suggestions":[{"label":"Formal","text":"..."},{"label":"Friendly","text":"..."},{"label":"Concise","text":"..."}]}. '
            . "No prose, no explanations, no markdown fences.";
    }

    protected function buildPrompt(Conversation $conversation, array $context, ?User $agent): string
    {
        $channel = (string) $conversation->channel;
        $contactName = trim(
            ($conversation->contact->first_name ?? '') . ' '
            . ($conversation->contact->last_name ?? '')
        );
        if ($contactName === '') $contactName = translate('customer');

        $header = "Channel: {$channel}\n"
                . "Customer: {$contactName}\n"
                . ($agent ? "Replying as: " . trim(($agent->name ?? '') ?: ($agent->username ?? '')) . "\n" : '')
                . "\nConversation (oldest first):\n";

        $lines = [];
        foreach ($context['turns'] as $t) {
            $who = $t['from'] === 'agent' ? 'AGENT' : 'CUSTOMER';
            $lines[] = "[{$who}] " . $t['body'];
        }
        return $header . implode("\n", $lines) . "\n\nReply now.";
    }

    /**
     * Parse the AI response into a canonical array. Handles the ideal case
     * (well-formed JSON) plus common failure modes (fenced code blocks,
     * plain-text lines with numeric prefixes).
     */
    protected function parseSuggestions(string $raw): array
    {
        $raw = trim($raw);
        // Strip common ```json fences
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw) ?? $raw;

        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['suggestions']) && is_array($decoded['suggestions'])) {
            $out = [];
            foreach ($decoded['suggestions'] as $s) {
                $label = trim((string) ($s['label'] ?? ''));
                $text  = trim((string) ($s['text']  ?? ''));
                if ($text !== '') {
                    $out[] = ['label' => $label ?: translate('Suggestion'), 'text' => $text];
                }
            }
            if (!empty($out)) return array_slice($out, 0, 3);
        }

        // Fallback — split by newline and strip "1." / "-" prefixes
        $lines = array_values(array_filter(array_map('trim', preg_split("/\r\n|\n|\r/", $raw))));
        $labels = [translate('Formal'), translate('Friendly'), translate('Concise')];
        $out = [];
        foreach ($lines as $i => $line) {
            if ($i >= 3) break;
            $line = preg_replace('/^\s*(?:\d+[\.)]|[-*])\s*/', '', $line) ?? $line;
            if ($line !== '') $out[] = ['label' => $labels[$i] ?? translate('Suggestion'), 'text' => $line];
        }
        return $out;
    }
}
