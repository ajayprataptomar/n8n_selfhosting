<?php

namespace App\Services\Campaign;

use App\Enums\Campaign\CampaignChannel;
use App\Enums\Campaign\DispatchStatus;
use App\Enums\Campaign\UnifiedCampaignStatus;
use App\Models\CampaignDispatch;
use App\Models\CampaignMessage;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\UnifiedCampaign;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use PHPMailer\PHPMailer\PHPMailer;

class CampaignDispatchService
{
    /**
     * Process a single dispatch
     */
    public function processDispatch(CampaignDispatch $dispatch): bool
    {
        try {
            // Mark as processing
            $dispatch->markAsProcessing();

            $message = $dispatch->campaignMessage;
            $contact = $dispatch->contact;
            $gateway = $dispatch->gateway;

            if (!$message || !$contact) {
                $dispatch->markAsFailed('Missing message or contact data');
                return false;
            }

            $isAndroidPull = $dispatch->channel === CampaignChannel::SMS
                && !empty($dispatch->android_sim_id);

            if (!$gateway && !$isAndroidPull) {
                $dispatch->markAsFailed('No gateway assigned to this dispatch');
                return false;
            }

            // Get personalized content
            $content = $message->getPersonalizedContent($contact);

            // For email: generate unsubscribe link and inject into content (spam compliance)
            $unsubscribeLink = null;
            if ($dispatch->channel === CampaignChannel::EMAIL) {
                $unsubscribeLink = $this->generateUnsubscribeLink(
                    $dispatch->campaign_id,
                    $contact->uid
                );
                $content = $this->injectUnsubscribeLink($content, $unsubscribeLink);

                // Inject open pixel + click tracking so the unified campaign records opens/clicks.
                if (site_settings('email_tracking_enabled') == \App\Enums\StatusEnum::TRUE->status()) {
                    $content = \App\Http\Controllers\TrackingController::injectTracking(
                        $content,
                        $dispatch->id,
                        $dispatch->campaign->user_id ?? null,
                        unified: true
                    );
                }
            }

            // Send based on channel.
            // Android-pull SMS returns 'queued' — async hand-off to the device, no immediate send.
            $result = match ($dispatch->channel) {
                CampaignChannel::SMS => $isAndroidPull
                    ? $this->queueSmsForAndroid($dispatch, $contact, $content)
                    : $this->sendSms($dispatch, $gateway, $contact, $content),
                CampaignChannel::EMAIL => $this->sendEmail($dispatch, $gateway, $contact, $content, $message, $unsubscribeLink),
                CampaignChannel::WHATSAPP => $this->sendWhatsApp($dispatch, $gateway, $contact, $content, $message),
                default => false,
            };

            // 'queued' = async (Android pull): leave status alone, MirrorAndroidSmsStatus will resolve.
            // true = synchronously sent
            // false = failed (already marked inside the send method)
            if ($result === 'queued') {
                return true;
            }

            if ($result === true) {
                $dispatch->markAsSent();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'sent');
                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Dispatch error: ' . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'campaign_id' => $dispatch->campaign_id,
                'trace' => $e->getTraceAsString(),
            ]);

            $dispatch->markAsFailed($e->getMessage());
            $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');

