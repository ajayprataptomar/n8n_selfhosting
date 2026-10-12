<?php

namespace App\Services\Automation;

use App\Models\User;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\ContactGroup;
use App\Models\Automation\WorkflowNode;
use App\Models\Automation\WorkflowExecution;
use App\Enums\Common\Status;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\GatewayManager;
use App\Services\System\Communication\NodeService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

class ActionExecutorService
{
    protected GatewayManager $gatewayManager;
    protected NodeService $nodeService;

    public function __construct()
    {
        $this->gatewayManager = new GatewayManager();
        $this->nodeService = new NodeService();
    }

    /**
     * Execute an action node
     */
    public function execute(WorkflowNode $node, Contact $contact, WorkflowExecution $execution): array
    {
        $actionType = $node->action_type;
        $config = $node->config ?? [];
        $workflow = $execution->workflow;
        $user = $workflow->user;

        Log::info("Executing action", [
            'action_type' => $actionType,
            'node_id' => $node->id,
            'contact_id' => $contact->id,
        ]);

        return match ($actionType) {
            'send_sms' => $this->executeSendSms($config, $contact, $user),
            'send_email' => $this->executeSendEmail($config, $contact, $user),
            'send_whatsapp' => $this->executeSendWhatsapp($config, $contact, $user),
            'add_to_group' => $this->executeAddToGroup($config, $contact, $user),
            'remove_from_group' => $this->executeRemoveFromGroup($config, $contact, $user),
            'update_contact' => $this->executeUpdateContact($config, $contact),
            'add_tag' => $this->executeAddTag($config, $contact),
            'notify_admin' => $this->executeNotifyAdmin($config, $contact, $user, $execution),
            'call_webhook' => $this->executeCallWebhook($config, $contact, $execution),
            'ai_auto_reply' => $this->executeAiAutoReply($config, $contact, $user, $execution),
            // v5.0-S6 — static-text counterpart to ai_auto_reply. Reuses
            // sendAiReply() dispatch helper (misnamed but generic) which
            // already routes by channel to the correct send utility.
            'send_template_reply' => $this->executeSendTemplateReply($config, $contact, $user, $execution),
            default => [
                'success' => false,
                'error' => "Unknown action type: {$actionType}",
            ],
        };
    }

    /**
     * v5.0-S6 — Reply on the same channel the trigger came from with a
     * fixed template. Placeholders {first_name}/{last_name}/{name}/{phone}
     * are replaced by parseMessage() (same behaviour as executeSendSms).
     */
    protected function executeSendTemplateReply(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        $raw = (string) ($config['message'] ?? '');
        if (trim($raw) === '') {
            return ['success' => false, 'error' => 'Template message is empty'];
        }

        $message = $this->parseMessage($raw, $contact);

        // Match executeAiAutoReply's channel-resolution logic — inbound
        // channel is available in trigger_data.channel; explicit
        // config.channel override wins when set.
        $context     = $execution->context ?? [];
        $triggerData = $context['trigger_data'] ?? [];
        $channel     = $config['channel']
            ?? Arr::get($triggerData, 'channel')
            ?? Arr::get($triggerData, 'reply_data.gateway_type')
            ?? null;

        return $this->sendAiReply($config, $contact, $user, $message, $channel);
    }

    /**
     * Send SMS action
     */
    protected function executeSendSms(array $config, Contact $contact, ?User $user): array
    {
        $gatewayId = $config['gateway_id'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);
        $senderId = $config['sender_id'] ?? null;

        if (!$contact->sms_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no SMS number',
            ];
        }

