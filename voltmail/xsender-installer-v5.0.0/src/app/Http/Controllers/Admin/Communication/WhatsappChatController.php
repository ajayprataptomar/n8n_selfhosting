<?php

namespace App\Http\Controllers\Admin\Communication;

use App\Models\Admin;
use App\Models\Contact;
use App\Models\Gateway;
use Illuminate\View\View;
use App\Models\Conversation;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use App\Jobs\SendWhatsappConversationMessage;
use App\Services\System\Communication\ChatService;
use App\Enums\System\ConversationMessageStatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;

class WhatsappChatController extends Controller
{
    protected $chatService;

    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * Main chat interface
     */
    public function index(): View
    {
        Session::put("menu_active", true);
        $title = translate("WhatsApp Chat");

        // Get WhatsApp Node templates for template selector
        $templates = \App\Models\Template::where('channel', ChannelTypeEnum::WHATSAPP)
            ->whereNull('cloud_id')
            ->whereNull('user_id')
            ->where('status', 'active')
            ->where('plugin', false)
            ->where('default', false)
            ->where('global', false)
            ->latest()
            ->get();

        // Get WhatsApp gateways for device selector (both Node + Cloud API).
        // Filtering by NODE-only used to hide Cloud API conversations from the Chats UI,
        // even though the Cloud API webhook was correctly writing them into `conversations`.
        // Include both active gateways AND gateways that have conversations
        // (even if deleted/inactive).
        $activeDevices = Gateway::where('channel', ChannelTypeEnum::WHATSAPP)
            ->whereIn('type', [
                WhatsAppGatewayTypeEnum::NODE->value,
                WhatsAppGatewayTypeEnum::CLOUD->value,
            ])
            ->whereNull('user_id')
            ->where('status', 'active')
            ->select(['id', 'name', 'address'])
            ->get();

        // Get gateway IDs that have conversations but might not be in active devices
        $conversationGatewayIds = Conversation::where('channel', 'whatsapp')
            ->whereNull('user_id')
            ->whereNotNull('gateway_id')
            ->distinct()
            ->pluck('gateway_id');

        // Get gateways for conversations that exist in gateways table (may include inactive ones)
        $conversationDevices = Gateway::whereIn('id', $conversationGatewayIds)
            ->whereNotIn('id', $activeDevices->pluck('id'))
            ->select(['id', 'name', 'address'])
            ->get();

        // Handle orphaned conversations (gateway was deleted) - create virtual device entries
        $existingGatewayIds = Gateway::whereIn('id', $conversationGatewayIds)->pluck('id');
        $orphanedGatewayIds = $conversationGatewayIds->diff($existingGatewayIds);

        // Convert all to simple array format to avoid Eloquent method issues
        $devices = collect();

        // Add active devices
        foreach ($activeDevices as $device) {
            $devices->push(['id' => $device->id, 'name' => $device->name, 'address' => $device->address]);
        }

        // Add conversation devices (inactive but exist)
        foreach ($conversationDevices as $device) {
            $devices->push(['id' => $device->id, 'name' => $device->name, 'address' => $device->address]);
        }

        // Add orphaned devices (deleted gateways)
        foreach ($orphanedGatewayIds as $gatewayId) {
            $devices->push([
                'id' => $gatewayId,
                'name' => translate('Deleted Device') . ' #' . $gatewayId,
                'address' => null
            ]);
        }

        return view('admin.communication.whatsapp.chats', compact("title", "templates", "devices"));
    }