            return false;
        }
    }

    // ============ EMAIL SENDING ============

    /**
     * Send Email using the gateway's configured provider
     */
    protected function sendEmail(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content,
        CampaignMessage $message,
        ?string $unsubscribeLink = null
    ): bool {
        $email = $contact->email_contact;

        if (empty($email)) {
            $dispatch->markAsFailed('No email address');
            return false;
        }

        $subject = $message->getPersonalizedSubject($contact);

        // Per-message sender overrides (fall back to gateway defaults).
        $fromName = $message->from_name ?: $gateway->name;
        $replyTo  = $message->reply_to ?: $gateway->address;

        // v5.0-S2 — Unified Inbox reply capture.
        // If the customer has configured an inbound reply domain, rewrite the
        // Reply-To to a signed reply-{token}@{domain} address. Their inbound
        // MX-forward then POSTs the customer's reply to /webhooks/inbound-email
        // and it appears in the Unified Inbox thread for this campaign contact.
        // Feature is opt-in: empty domain = no rewrite (default v4.x behaviour).
        if (site_settings('inbound_reply_domain')) {
            try {
                $ownerUserId = $dispatch->campaign?->user_id;
                $inboundReplyTo = app(\App\Services\System\Communication\InboundEmailService::class)
                    ->makeReplyToAddress(
                        campaignDispatchId: $dispatch->id,
                        dispatchLogId:      null,
                        userId:             $ownerUserId,
                    );
                if ($inboundReplyTo) {
                    $replyTo = $inboundReplyTo;
                }
            } catch (\Throwable $e) {
                // Never break the send because the inbox helper failed.
                Log::warning('Inbound reply-to rewrite skipped: ' . $e->getMessage(), [
                    'dispatch_id' => $dispatch->id,
                ]);
            }
        }

        try {
            $sent = match ($gateway->type) {
                'smtp'     => $this->sendViaSmtp($gateway, $email, $subject, $content, $unsubscribeLink, $fromName, $replyTo),
                'mailjet'  => $this->sendViaMailjet($gateway, $email, $subject, $content, $unsubscribeLink, $fromName, $replyTo),
                'sendgrid' => $this->sendViaSendgrid($gateway, $email, $subject, $content, $unsubscribeLink, $fromName, $replyTo),
                'aws'      => $this->sendViaSes($gateway, $email, $subject, $content, $unsubscribeLink, $fromName, $replyTo),
                'mailgun'  => $this->sendViaMailgun($gateway, $email, $subject, $content, $unsubscribeLink, $fromName, $replyTo),
                default    => throw new \Exception("Unsupported email gateway type: {$gateway->type}"),
            };

            return $sent;
        } catch (\Exception $e) {
            Log::error("Campaign email send failed: " . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'gateway_type' => $gateway->type,
                'to' => $email,
            ]);
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send email via SMTP using PHPMailer
     */
    protected function sendViaSmtp(Gateway $gateway, string $to, string $subject, string $body, ?string $unsubscribeLink = null, ?string $fromName = null, ?string $replyTo = null): bool
    {
        $mail = new PHPMailer(true);

        $mail->isSMTP();
        $mail->Host       = Arr::get($gateway->meta_data, 'host');
        $mail->Port       = (int) Arr::get($gateway->meta_data, 'port', 465);
        $mail->SMTPAuth   = true;
        $mail->Username   = Arr::get($gateway->meta_data, 'username');
        $mail->Password   = Arr::get($gateway->meta_data, 'password');
        $mail->Timeout    = 30;
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;

        $encryption = Arr::get($gateway->meta_data, 'encryption');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif (in_array($encryption, ['tls', 'starttls'])) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer'       => false,
                'verify_peer_name'  => false,
                'allow_self_signed' => true,
            ],
        ];

        $mail->setFrom($gateway->address, $fromName ?: $gateway->name);
        $mail->addAddress($to);
        $mail->addReplyTo($replyTo ?: $gateway->address);

        // RFC 8058: List-Unsubscribe headers (required by Gmail/Yahoo since 2024)
        if ($unsubscribeLink) {
            $mail->addCustomHeader('List-Unsubscribe', '<' . $unsubscribeLink . '>, <mailto:' . $gateway->address . '?subject=unsubscribe>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

        $mail->send();
        return true;
    }

    /**
     * Send email via MailJet API
     */
    protected function sendViaMailjet(Gateway $gateway, string $to, string $subject, string $body, ?string $unsubscribeLink = null, ?string $fromName = null, ?string $replyTo = null): bool
    {
        $apiKey = Arr::get($gateway->meta_data, 'api_key');
        $secretKey = Arr::get($gateway->meta_data, 'secret_key');

        $client = new \GuzzleHttp\Client(['base_uri' => 'https://api.mailjet.com/v3.1/']);

        $messageData = [
            'From' => [
                'Email' => $gateway->address,
                'Name'  => $fromName ?: $gateway->name,
            ],
            'To' => [[
                'Email' => $to,
                'Name'  => explode('@', $to)[0],
            ]],
            'Subject'  => $subject,
            'TextPart' => strip_tags($body),
            'HTMLPart' => $body,
        ];

        if ($replyTo) {
            $messageData['ReplyTo'] = ['Email' => $replyTo];
        }

        // Mailjet supports List-Unsubscribe via Headers property
        if ($unsubscribeLink) {
            $messageData['Headers'] = [
                'List-Unsubscribe' => '<' . $unsubscribeLink . '>, <mailto:' . $gateway->address . '?subject=unsubscribe>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ];
        }

        $response = $client->request('POST', 'send', [
            'json' => ['Messages' => [$messageData]],
            'auth' => [$apiKey, $secretKey],
        ]);

        if ($response->getStatusCode() == 200) {
            $result = json_decode($response->getBody());
            return ($result->Messages[0]->Status ?? '') === 'success';
        }

        return false;
    }

    /**
     * Send email via SendGrid API
     */
    protected function sendViaSendgrid(Gateway $gateway, string $to, string $subject, string $body, ?string $unsubscribeLink = null, ?string $fromName = null, ?string $replyTo = null): bool
    {
        $apiKey = Arr::get($gateway->meta_data, 'secret_key');

        $email = new \SendGrid\Mail\Mail();
        $email->setFrom($gateway->address, $fromName ?: $gateway->name);
        $email->addTo($to);
        $email->setSubject($subject);
        $email->addContent("text/html", $body);

        if ($replyTo) {
            $email->setReplyTo($replyTo);
        }

        // SendGrid supports List-Unsubscribe via headers
        if ($unsubscribeLink) {
            $email->addHeader('List-Unsubscribe', '<' . $unsubscribeLink . '>, <mailto:' . $gateway->address . '?subject=unsubscribe>');
            $email->addHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        }

        $sendgrid = new \SendGrid($apiKey);
        $response = $sendgrid->send($email);

        return in_array($response->statusCode(), [200, 201, 202]);
    }

    /**
     * Send email via Amazon SES
     */
    protected function sendViaSes(Gateway $gateway, string $to, string $subject, string $body, ?string $unsubscribeLink = null, ?string $fromName = null, ?string $replyTo = null): bool
    {
        $SesClient = new \Aws\Ses\SesClient([
            'profile' => Arr::get($gateway->meta_data, 'profile'),
            'version' => Arr::get($gateway->meta_data, 'version', '2010-12-01'),
            'region'  => Arr::get($gateway->meta_data, 'region'),
        ]);

        // SES "Source" is the displayed From — RFC 5322 allows "Name <addr>" syntax.
        $source = $fromName
            ? sprintf('%s <%s>', addslashes($fromName), $gateway->address)
            : $gateway->address;

        $SesClient->sendEmail([
            'Destination' => [
                'ToAddresses' => [$to],
            ],
            'ReplyToAddresses' => [$replyTo ?: $gateway->address],
            'Source' => $source,
            'Message' => [
                'Body' => [
                    'Html' => [
                        'Charset' => 'UTF-8',
                        'Data'    => $body,
                    ],
                ],
                'Subject' => [
                    'Charset' => 'UTF-8',
                    'Data'    => $subject,
                ],
            ],
        ]);

        return true;
    }

    /**
     * Send email via Mailgun API
     */
    protected function sendViaMailgun(Gateway $gateway, string $to, string $subject, string $body, ?string $unsubscribeLink = null, ?string $fromName = null, ?string $replyTo = null): bool
    {
        $secretKey = Arr::get($gateway->meta_data, 'secret_key');
        $domain = Arr::get($gateway->meta_data, 'verified_domain');

        $mailGun = \Mailgun\Mailgun::create($secretKey);

        $params = [
            'from'    => $fromName
                ? sprintf('%s <%s>', addslashes($fromName), $gateway->address)
                : $gateway->address,
            'to'      => $to,
            'subject' => $subject,
            'html'    => $body,
        ];

        if ($replyTo) {
            $params['h:Reply-To'] = $replyTo;
        }

        // Mailgun supports List-Unsubscribe via h: prefix headers
        if ($unsubscribeLink) {
            $params['h:List-Unsubscribe'] = '<' . $unsubscribeLink . '>, <mailto:' . $gateway->address . '?subject=unsubscribe>';
            $params['h:List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        $mailGun->messages()->send($domain, $params);

        return true;
    }

    // ============ SMS SENDING ============

    /**
     * Send SMS using the existing SendSMS utility
     */
    protected function sendSms(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content
    ): bool {
        $phone = $contact->sms_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No phone number');
            return false;
        }

        try {
            // Use the existing SendSMS utility with an empty collection
            // to avoid DispatchLog dependency. The empty collection ensures
            // Dispatchable trait methods are no-ops.
            $smsSender = new \App\Http\Utility\SendSMS();
            $result = $smsSender->send(
                provider: $gateway->type,
                to: $phone,
                gateway: $gateway,
                dispatchLog: collect(),
                message: $content
            );

            return $result;
        } catch (\Exception $e) {
            Log::error("Campaign SMS send failed: " . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'gateway_type' => $gateway->type,
                'to' => $phone,
            ]);
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Queue an SMS for an Android device to pull. Inserts a row into the legacy
     * dispatch_logs table (polymorphic gatewayable_type = AndroidSim) that the
     * existing /api/logs/sms/pending endpoint already understands. The campaign
     * dispatch is marked QUEUED — MirrorAndroidSmsStatus mirrors device status
     * updates back into campaign_dispatches.
     *
     * Returns 'queued' (string sentinel) on success so processDispatch knows the
     * send is async and should NOT mark the row as SENT yet.
     */
    protected function queueSmsForAndroid(
        CampaignDispatch $dispatch,
        Contact $contact,
        string $content
    ): bool|string {
        $phone = $contact->sms_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No phone number');
            return false;
        }

        try {
            $simId = $dispatch->android_sim_id;
            $sim = \App\Models\AndroidSim::with('androidSession')->find($simId);
            if (!$sim || !$sim->androidSession) {
                $dispatch->markAsFailed('Android SIM or session no longer exists');
                return false;
            }

            $log = \App\Models\DispatchLog::create([
                'user_id'           => $sim->user_id,
                'message_id'        => null, // unified — content lives in meta_data instead
                'contact_id'        => $contact->id,
                'campaign_id'       => null, // intentionally null: campaign_id FKs the legacy campaigns table, not unified_campaigns
                'type'              => \App\Enums\System\ChannelTypeEnum::SMS->value,
                'gatewayable_id'    => $sim->id,
                'gatewayable_type' => \App\Models\AndroidSim::class,
                'priority'          => \App\Enums\System\PriorityEnum::MEDIUM->value,
                'status'            => \App\Enums\System\CommunicationStatusEnum::PENDING->value,
                'scheduled_at'      => $dispatch->scheduled_at ?? now(),
                'meta_data'         => [
                    'unified_dispatch_id' => $dispatch->id,
                    'unified_campaign_id' => $dispatch->campaign_id,
                    'recipient'           => $phone,
                    'message'             => $content,
                ],
            ]);

            $dispatch->update([
                'status'          => DispatchStatus::QUEUED,
                'dispatch_log_id' => $log->id,
            ]);

            return 'queued';
        } catch (\Exception $e) {
            Log::error('Failed to queue Android SMS dispatch: ' . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'sim_id'      => $dispatch->android_sim_id,
            ]);
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    // ============ WHATSAPP SENDING ============

    /**
     * Send WhatsApp message using the existing SendWhatsapp utility
     */
    protected function sendWhatsApp(
        CampaignDispatch $dispatch,
        Gateway $gateway,
        Contact $contact,
        string $content,
        CampaignMessage $message
    ): bool {
        $phone = $contact->whatsapp_contact;

        if (empty($phone)) {
            $dispatch->markAsFailed('No WhatsApp number');
            return false;
        }

        try {
            $whatsappSender = new \App\Http\Utility\SendWhatsapp();

            // Determine if Cloud API or Node-based
            if ($gateway->isCloudApi()) {
                return $this->sendWhatsAppCloudApi($gateway, $phone, $content, $message, $dispatch);
            } else {
                return $this->sendWhatsAppNode($gateway, $phone, $content, $dispatch);
            }
        } catch (\Exception $e) {
            Log::error("Campaign WhatsApp send failed: " . $e->getMessage(), [
                'dispatch_id' => $dispatch->id,
                'gateway_type' => $gateway->type,
                'to' => $phone,
            ]);
            $dispatch->markAsFailed($e->getMessage());
            return false;
        }
    }

    /**
     * Send WhatsApp message via Cloud API (Meta Business API)
     */
    protected function sendWhatsAppCloudApi(Gateway $gateway, string $phone, string $content, CampaignMessage $message, CampaignDispatch $dispatch): bool
    {
        $accessToken = $gateway->getAccessToken();
        $phoneNumberId = $gateway->phone_number_id;
        $apiVersion = $gateway->api_version ?? \App\Enums\MetaApiEndpoints::CURRENT_VERSION;

        if (!$accessToken || !$phoneNumberId) {
            $dispatch->markAsFailed('Missing Cloud API credentials (access token or phone number ID)');
            return false;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $content],
        ];

        // Use template if available
        if ($message->template_id) {
            $template = $gateway->templates()->find($message->template_id);
            if ($template) {
                $payload = [
                    'messaging_product' => 'whatsapp',
                    'to' => $phone,
                    'type' => 'template',
                    'template' => [
                        'name' => $template->name,
                        'language' => ['code' => $template->language ?? 'en'],
                    ],
                ];
            }
        }

        $response = \Illuminate\Support\Facades\Http::withToken($accessToken)
            ->post("https://graph.facebook.com/{$apiVersion}/{$phoneNumberId}/messages", $payload);

        if ($response->successful()) {
            $data = $response->json();
            $dispatch->addMetadata('message_id', $data['messages'][0]['id'] ?? null);
            return true;
        }

        $error = $response->json('error.message', 'Cloud API request failed');
        $dispatch->markAsFailed($error);
        return false;
    }

    /**
     * Send WhatsApp message via Node service (self-hosted xsender-whatsapp-service).
     *
     * Every part of this used to be wrong in v4.7.0:
     *   - Endpoint was `/api/send-message` — the real route is `/messages/send`
     *     (see xsender-whatsapp-service/src/routes/messages.route.js).
     *   - Payload used `session_id` / `to` / `text` — the Node schema is
     *     `sessionId` / `receiver` / `message.text`.
     *   - No `X-API-Key` header — auth middleware rejected every request.
     *   - `env('WHATSAPP_NODE_URL')` was the wrong key; deployed env uses
     *     `WP_SERVER_URL` (same key SendWhatsappConversationMessage uses).
     *   - sessionId guess fell back to a human "name" that never matches a
     *     real Node session id (sessions are keyed by gateway.name in this app).
     * Delegating to NodeService::sendTextMessage fixes all five in one call.
     */
    protected function sendWhatsAppNode(Gateway $gateway, string $phone, string $content, CampaignDispatch $dispatch): bool
    {
        try {
            $nodeService = app(\App\Services\System\Communication\NodeService::class);
            $sessionId   = $gateway->name; // matches SendWhatsappConversationMessage convention

            if (empty($sessionId)) {
                $dispatch->markAsFailed('WhatsApp Node gateway has no session id (empty gateway name)');
                return false;
            }

            $response = $nodeService->sendTextMessage($sessionId, $phone, $content);

            if ($response->successful()) {
                $data = $response->json();
                $dispatch->addMetadata('message_id', $data['data']['key']['id'] ?? ($data['message_id'] ?? null));
                return true;
            }

            $error = $response->json('error') ?? $response->json('message') ?? ('HTTP ' . $response->status());
            $dispatch->markAsFailed('WhatsApp Node send failed: ' . (is_array($error) ? json_encode($error) : (string) $error));
            return false;
        } catch (\Throwable $e) {
            $dispatch->markAsFailed('WhatsApp Node exception: ' . $e->getMessage());
            return false;
        }
    }

    // ============ UNSUBSCRIBE (SPAM COMPLIANCE) ============

    /**
     * Generate encrypted unsubscribe link for a campaign contact
     */
    protected function generateUnsubscribeLink(int $campaignId, string $contactUid): string
    {
        return route('unsubscribe', [
            'campaign_id' => encrypt($campaignId),
            'contact_id'  => encrypt($contactUid),
            'channel'     => 'email',
        ]);
    }

    /**
     * Inject unsubscribe link into email content
     *
     * 1. Replaces {{unsubscribe_link}} placeholder (and URL-encoded variant) with actual link
     * 2. If no placeholder found, auto-appends a compliant unsubscribe footer
     *
     * This ensures CAN-SPAM / GDPR compliance on every campaign email,
     * even if the user forgets to add the placeholder.
     */
    protected function injectUnsubscribeLink(string $content, string $unsubscribeLink): string
    {
        $hasPlaceholder = str_contains($content, '{{unsubscribe_link}}')
            || str_contains($content, '%7B%7Bunsubscribe_link%7D%7D');

        if ($hasPlaceholder) {
            // Replace both raw and URL-encoded placeholders
            $content = str_replace(
                ['{{unsubscribe_link}}', '%7B%7Bunsubscribe_link%7D%7D'],
                $unsubscribeLink,
                $content
            );
        } else {
            // Auto-append unsubscribe footer (user didn't add placeholder)
            $footer = '<div style="text-align:center;padding:20px 0 10px;margin-top:20px;border-top:1px solid #e0e0e0;font-size:12px;color:#999;">'
                . '<p style="margin:0;">If you no longer wish to receive these emails, '
                . '<a href="' . $unsubscribeLink . '" style="color:#666;text-decoration:underline;">unsubscribe here</a>.</p>'
                . '</div>';

            // Insert before </body> if exists, otherwise append
            if (stripos($content, '</body>') !== false) {
                $content = str_ireplace('</body>', $footer . '</body>', $content);
            } else {
                $content .= $footer;
            }
        }

        return $content;
    }

    // ============ STATS & COMPLETION ============

    /**
     * Update campaign stats
     */
    protected function updateCampaignStats(UnifiedCampaign $campaign, string $channel, string $stat): void
    {
        $campaign->updateChannelStats($channel, [$stat => 1]);
        $campaign->incrementProcessed();

        // Check if campaign is complete
        $this->checkCampaignCompletion($campaign);
    }

    /**
     * Check if campaign is complete
     */
    protected function checkCampaignCompletion(UnifiedCampaign $campaign): void
    {
        $pending = $campaign->dispatches()
            ->whereIn('status', [DispatchStatus::PENDING, DispatchStatus::QUEUED, DispatchStatus::PROCESSING])
            ->count();

        if ($pending === 0 && $campaign->status === UnifiedCampaignStatus::RUNNING) {
            $campaign->markAsCompleted();
        }
    }

    /**
     * Process batch of dispatches
     */
    public function processBatch(int $limit = 100): array
    {
        $dispatches = CampaignDispatch::readyToProcess()
            ->with(['campaign', 'campaignMessage', 'contact', 'gateway'])
            ->whereHas('campaign', function ($q) {
                $q->where('status', UnifiedCampaignStatus::RUNNING);
            })
            ->limit($limit)
            ->get();

        $processed = 0;
        $succeeded = 0;
        $failed = 0;

        foreach ($dispatches as $dispatch) {
            $processed++;

            if ($this->processDispatch($dispatch)) {
                $succeeded++;
            } else {
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'succeeded' => $succeeded,
            'failed' => $failed,
        ];
    }

    /**
     * Retry failed dispatches
     */
    public function retryFailed(UnifiedCampaign $campaign, int $maxRetries = 3): int
    {
        $retryable = $campaign->dispatches()
            ->retryable($maxRetries)
            ->get();

        $count = 0;

        foreach ($retryable as $dispatch) {
            $dispatch->incrementRetry();
            $count++;
        }

        return $count;
    }

    /**
     * Handle delivery status callback
     */
    public function handleDeliveryStatus(string $messageId, string $status, ?string $error = null): bool
    {
        $dispatch = CampaignDispatch::where('meta_data->message_id', $messageId)->first();

        if (!$dispatch) {
            return false;
        }

        switch (strtolower($status)) {
            case 'delivered':
                $dispatch->markAsDelivered();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'delivered');
                break;

            case 'read':
            case 'opened':
                $dispatch->markAsOpened();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'opened');
                break;

            case 'clicked':
                $dispatch->markAsClicked();
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'clicked');
                break;

            case 'replied':
                $dispatch->markAsReplied();
                break;

            case 'failed':
            case 'undelivered':
                $dispatch->markAsFailed($error);
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                break;

            case 'bounced':
                $dispatch->markAsBounced($error);
                $this->updateCampaignStats($dispatch->campaign, $dispatch->channel->value, 'failed');
                break;
        }

        return true;
    }

    /**
     * Get dispatch rate statistics for a time period
     */
    public function getDispatchRateStats(UnifiedCampaign $campaign, string $period = 'hour'): array
    {
        $groupBy = match ($period) {
            'minute' => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:%i')",
            'hour' => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
            'day' => "DATE(sent_at)",
            default => "DATE_FORMAT(sent_at, '%Y-%m-%d %H:00')",
        };

        return $campaign->dispatches()
            ->whereNotNull('sent_at')
            ->selectRaw("$groupBy as period, COUNT(*) as count")
            ->groupByRaw($groupBy)
            ->orderBy('period')
            ->pluck('count', 'period')
            ->toArray();
    }
}
