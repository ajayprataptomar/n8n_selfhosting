<?php

namespace App\Jobs;

use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\ConversationMessageStatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\Gateway;
use App\Models\MessageStatus;
use App\Services\System\PhoneNumberService;
use App\Traits\MetaApiTrait;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class SendWhatsappConversationMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MetaApiTrait;

    protected $messageStatus;
    protected $gateway;
    protected $to;
    protected $mediaUrl;
    protected $mediaType;

    public function __construct(
        string $to,
        MessageStatus|null $messageStatus = null,
        Gateway|null $gateway = null,
        ?string $mediaUrl = null,
        ?string $mediaType = null
    ) {
        $this->messageStatus    = $messageStatus;
        $this->gateway          = $gateway;
        $this->to               = $to;
        $this->mediaUrl         = $mediaUrl;
        $this->mediaType        = $mediaType;

        // Note: Chat messages use dispatchSync() for immediate delivery
        // This queue setting only applies if dispatch() is used instead
        $this->onQueue(config('queue.pipes.chat.whatsapp', 'default'));
    }

    /**
     * Summary of handle
     * @return void
     */
    public function handle()
    {
        try {

            if(!$this->gateway || !$this->messageStatus) return;
            $this->messageStatus = $this->messageStatus->load(["message"]);
            if(!$this->messageStatus->message) return;

            $message = $this->messageStatus->message;

            // Check gateway type - Node or Cloud API
            if ($this->gateway->type === WhatsAppGatewayTypeEnum::NODE->value) {
                $this->sendViaNodeService($message);
            } else {
                $this->sendViaCloudApi($message);
            }
        } catch (Exception $e) {
            $this->updateMessageStatus(status: ConversationMessageStatusEnum::FAILED, response: null, errorMessage: $e->getMessage());
        }
    }

    /**
     * Send message via Node WhatsApp Service
     */
    private function sendViaNodeService($message)
    {
        $apiURL = env('WP_SERVER_URL') . '/messages/send';

        // Use PhoneNumberService for professional phone number handling
        // Supports all formats: +880xxx, 880xxx, 0xxx, with spaces, dashes, etc.
        $phoneResult = PhoneNumberService::prepareForSending($this->to, $this->gateway, false);

        if (!$phoneResult['success']) {
            $this->updateMessageStatus(
                status: ConversationMessageStatusEnum::FAILED,
                response: null,
                errorMessage: $phoneResult['error'] ?? 'Invalid phone number format'
            );
            return;
        }

        $receiver = $phoneResult['formatted'];

        // Replace variables in message
        $messageText = $message->message ? $this->replaceMessageVariables($message->message, $receiver) : '';

        // Build message payload based on content type
        $postInput = [
            'sessionId' => $this->gateway->name,
            'receiver'  => $receiver,
        ];

        // Handle media if present
        if ($this->mediaUrl) {
            $mediaMessage = [];

            // Determine media type from URL if not specified
            $detectedType = $this->mediaType ?? $this->detectMediaType($this->mediaUrl);

            switch ($detectedType) {
                case 'image':
                    $mediaMessage['image'] = [
                        'url' => $this->mediaUrl,
                        'caption' => $messageText
                    ];
                    break;
                case 'video':
                    $mediaMessage['video'] = [
                        'url' => $this->mediaUrl,
                        'caption' => $messageText
                    ];
                    break;
                case 'audio':
                    $mediaMessage['audio'] = [
                        'url' => $this->mediaUrl
                    ];
                    // Audio doesn't support caption, send text separately if needed
                    if ($messageText) {
                        $postInput['message'] = ['text' => $messageText];
                    }
                    break;
                case 'document':
                default:
                    $mediaMessage['document'] = [
                        'url' => $this->mediaUrl,
                        'caption' => $messageText,
                        'filename' => basename(parse_url($this->mediaUrl, PHP_URL_PATH))
                    ];
                    break;
            }

            $postInput['message'] = $mediaMessage;
        } else {
            // Text only message
            $postInput['message'] = ['text' => $messageText];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-API-Key'    => env('WP_API_KEY', ''),
        ];

        $response = Http::timeout(30)->withoutVerifying()->withHeaders($headers)->post($apiURL, $postInput);

        if ($response->status() === 200) {
            $res = json_decode($response->body(), true);
            if (Arr::get($res, 'success')) {
                $this->updateMessageStatus(
                    status: ConversationMessageStatusEnum::SENT,
                    response: $res,
                    phoneNumberId: $this->gateway->name
                );
            } else {
                $this->updateMessageStatus(
                    status: ConversationMessageStatusEnum::FAILED,
                    response: $res,
                    errorMessage: Arr::get($res, 'message', 'Unknown error')
                );
            }
        } else {
            $this->updateMessageStatus(
                status: ConversationMessageStatusEnum::FAILED,
                response: null,
                errorMessage: 'Failed to connect to Node service'
            );
        }
    }

    /**
     * Detect media type from URL
     */
    private function detectMediaType(string $url): string
    {
        $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $videoExtensions = ['mp4', '3gp', 'mkv', 'avi'];
        $audioExtensions = ['mp3', 'ogg', 'amr', 'wav', 'm4a', 'aac'];
        $documentExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];

        if (in_array($extension, $imageExtensions)) {
            return 'image';
        } elseif (in_array($extension, $videoExtensions)) {
            return 'video';
        } elseif (in_array($extension, $audioExtensions)) {
            return 'audio';
        }

        return 'document';
    }

    /**
     * Send message via Meta Cloud API
     */
    private function sendViaCloudApi($message)
    {
        $phoneNumberId  = Arr::get($this->gateway->meta_data, "phone_number_id", "");
        $accessToken    = Arr::get($this->gateway->meta_data, "user_access_token", "");
        $params = [
            'access_token'      => $accessToken,
            'messaging_product' => 'whatsapp',
            'to'                => $this->to,
            'type'              => 'text',
            'text'              => ['body' => $message->message],
        ];

        $data = $this->makeMetaApiRequest("$phoneNumberId/messages", $params, "post");
        $response = Arr::get($data, "data");

        if (Arr::get($data, "success")) {
            $this->updateMessageStatus(status: ConversationMessageStatusEnum::SENT, response: $response, phoneNumberId: $phoneNumberId);
        } else {
            $this->updateMessageStatus(status: ConversationMessageStatusEnum::FAILED, response: $data);
        }
    }

    /**
     * Replace variables in message with WhatsApp contact data
     * Falls back to WhatsApp profile name if needed
     *
     * @param string $messageText
     * @param string $receiver
     * @return string
     */
    private function replaceMessageVariables(string $messageText, string $receiver): string
    {
        // Build replacement map
        $variables = [
            'phone' => $receiver,
            'name' => $receiver // Default to phone number
        ];

        // Try to get contact name from database first
        try {
            // Try multiple phone formats
            $phoneVariations = [
                $receiver,
                '+' . $receiver,
                ltrim($receiver, '+'),
            ];

            $contact = \App\Models\Contact::where(function($query) use ($phoneVariations) {
                foreach ($phoneVariations as $phone) {
                    $query->orWhere('whatsapp_contact', 'LIKE', '%' . $phone . '%')
                          ->orWhere('phone_number', 'LIKE', '%' . $phone . '%');
                }
            })->first();

            if ($contact && !empty($contact->name)) {
                $variables['name'] = $contact->name;
            } elseif ($contact && (!empty($contact->first_name) || !empty($contact->last_name))) {
                $variables['name'] = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            }
        } catch (Exception $e) {
            // Silently fail and use default values
        }

        // Replace both {{variable}} and {variable} patterns
        $message = $messageText;
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get WhatsApp profile name from Node service
     *
     * @param string $receiver
     * @return string|null
     */
    private function getWhatsAppProfileName(string $receiver): ?string
    {
        try {
            $apiURL = env('WP_SERVER_URL') . '/messages/get-contact';

            $postInput = [
                'sessionId' => $this->gateway->name,
                'number'    => $receiver
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'X-API-Key'    => env('WP_API_KEY', ''),
            ];

            $response = Http::timeout(5)
                           ->withoutVerifying()
                           ->withHeaders($headers)
                           ->post($apiURL, $postInput);

            if ($response && $response->status() === 200) {
                $res = json_decode($response->body(), true);

                if (Arr::get($res, 'success') === true) {
                    $contactData = Arr::get($res, 'data', []);
                    return Arr::get($contactData, 'pushname')
                        ?? Arr::get($contactData, 'notify')
                        ?? Arr::get($contactData, 'name')
                        ?? null;
                }
            }

            return null;

        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Summary of updateMessageStatus
     * @param \App\Enums\System\ConversationMessageStatusEnum $status
     * @param array|null $response
     * @param string|null $phoneNumberId
     * @param string|null $errorMessage
     * @return void
     */
    private function updateMessageStatus(ConversationMessageStatusEnum $status, array|null $response = null, string|null $phoneNumberId = null, string|null $errorMessage = null) {

        // Extract WhatsApp message ID from response
        // Cloud API format: messages.0.id
        // Node API format: data.messageId
        $whatsappMessageId  = Arr::get($response, "messages.0.id")
                              ?? Arr::get($response, "data.messageId")
                              ?? null;

        $additionalData     =  ConversationMessageStatusEnum::SENT && $phoneNumberId
                                ? ['phone_number_id' => $phoneNumberId]
                                : $errorMessage ?? Arr::get($response, "error", []);

        $this->messageStatus->additional_data     = $additionalData;
        $this->messageStatus->whatsapp_message_id = $whatsappMessageId;
        $this->messageStatus->status              = $status->value;
        $this->messageStatus->status_timestamp    = Carbon::now();
        $this->messageStatus->save();
    }
}