    /**
     * Get conversations with pagination and search
     */
    public function getConversations(Request $request)
    {
        $query = Conversation::with([
            'contact',
            'gateway:id,name,address', // Include gateway info for device indicator
            'latestMessage' // Use latestMessage relationship
        ])
        ->where('channel', 'whatsapp')
        ->whereNull('user_id');

        // Filter by device/gateway if specified
        if ($request->filled('device_id') && $request->device_id !== 'all') {
            $query->where('gateway_id', (int) $request->device_id);
        }

        // Handle search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('contact', function ($q) use ($search) {
                $q->where(function($subQuery) use ($search) {
                    $subQuery->whereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) LIKE ?", ["%{$search}%"])
                            ->orWhere('whatsapp_contact', 'LIKE', "%{$search}%")
                            ->orWhere('email_contact', 'LIKE', "%{$search}%");
                });
            });
        }

        // Handle pending filter
        if ($request->type === 'pending') {
            $query->where('unread_count', '>', 0);
        }

        $conversations = $query->orderByDesc('last_message_at')
                            ->paginate(paginateNumber(site_settings("paginate_number")));

        return response()->json([
            'conversations' => $conversations->items(),
            'has_more' => $conversations->hasMorePages(),
            'next_page' => $conversations->currentPage() + 1
        ]);
    }

    /**
     * Get conversation messages with pagination
     */
    public function show(Request $request, Conversation $conversation)
    {
        try {
            // SECURITY: Admin can only access admin conversations (user_id = null)
            if ($conversation->user_id !== null) {
                if ($request->ajax()) {
                    return response()->json(['error' => 'Unauthorized - This is a user conversation'], 403);
                }
                abort(403, 'Unauthorized access to user conversation');
            }

            $page = $request->get('page', 1);
            $perPage = paginateNumber(20);

            // Load messages with pagination - admin messages have user_id = null
            $messages = $conversation->messages()
                                    ->whereNull('user_id')
                                    ->with(['statuses', 'participants.participantable'])
                                    ->orderBy('created_at', 'desc')
                                    ->paginate($perPage, ['*'], 'page', $page);

            // Mark conversation as read
            $conversation->update(['unread_count' => 0]);
            $conversation->load('contact');

            if ($request->ajax()) {
                return response()->json([
                    'messages' => array_reverse($messages->items()),
                    'contact' => [
                        'id' => $conversation->contact->id,
                        'first_name' => $conversation->contact->first_name,
                        'last_name' => $conversation->contact->last_name,
                        'whatsapp_contact' => $conversation->contact->whatsapp_contact,
                        'email_contact' => $conversation->contact->email_contact,
                        'display_name' => $this->getContactDisplayName($conversation->contact),
                        'has_name' => $this->contactHasName($conversation->contact)
                    ],
                    'has_more' => $messages->hasMorePages(),
                    'next_page' => $messages->currentPage() + 1,
                    'conversation' => $conversation
                ]);
            }

            return redirect()->route('admin.communication.whatsapp.chats.index');

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'Failed to load messages',
                    'message' => $e->getMessage()
                ], 500);
            }

            $notify[] = ["error", "Failed to load conversation"];
            return back()->withNotify($notify);
        }
    }

    /**
     * Search messages within a conversation
     */
    public function searchMessages(Request $request, Conversation $conversation)
    {
        try {
            // SECURITY: Admin can only access admin conversations
            if ($conversation->user_id !== null) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $messages = $conversation->messages()
                                    ->whereNull('user_id')
                                    ->with(['statuses', 'participants.participantable'])
                                    ->search(['message'])
                                    ->orderBy('created_at', 'desc')
                                    ->paginate(paginateNumber(site_settings("paginate_number")));

            return response()->json([
                'messages' => array_reverse($messages->items()),
                'has_more' => $messages->hasMorePages(),
                'next_page' => $messages->currentPage() + 1
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to search messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Load more messages for infinite scroll
     */
    public function loadMoreMessages(Request $request, Conversation $conversation)
    {
        try {
            // SECURITY: Admin can only access admin conversations
            if ($conversation->user_id !== null) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $page = $request->get('page', 1);
            $perPage = paginateNumber(site_settings("paginate_number"));

            $messages = $conversation->messages()
                                    ->whereNull('user_id')
                                    ->with(['statuses', 'participants.participantable'])
                                    ->orderBy('created_at', 'desc')
                                    ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'messages' => array_reverse($messages->items()),
                'has_more' => $messages->hasMorePages(),
                'next_page' => $messages->currentPage() + 1
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load more messages',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function send(Request $request)
    {
        $request->validate([
            'conversation_id' => ["required", "exists:conversations,id"],
            'body' => 'nullable|string',
            'media' => 'nullable|file|max:16384|mimes:jpeg,jpg,png,gif,webp,pdf,doc,docx,xls,xlsx,mp3,ogg,amr,mp4,3gp',
            'media_type' => 'nullable|string|in:image,document,audio,video'
        ]);

        // Ensure at least message or media is provided
        if (empty($request->body) && !$request->hasFile('media')) {
            return response()->json([
                'error' => 'Message or media is required',
                'message' => translate('Please provide a message or upload media')
            ], 422);
        }

        try {
            // SECURITY: Admin can only send to admin conversations (user_id = null)
            $conversation = Conversation::whereNull('user_id')
                ->find($request->input("conversation_id"));

            if (!$conversation) {
                return response()->json([
                    'error' => 'Unauthorized',
                    'message' => translate('Conversation not found or access denied')
                ], 403);
            }

            $fileInfo = null;

            // Handle media upload
            if ($request->hasFile('media')) {
                $file = $request->file('media');
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
                $path = $file->storeAs('chat-media/' . date('Y/m'), $filename, 'public');
                $fileInfo = [asset('storage/' . $path)];
            }

            $message = $this->chatService->createMessage(
                conversation: $conversation,
                messageContent: $request->body ?? '',
                fileInfo: $fileInfo
            );

            $this->chatService->createMessageParticipants(
                message: $message,
                senderId: 1,
                senderType: Admin::class,
                receiverId: $conversation->contact_id,
                receiverType: Contact::class
            );

            $messageStatus = $this->chatService->createMessageStatus(
                message: $message,
                status: ConversationMessageStatusEnum::PENDING
            );

            $conversation = $conversation->load(["gateway"]);
            $gateway = $conversation->gateway;

            // Dispatch immediately for real-time chat (no queue delay)
            SendWhatsappConversationMessage::dispatchSync(
                to: $conversation->contact->whatsapp_contact,
                messageStatus: $messageStatus,
                gateway: $gateway,
                mediaUrl: $fileInfo ? $fileInfo[0] : null,
                mediaType: $request->media_type
            );

            // Reload the message with updated status after sending
            $message->load(['statuses', 'participants.participantable']);

            if ($request->ajax()) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            $notify[] = ["success", "Message sent successfully"];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'error' => 'Failed to send message',
                    'message' => $e->getMessage()
                ], 500);
            }

            $notify[] = ["error", "Failed to send message"];
            return back()->withNotify($notify);
        }
    }

    /**
     * Helper function to get contact display name
     */
    private function getContactDisplayName($contact)
    {
        $fullName = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
        return $fullName ?: ($contact->whatsapp_contact ?? $contact->email_contact ?? 'Unknown Contact');
    }

    /**
     * Helper function to check if contact has name
     */
    private function contactHasName($contact)
    {
        return !empty($contact->first_name) || !empty($contact->last_name);
    }

    /**
     * Helper function to get last message text
     */
    private function getLastMessageText($conversation)
    {
        $lastMessage = $conversation->messages->last();
        
        if (!$lastMessage) {
            return null;
        }

        if ($lastMessage->message) {
            return $lastMessage->message;
        }

        if ($lastMessage->file_info && is_array($lastMessage->file_info) && count($lastMessage->file_info) > 0) {
            return 'Media file';
        }

        return 'Message';
    }

    /**
     * Start or find a conversation from WhatsApp logs
     * This allows users to click "Chat" button in logs to start a conversation
     */
    public function startChat(Request $request)
    {
        try {
            $contactId = $request->get('contact_id');
            $gatewayId = $request->get('gateway_id');
            $gatewayType = $request->get('gateway_type');

            if (!$contactId) {
                $notify[] = ['error', translate('Contact not found')];
                return back()->withNotify($notify);
            }

            $contact = Contact::find($contactId);
            if (!$contact) {
                $notify[] = ['error', translate('Contact not found')];
                return back()->withNotify($notify);
            }

            // Find or create conversation
            $conversation = Conversation::firstOrCreate(
                [
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp',
                    'user_id' => null, // Admin conversation
                ],
                [
                    'gateway_id' => $gatewayId,
                    'last_message_at' => now(),
                    'unread_count' => 0,
                    'meta_data' => [
                        'gateway_type' => $gatewayType,
                        'started_from' => 'logs'
                    ],
                ]
            );

            // If conversation exists but has no gateway, update it
            if ($conversation->wasRecentlyCreated === false && !$conversation->gateway_id && $gatewayId) {
                $conversation->update(['gateway_id' => $gatewayId]);
            }

            // Redirect to chat page with conversation selected
            return redirect()->route('admin.communication.whatsapp.chats.index', [
                'conversation' => $conversation->id
            ]);

        } catch (\Exception $e) {
            $notify[] = ['error', translate('Failed to start conversation: ') . $e->getMessage()];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete a conversation
     */
    public function destroy(Request $request, Conversation $conversation)
    {
        try {
            // SECURITY: Admin can only delete admin conversations
            if ($conversation->user_id !== null) {
                if ($request->ajax()) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
                abort(403, 'Unauthorized');
            }

            // Delete all messages in the conversation
            $conversation->messages()->delete();

            // Delete the conversation
            $conversation->delete();

            if ($request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => translate('Conversation deleted successfully')
                ]);
            }

            $notify[] = ['success', translate('Conversation deleted successfully')];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            if ($request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => translate('Failed to delete conversation')
                ], 500);
            }

            $notify[] = ['error', translate('Failed to delete conversation')];
            return back()->withNotify($notify);
        }
    }
}