        $gateway = Gateway::where('id', $gatewayId)
            ->where('channel', ChannelTypeEnum::SMS->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'SMS gateway not found or inactive',
            ];
        }

        try {
            // Use the existing dispatch system
            $dispatchData = [
                'gateway_id' => $gateway->id,
                'to' => $contact->sms_contact,
                'message' => $message,
                'sender_id' => $senderId,
            ];

            // Queue the message through the existing system
            \App\Models\DispatchLog::create([
                'uid' => str_unique(),
                'user_id' => $user?->id,
                'contact_id' => $contact->id,
                'gateway_id' => $gateway->id,
                'channel' => ChannelTypeEnum::SMS->value,
                'to' => $contact->sms_contact,
                'message' => $message,
                'status' => 'pending',
                'schedule_at' => now(),
                'meta_data' => ['sender_id' => $senderId, 'source' => 'automation'],
            ]);

            return [
                'success' => true,
                'data' => ['to' => $contact->sms_contact, 'gateway' => $gateway->name],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send Email action
     */
    protected function executeSendEmail(array $config, Contact $contact, ?User $user): array
    {
        $gatewayId = $config['gateway_id'] ?? null;
        $subject = $this->parseMessage($config['subject'] ?? '', $contact);
        $message = $this->parseMessage($config['message'] ?? '', $contact);

        if (!$contact->email_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no email address',
            ];
        }

        $gateway = Gateway::where('id', $gatewayId)
            ->where('channel', ChannelTypeEnum::EMAIL->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'Email gateway not found or inactive',
            ];
        }

        try {
            // Queue the email through the existing system
            \App\Models\DispatchLog::create([
                'uid' => str_unique(),
                'user_id' => $user?->id,
                'contact_id' => $contact->id,
                'gateway_id' => $gateway->id,
                'channel' => ChannelTypeEnum::EMAIL->value,
                'to' => $contact->email_contact,
                'message' => $message,
                'status' => 'pending',
                'schedule_at' => now(),
                'meta_data' => ['subject' => $subject, 'source' => 'automation'],
            ]);

            return [
                'success' => true,
                'data' => ['to' => $contact->email_contact, 'subject' => $subject],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send WhatsApp action
     */
    protected function executeSendWhatsapp(array $config, Contact $contact, ?User $user): array
    {
        $deviceId = $config['device_id'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);
        $mediaUrl = $config['media_url'] ?? null;

        if (!$contact->whatsapp_contact) {
            return [
                'success' => false,
                'error' => 'Contact has no WhatsApp number',
            ];
        }

        $gateway = Gateway::where('id', $deviceId)
            ->where('channel', ChannelTypeEnum::WHATSAPP->value)
            ->where('status', Status::ACTIVE->value)
            ->first();

        if (!$gateway) {
            return [
                'success' => false,
                'error' => 'WhatsApp device not found or inactive',
            ];
        }

        try {
            // Queue the message through the existing system
            \App\Models\DispatchLog::create([
                'uid' => str_unique(),
                'user_id' => $user?->id,
                'contact_id' => $contact->id,
                'gateway_id' => $gateway->id,
                'channel' => ChannelTypeEnum::WHATSAPP->value,
                'to' => $contact->whatsapp_contact,
                'message' => $message,
                'status' => 'pending',
                'schedule_at' => now(),
                'meta_data' => ['media_url' => $mediaUrl, 'source' => 'automation'],
            ]);

            return [
                'success' => true,
                'data' => ['to' => $contact->whatsapp_contact, 'device' => $gateway->name],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add contact to group action
     */
    protected function executeAddToGroup(array $config, Contact $contact, ?User $user): array
    {
        $groupId = $config['group_id'] ?? null;

        if (!$groupId) {
            return [
                'success' => false,
                'error' => 'Group ID not specified',
            ];
        }

        $group = ContactGroup::where('id', $groupId)
            ->when($user, fn($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$group) {
            return [
                'success' => false,
                'error' => 'Group not found',
            ];
        }

        try {
            // Check if contact already in group
            $existsInGroup = Contact::where('group_id', $group->id)
                ->where(function ($q) use ($contact) {
                    if ($contact->email_contact) {
                        $q->orWhere('email_contact', $contact->email_contact);
                    }
                    if ($contact->sms_contact) {
                        $q->orWhere('sms_contact', $contact->sms_contact);
                    }
                    if ($contact->whatsapp_contact) {
                        $q->orWhere('whatsapp_contact', $contact->whatsapp_contact);
                    }
                })
                ->exists();

            if ($existsInGroup) {
                return [
                    'success' => true,
                    'data' => ['group' => $group->name, 'note' => 'Contact already in group'],
                ];
            }

            // Clone contact to new group
            $newContact = $contact->replicate();
            $newContact->uid = str_unique();
            $newContact->group_id = $group->id;
            $newContact->save();

            return [
                'success' => true,
                'data' => ['group' => $group->name, 'new_contact_id' => $newContact->id],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Remove contact from group action
     */
    protected function executeRemoveFromGroup(array $config, Contact $contact, ?User $user): array
    {
        $groupId = $config['group_id'] ?? null;

        if (!$groupId) {
            return [
                'success' => false,
                'error' => 'Group ID not specified',
            ];
        }

        try {
            // Find and remove contact from the specified group
            $deleted = Contact::where('group_id', $groupId)
                ->when($user, fn($q) => $q->where('user_id', $user->id))
                ->where(function ($q) use ($contact) {
                    if ($contact->email_contact) {
                        $q->orWhere('email_contact', $contact->email_contact);
                    }
                    if ($contact->sms_contact) {
                        $q->orWhere('sms_contact', $contact->sms_contact);
                    }
                    if ($contact->whatsapp_contact) {
                        $q->orWhere('whatsapp_contact', $contact->whatsapp_contact);
                    }
                })
                ->delete();

            return [
                'success' => true,
                'data' => ['removed' => $deleted > 0],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Update contact field action
     */
    protected function executeUpdateContact(array $config, Contact $contact): array
    {
        $field = $config['field'] ?? null;
        $value = $config['value'] ?? null;

        if (!$field) {
            return [
                'success' => false,
                'error' => 'Field not specified',
            ];
        }

        try {
            // Handle standard fields
            $standardFields = ['first_name', 'last_name', 'email_contact', 'sms_contact', 'whatsapp_contact', 'status'];

            if (in_array($field, $standardFields)) {
                $contact->$field = $value;
                $contact->save();
            } else {
                // Handle meta_data fields
                $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];
                $metaData[$field] = ['value' => $value, 'type' => 'text'];
                $contact->meta_data = json_encode($metaData);
                $contact->save();
            }

            return [
                'success' => true,
                'data' => ['field' => $field, 'value' => $value],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Add tag to contact action
     */
    protected function executeAddTag(array $config, Contact $contact): array
    {
        $tag = $config['tag'] ?? null;

        if (!$tag) {
            return [
                'success' => false,
                'error' => 'Tag not specified',
            ];
        }

        try {
            $metaData = $contact->meta_data ? json_decode($contact->meta_data, true) : [];
            $tags = $metaData['tags'] ?? [];

            if (!in_array($tag, $tags)) {
                $tags[] = $tag;
                $metaData['tags'] = $tags;
                $contact->meta_data = json_encode($metaData);
                $contact->save();
            }

            return [
                'success' => true,
                'data' => ['tag' => $tag, 'all_tags' => $tags],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Notify admin action
     */
    protected function executeNotifyAdmin(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        $method = $config['method'] ?? 'email';
        $recipient = $config['recipient'] ?? null;
        $message = $this->parseMessage($config['message'] ?? '', $contact);

        if (!$recipient) {
            // Default to workflow owner email
            $recipient = $user?->email ?? site_settings('mail_from_address');
        }

        try {
            if ($method === 'email') {
                // Send email notification
                Mail::raw($message, function ($mail) use ($recipient, $execution) {
                    $mail->to($recipient)
                        ->subject("Workflow Notification: {$execution->workflow->name}");
                });
            } elseif ($method === 'webhook') {
                // Call webhook for notification
                Http::post($recipient, [
                    'workflow' => $execution->workflow->name,
                    'contact' => [
                        'id' => $contact->id,
                        'name' => trim("{$contact->first_name} {$contact->last_name}"),
                        'email' => $contact->email_contact,
                        'phone' => $contact->sms_contact ?? $contact->whatsapp_contact,
                    ],
                    'message' => $message,
                    'timestamp' => now()->toISOString(),
                ]);
            }

            return [
                'success' => true,
                'data' => ['method' => $method, 'recipient' => $recipient],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Call external webhook action
     */
    protected function executeCallWebhook(array $config, Contact $contact, WorkflowExecution $execution): array
    {
        $url = $config['url'] ?? null;
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? [];

        if (!$url) {
            return [
                'success' => false,
                'error' => 'Webhook URL not specified',
            ];
        }

        try {
            // Build webhook payload
            $payload = array_merge($body, [
                'contact' => [
                    'id' => $contact->id,
                    'uid' => $contact->uid,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'email' => $contact->email_contact,
                    'phone' => $contact->sms_contact,
                    'whatsapp' => $contact->whatsapp_contact,
                    'meta_data' => $contact->meta_data ? json_decode($contact->meta_data, true) : [],
                ],
                'workflow' => [
                    'id' => $execution->workflow_id,
                    'name' => $execution->workflow->name,
                ],
                'execution_id' => $execution->uid,
                'timestamp' => now()->toISOString(),
            ]);

            // Parse any variables in the payload
            $payload = $this->parsePayload($payload, $contact);

            $response = match ($method) {
                'GET' => Http::withHeaders($headers)->get($url, $payload),
                'PUT' => Http::withHeaders($headers)->put($url, $payload),
                'PATCH' => Http::withHeaders($headers)->patch($url, $payload),
                default => Http::withHeaders($headers)->post($url, $payload),
            };

            return [
                'success' => $response->successful(),
                'data' => [
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body(),
                ],
                'error' => $response->failed() ? "HTTP {$response->status()}" : null,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Parse message with contact variables
     */
    /**
     * AI Auto-Reply action
     *
     * Generates an AI response based on the incoming message context
     * and sends it back via the appropriate channel (WhatsApp/SMS/Email).
     *
     * Config keys:
     *   - channel: 'whatsapp' | 'sms' | 'email' (auto-detect if not set)
     *   - system_prompt: Custom system instruction for the AI (optional)
     *   - model: OpenAI model name (default: gpt-4o-mini)
     *   - max_tokens: Max response length (default: 500)
     *   - gateway_id / device_id: Specific gateway to send reply through
     */
    protected function executeAiAutoReply(array $config, Contact $contact, ?User $user, WorkflowExecution $execution): array
    {
        // Get the incoming message that triggered this workflow
        $context = $execution->context ?? [];
        $triggerData = $context['trigger_data'] ?? [];
        $incomingMessage = Arr::get($triggerData, 'reply_data.message_body')
            ?? Arr::get($triggerData, 'message_body')
            ?? '';

        if (empty($incomingMessage)) {
            return ['success' => false, 'error' => 'No incoming message found to reply to'];
        }

        // Get AI configuration
        $aiModels = json_decode(site_settings('ai_models', '{}'), true);
        $aiEnabled = Arr::get($aiModels, 'open_ai_text.status', '0');
        $apiKey = Arr::get($aiModels, 'open_ai_text.key', '');
        $baseUrl = Arr::get($aiModels, 'open_ai_text.base_url', '');

        if ($aiEnabled !== '1' || empty($apiKey) || $apiKey === '###') {
            return ['success' => false, 'error' => 'AI module is not configured or disabled'];
        }

        // Build context for AI
        $systemPrompt = $config['system_prompt'] ?? 'You are a helpful customer service assistant. Reply concisely and professionally. Keep responses under 200 words.';
        $model = $config['model'] ?? Arr::get($aiModels, 'open_ai_text.model', 'gpt-4o-mini');
        $maxTokens = (int) ($config['max_tokens'] ?? 500);

        // Add contact context to system prompt
        $contactContext = '';
        if ($contact->first_name || $contact->last_name) {
            $contactContext = " The customer's name is " . trim("{$contact->first_name} {$contact->last_name}") . ".";
        }

        // Multi-language support — let the AI match the user's language by default, or pin to
        // a specific language if reply_language is explicitly set in node config. The model is
        // already multilingual; explicit instruction in the system prompt removes ambiguity.
        $replyLanguage = trim((string) ($config['reply_language'] ?? ''));
        if ($replyLanguage !== '' && strtolower($replyLanguage) !== 'auto') {
            $contactContext .= " Always reply in {$replyLanguage}, regardless of the language the customer uses.";
        } else {
            $contactContext .= " Detect the language the customer is writing in and reply in the SAME language. Match formal/informal tone if applicable.";
        }

        try {
            // Support custom base URLs (DeepSeek, Groq, Together AI, etc.)
            if (!empty($baseUrl)) {
                $client = \OpenAI::factory()
                    ->withApiKey($apiKey)
                    ->withBaseUri($baseUrl)
                    ->make();
            } else {
                $client = \OpenAI::client($apiKey);
            }

            $result = $client->chat()->create([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt . $contactContext],
                    ['role' => 'user', 'content' => $incomingMessage],
                ],
            ]);

            $aiResponse = $result['choices'][0]['message']['content'] ?? '';

            if (empty($aiResponse)) {
                return ['success' => false, 'error' => 'AI returned empty response'];
            }

            // Determine reply channel
            $channel = $config['channel'] ?? $triggerData['reply_data']['gateway_type'] ?? null;

            // Send the AI response via the appropriate channel
            $sendResult = $this->sendAiReply($config, $contact, $user, $aiResponse, $channel);

            // Store AI response in execution context for downstream nodes
            $existingContext = is_array($execution->context) ? $execution->context : json_decode($execution->context ?? '{}', true);
            $existingContext['ai_response'] = $aiResponse;
            $existingContext['ai_model'] = $model;
            $execution->update(['context' => $existingContext]);

            return [
                'success' => $sendResult['success'],
                'data' => [
                    'ai_response' => mb_substr($aiResponse, 0, 200) . (mb_strlen($aiResponse) > 200 ? '...' : ''),
                    'channel' => $channel,
                    'send_result' => $sendResult,
                ],
            ];

        } catch (\Exception $e) {
            Log::error('AI auto-reply failed', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => 'AI generation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Send AI-generated reply through the appropriate channel
     */
    protected function sendAiReply(array $config, Contact $contact, ?User $user, string $message, ?string $channel): array
    {
        // Auto-detect channel based on available contact info
        if (!$channel || $channel === 'node' || $channel === 'cloud_api') {
            if ($contact->whatsapp_contact) {
                $channel = 'whatsapp';
            } elseif ($contact->sms_contact) {
                $channel = 'sms';
            } elseif ($contact->email_contact) {
                $channel = 'email';
            } else {
                return ['success' => false, 'error' => 'No contact channel available for reply'];
            }
        }

        // Delegate to existing send methods
        $sendConfig = array_merge($config, ['message' => $message]);

        return match ($channel) {
            'whatsapp' => $this->executeSendWhatsapp($sendConfig, $contact, $user),
            'sms' => $this->executeSendSms($sendConfig, $contact, $user),
            'email' => $this->executeSendEmail(
                array_merge($sendConfig, ['subject' => $config['email_subject'] ?? 'Re: Your message']),
                $contact,
                $user
            ),
            default => ['success' => false, 'error' => "Unsupported reply channel: {$channel}"],
        };
    }

    protected function parseMessage(string $message, Contact $contact): string
    {
        $replacements = [
            '{{first_name}}' => $contact->first_name ?? '',
            '{{last_name}}' => $contact->last_name ?? '',
            '{{full_name}}' => trim("{$contact->first_name} {$contact->last_name}"),
            '{{email}}' => $contact->email_contact ?? '',
            '{{phone}}' => $contact->sms_contact ?? '',
            '{{whatsapp}}' => $contact->whatsapp_contact ?? '',
        ];

        // Add meta_data variables
        if ($contact->meta_data) {
            $metaData = json_decode($contact->meta_data, true);
            foreach ($metaData as $key => $value) {
                $val = is_array($value) ? ($value['value'] ?? '') : $value;
                $replacements["{{$key}}"] = $val;
            }
        }

        return str_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Parse payload recursively with contact variables
     */
    protected function parsePayload(array $payload, Contact $contact): array
    {
        foreach ($payload as $key => $value) {
            if (is_string($value)) {
                $payload[$key] = $this->parseMessage($value, $contact);
            } elseif (is_array($value)) {
                $payload[$key] = $this->parsePayload($value, $contact);
            }
        }
        return $payload;
    }
}
