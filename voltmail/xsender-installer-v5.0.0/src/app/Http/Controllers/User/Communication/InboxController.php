<?php

namespace App\Http\Controllers\User\Communication;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\System\Communication\InboxAiCopilotService;
use App\Services\System\Communication\InboxReplyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

/**
 * v5.0 Sprint 1 — Unified Inbox for the member/user panel.
 *
 * Mirror of the admin controller but scoped to the authenticated user's
 * conversations only. Same reuse story — no new models, no new services,
 * schema already supports every channel via Conversation.channel enum.
 */
class InboxController extends Controller
{
    /**
     * v5.0 plan-gate. Backward-compatible default = allowed. Admins who
     * want to restrict the inbox to specific tiers flip the feature key
     * `unified_inbox.is_allowed` to false on the free/starter plan.
     */
    protected function checkAccess(): bool
    {
        $user   = auth()->user();
        $access = $user ? planAccess($user) : [];

        // Not gated at all in the plan → allow (avoids silently disabling
        // the inbox on installs that upgraded before their plans were
        // reconfigured for v5.0).
        if (!isset($access['unified_inbox'])) {
            return true;
        }
        return (bool) ($access['unified_inbox']['is_allowed'] ?? true);
    }

    public function index()
    {
        if (!$this->checkAccess()) {
            $notify[] = ['error', translate('Your current plan does not include the Unified Inbox. Please upgrade your plan.')];
            return redirect()->route('user.dashboard')->withNotify($notify);
        }
        Session::put('menu_active', true);
        return view('user.communication.inbox.index', [
            'title'          => translate('Unified Inbox'),
            'listUrl'        => route('user.communication.inbox.conversations'),
            'threadUrlBase'  => url('/user/communication/inbox/conversations'),
            'channels'       => $this->availableChannels(auth()->id()),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Not permitted on your plan.')], 403);
        }

        $userId = auth()->id();

        $q = Conversation::with(['contact:id,first_name,last_name,email_contact,sms_contact,whatsapp_contact', 'gateway:id,name,address', 'latestMessage'])
            ->where('user_id', $userId);

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

    public function show(Conversation $conversation): JsonResponse
    {
        if (!$this->checkAccess()) {
            return response()->json(['error' => translate('Not permitted on your plan.')], 403);
        }
        // SECURITY: user can only see their own conversations.
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $messages = $conversation->messages()
            ->with(['statuses', 'participants.participantable'])
            ->orderBy('created_at', 'asc')
            ->paginate(50);

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
     * Same as admin action but scoped to auth user's conversations only.
     */
    public function reply(Request $request, Conversation $conversation, InboxReplyService $svc): JsonResponse
    {
        if (!$this->checkAccess()) {
            return response()->json(['ok' => false, 'error' => translate('Not permitted on your plan.')], 403);
        }
        if ($conversation->user_id !== auth()->id()) {
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
                user:         auth()->user(),
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
     * v5.0-S5 — AI Reply Copilot suggestion endpoint (user-scope).
     * Route rate-limits per user via throttle middleware.
     */
    public function copilotSuggest(Conversation $conversation, InboxAiCopilotService $copilot): JsonResponse
    {
        if (!$this->checkAccess()) {
            return response()->json(['ok' => false, 'reason' => translate('Not permitted on your plan.')], 403);
        }
        if ($conversation->user_id !== auth()->id()) {
            return response()->json(['ok' => false, 'reason' => 'Unauthorized'], 403);
        }
        $result = $copilot->suggest($conversation, auth()->user());
        return response()->json($result, ($result['ok'] ?? false) ? 200 : 422);
    }

    private function availableChannels(int $userId): array
    {
        $used = Conversation::where('user_id', $userId)
            ->select('channel')
            ->distinct()
            ->pluck('channel')
            ->all();

        $all = [
            'email'    => ['label' => translate('Email'),    'icon' => 'ri-mail-line',     'color' => '#3b82f6'],
            'sms'      => ['label' => translate('SMS'),      'icon' => 'ri-chat-1-line',   'color' => '#6366f1'],
            'whatsapp' => ['label' => translate('WhatsApp'), 'icon' => 'ri-whatsapp-line', 'color' => '#25d366'],
        ];

        $out = [];
        foreach ($all as $key => $cfg) {
            $out[$key] = $cfg + ['used' => in_array($key, $used, true)];
        }
        return $out;
    }
}
