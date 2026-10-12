<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\System\Communication\InboxAiCopilotService;
use App\Services\System\Communication\InboxReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * v5.0 Sprint 1 — Unified Inbox (foundation, read-only).
 *
 * One screen for every conversation across every channel: SMS + Email +
 * WhatsApp (Cloud + Node). Same UI shape as the per-channel WhatsApp Chats
 * page, but without the channel filter locked to whatsapp.
 *
 * Reuses:
 *   - App\Models\Conversation (schema already supports the three channels
 *     via its `channel` enum — no migration needed)
 *   - App\Models\Message + ChatService (all channel-agnostic)
 *   - partials/notify.blade.php (toast layer)
 *   - lead-stat-styles design tokens
 *
 * v5.0 Sprint 4 will add cross-channel reply from this same UI. Sprints 2–3
 * add inbound reply capture (email IMAP / SMS webhook) so incoming replies
 * from all providers create conversation rows automatically.
 */
class InboxController extends Controller
{
    /**
     * Render the unified inbox page.
     */
    public function index(): View
    {
        Session::put('menu_active', true);
        return view('admin.communication.inbox.index', [
            'title'          => translate('Unified Inbox'),
            'listUrl'        => route('admin.communication.inbox.conversations'),
            'threadUrlBase'  => url('/admin/communication/inbox/conversations'),
            'channels'       => $this->availableChannels(),
        ]);
    }

    /**
     * Paginated conversation list — admin sees every conversation
     * (user_id NULL only, admin scope). Filterable by channel + search.
     */
    public function conversations(Request $request): JsonResponse
    {
        $q = Conversation::with(['contact:id,first_name,last_name,email_contact,sms_contact,whatsapp_contact', 'gateway:id,name,address', 'latestMessage'])
            ->whereNull('user_id'); // admin-scope conversations

        if ($request->filled('channel') && $request->channel !== 'all') {
            $q->where('channel', $request->channel);
        }
        if ($request->filled('unread_only') && $request->boolean('unread_only')) {
            $q->where('unread_count', '>', 0);
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $q->whereHas('contact', function ($sub) use ($s) {
                $sub->where(function ($w) use ($s) {
                    $w->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$s}%"])
                      ->orWhere('email_contact',    'like', "%{$s}%")
                      ->orWhere('sms_contact',      'like', "%{$s}%")
                      ->orWhere('whatsapp_contact', 'like', "%{$s}%");
                });
            });
        }

        $page = $q->orderByDesc('last_message_at')
            ->paginate((int) $request->input('per_page', 30));

        return response()->json([
            'conversations' => $page->items(),
            'has_more'      => $page->hasMorePages(),
            'next_page'     => $page->currentPage() + 1,
        ]);
    }

    /**
     * Read a single conversation's messages. Marks it read.
     */
    public function show(Conversation $conversation): JsonResponse
    {
        // Admin scope only for now.
        if ($conversation->user_id !== null) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with(['statuses', 'participants.participantable'])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

        // Mark read
        $conversation->update(['unread_count' => 0]);
        $conversation->load(['contact', 'gateway:id,name,address,channel,type']);

        return response()->json([
            'conversation' => $conversation,
            'messages'     => $messages->items(),
            'has_more'     => $messages->hasMorePages(),
        ]);
    }

    /**
     * v5.0-S4 — Send a reply on any channel (SMS / Email / WhatsApp).
     * Delegates to InboxReplyService which reuses existing per-channel
     * senders (SendMail / SendSMS / SendWhatsappConversationMessage).
     */
    public function reply(Request $request, Conversation $conversation, InboxReplyService $svc): JsonResponse
    {
        // Admin-scope conversations only for this controller.
        if ($conversation->user_id !== null) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'body'  => 'nullable|string',
            'media' => 'nullable|file|max:16384',
        ]);

        try {
            $media = null;
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $path = $file->storeAs('inbox-media/' . date('Y/m'), $filename, 'public');
                $media = [asset('storage/' . $path)];
            }

            $message = $svc->reply(
                conversation: $conversation,
                body:         (string) $request->input('body', ''),
                user:         null, // admin-scope
                media:        $media,
            );

            return response()->json(['ok' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * v5.0-S5 — AI Reply Copilot suggestion endpoint.
     * Rate-limited via the `throttle:inbox-ai` middleware on the route.
     */
    public function copilotSuggest(Conversation $conversation, InboxAiCopilotService $copilot): JsonResponse
    {
        if ($conversation->user_id !== null) {
            return response()->json(['ok' => false, 'reason' => 'Unauthorized'], 403);
        }
        $result = $copilot->suggest($conversation, null);
        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Channels the inbox can filter by. Excludes any channel this install
     * doesn't have any conversations for — cleaner UI on fresh installs.
     */
    private function availableChannels(): array
    {
        $used = Conversation::whereNull('user_id')
            ->select('channel')
            ->distinct()
            ->pluck('channel')
            ->all();

        $all = [
            'email'    => ['label' => translate('Email'),    'icon' => 'ri-mail-line',     'color' => '#3b82f6'],
            'sms'      => ['label' => translate('SMS'),      'icon' => 'ri-chat-1-line',   'color' => '#6366f1'],
            'whatsapp' => ['label' => translate('WhatsApp'), 'icon' => 'ri-whatsapp-line', 'color' => '#25d366'],
        ];

        // Always expose all three so the empty-state message reads correctly;
        // frontend hides zero-conversation channels only when the admin toggles
        // the compact-view checkbox.
        $out = [];
        foreach ($all as $key => $cfg) {
            $out[$key] = $cfg + ['used' => in_array($key, $used, true)];
        }
        return $out;
    }
}
