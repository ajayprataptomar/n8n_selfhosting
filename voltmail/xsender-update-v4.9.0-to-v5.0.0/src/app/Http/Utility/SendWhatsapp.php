<?php
namespace App\Http\Utility;

use App\Enums\CommunicationStatusEnum;
use App\Enums\ServiceType;
use App\Enums\System\CommunicationStatusEnum as SystemCommunicationStatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\CampaignContact;
use App\Models\CommunicationLog;
use App\Models\DispatchLog;
use App\Models\Gateway;
use App\Models\Message;
use App\Models\Template;
use App\Models\User;
use App\Models\WhatsappCreditLog;
use App\Models\WhatsappDevice;
use App\Service\Admin\Core\CustomerService;
use App\Services\System\PhoneNumberService;
use App\Traits\Dispatchable;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWhatsapp
{
    use Dispatchable;

    /**
     * Track message count per gateway for delay calculation
     * @var array
     */
    private static array $messageCounters = [];

    /**
     * Track recipients we've messaged per gateway (for new-chat detection)
     * @var array
     */
    private static array $knownRecipients = [];

    /**
     * Track gateway activation timestamps for warm-up enforcement
     * @var array
     */
    private static array $gatewayWarmup = [];

    /**
     * send
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|Collection|DispatchLog $dispatchLog
     * @param Message $message
     * @param string $body
     *
     * @return bool
     */
    public function send(Gateway $gateway, array|string $to, array|Collection|DispatchLog $dispatchLog, Message $message, string $body): bool
    {
        $body = textSpinner($body);
        return $this->sendWithHandler($gateway, $to,$dispatchLog, $message, $body);
    }

    /**
     * Enterprise anti-ban delay calculation with Gaussian jitter, rate limiting,
     * batch cooldowns, new-chat detection, and warm-up enforcement.
     *
     * Techniques based on market research of WASender, Pro Sender, baileys-antiban,
     * and WhatsApp Web Ultimate — combined into a unified protection system.
     *
     * @param Gateway $gateway
     * @param string|null $receiver  Recipient number (for new-chat detection)
     * @return int Delay in milliseconds
     */
    private function calculateAntiBanDelay(Gateway $gateway, ?string $receiver = null): int
    {
        $gatewayId = $gateway->id;

        // Initialize counter for this gateway
        if (!isset(self::$messageCounters[$gatewayId])) {
            self::$messageCounters[$gatewayId] = [
                'count'          => 0,
                'hourly_count'   => 0,
                'daily_count'    => 0,
                'minute_count'   => 0,
                'last_reset'     => time(),
                'last_hour'      => time(),
                'last_day'       => time(),
                'last_minute'    => time(),
            ];
        }

        $counter = &self::$messageCounters[$gatewayId];
        $now = time();

        // Reset per-minute counter every 60 seconds
        if ($now - $counter['last_minute'] >= 60) {
            $counter['minute_count'] = 0;
            $counter['last_minute'] = $now;
        }

        // Reset hourly counter
        if ($now - $counter['last_hour'] >= 3600) {
            $counter['hourly_count'] = 0;
            $counter['last_hour'] = $now;
        }

        // Reset daily counter
        if ($now - $counter['last_day'] >= 86400) {
            $counter['daily_count'] = 0;
            $counter['last_day'] = $now;
        }

        $counter['count']++;
        $counter['minute_count']++;
        $counter['hourly_count']++;
        $counter['daily_count']++;

        // Get gateway delay settings (with enterprise defaults)
        $minDelay        = (float) ($gateway->per_message_min_delay ?? 2);    // seconds
        $maxDelay        = (float) ($gateway->per_message_max_delay ?? 5);    // seconds
        $delayAfterCount = (int)   ($gateway->delay_after_count ?? 50);
        $delayAfterDuration = (float) ($gateway->delay_after_duration ?? 30); // seconds
        $resetAfterCount = (int)   ($gateway->reset_after_count ?? 200);

        // ── 1. Gaussian jitter delay (more natural than uniform random) ──
        // Box-Muller transform: generates normal distribution centered between min/max
        $mean   = ($minDelay + $maxDelay) / 2;
        $stddev = ($maxDelay - $minDelay) / 4; // 95% of values fall within min-max
        $u1 = max(0.0001, (float) mt_rand() / mt_getrandmax());
        $u2 = (float) mt_rand() / mt_getrandmax();
        $gaussian = $mean + $stddev * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        // Clamp to reasonable bounds (min * 0.5 to max * 1.5)
        $gaussian = max($minDelay * 0.5, min($maxDelay * 1.5, $gaussian));
        $baseDelayMs = (int) ($gaussian * 1000);

        // ── 2. Batch cooldown: extended pause after every N messages ──
        if ($delayAfterCount > 0 && $counter['count'] % $delayAfterCount === 0) {
            $extendedDelayMs = (int) ($delayAfterDuration * 1000);
            // Add jitter to cooldown too (±20%)
            $jitter = (int) ($extendedDelayMs * (0.8 + (mt_rand() / mt_getrandmax()) * 0.4));
            $baseDelayMs += $jitter;

            Log::info("WhatsApp anti-ban: Batch cooldown", [
                'gateway_id'    => $gatewayId,
                'message_count' => $counter['count'],
                'cooldown_ms'   => $jitter,
                'total_delay'   => $baseDelayMs,
            ]);
        }

        // ── 3. Per-minute rate limiting (max 8 msgs/min recommended) ──
        $maxPerMinute = 8;
        if ($counter['minute_count'] > $maxPerMinute) {
            // Slow down significantly — wait remaining time in the minute
            $remainingInMinute = 60 - ($now - $counter['last_minute']);
            $rateLimitDelay = max(5000, $remainingInMinute * 1000);
            $baseDelayMs += $rateLimitDelay;

            Log::warning("WhatsApp anti-ban: Per-minute rate limit hit", [
                'gateway_id'    => $gatewayId,
                'minute_count'  => $counter['minute_count'],
                'extra_delay'   => $rateLimitDelay,
            ]);
        }

        // ── 4. Hourly rate limiting (max 200 msgs/hour) ──
        $maxPerHour = 200;
        if ($counter['hourly_count'] > $maxPerHour) {
            $baseDelayMs += 15000; // 15s extra delay to slow throughput

            Log::warning("WhatsApp anti-ban: Hourly rate limit hit", [
                'gateway_id'    => $gatewayId,
                'hourly_count'  => $counter['hourly_count'],
            ]);
        }

        // ── 5. Daily soft limit warning (1500 msgs/day) ──
        $maxPerDay = 1500;
        if ($counter['daily_count'] > $maxPerDay) {
            $baseDelayMs += 10000; // 10s extra delay

            Log::warning("WhatsApp anti-ban: Daily rate limit hit — risk of ban increases", [
                'gateway_id'  => $gatewayId,
                'daily_count' => $counter['daily_count'],
            ]);
        }

        // ── 6. New-chat detection: first message to unknown recipient gets extra delay ──
        if ($receiver) {
            if (!isset(self::$knownRecipients[$gatewayId])) {
                self::$knownRecipients[$gatewayId] = [];
            }

            if (!isset(self::$knownRecipients[$gatewayId][$receiver])) {
                // First message to this number in this session — add 2-5s extra delay
                $newChatDelay = rand(2000, 5000);
                $baseDelayMs += $newChatDelay;
                self::$knownRecipients[$gatewayId][$receiver] = time();

                Log::debug("WhatsApp anti-ban: New chat delay applied", [
                    'gateway_id' => $gatewayId,
                    'receiver'   => substr($receiver, 0, 6) . '***',
                    'extra_delay' => $newChatDelay,
                ]);
            }

            // Limit tracked recipients to 10,000 to prevent memory bloat
            if (count(self::$knownRecipients[$gatewayId]) > 10000) {
                self::$knownRecipients[$gatewayId] = array_slice(
                    self::$knownRecipients[$gatewayId], -5000, null, true
                );
            }
        }

        // ── 7. Warm-up enforcement for new/recently connected gateways ──
        // New accounts should start slow: Day 1 = 20 msgs, growing 1.8x per day over 7 days
        $warmupKey = "whatsapp_warmup_{$gatewayId}";
        $activatedAt = \Illuminate\Support\Facades\Cache::get($warmupKey);

        if ($activatedAt === null) {
            // First time seeing this gateway — set activation timestamp
            \Illuminate\Support\Facades\Cache::put($warmupKey, now()->timestamp, 60 * 60 * 24 * 30); // 30 days
            $activatedAt = now()->timestamp;
        }

        $daysSinceActivation = max(1, (int) ceil(($now - $activatedAt) / 86400));

        if ($daysSinceActivation <= 7) {
            // Warm-up period: daily limit = 20 * 1.8^(day-1)
            // Day 1: 20, Day 2: 36, Day 3: 65, Day 4: 116, Day 5: 210, Day 6: 377, Day 7: 680
            $warmupDailyLimit = (int) (20 * pow(1.8, $daysSinceActivation - 1));

            if ($counter['daily_count'] > $warmupDailyLimit) {
                // During warm-up, severely slow down beyond the limit
                $baseDelayMs += 30000; // 30s extra

                Log::warning("WhatsApp anti-ban: Warm-up daily limit exceeded", [
                    'gateway_id' => $gatewayId,
                    'day'        => $daysSinceActivation,
                    'limit'      => $warmupDailyLimit,
                    'sent'       => $counter['daily_count'],
                ]);
            }

            // During warm-up, also enforce slower base delays
            $warmupMultiplier = 1 + (7 - $daysSinceActivation) * 0.15; // Day 1: 1.9x, Day 7: 1.0x
            $baseDelayMs = (int) ($baseDelayMs * $warmupMultiplier);
        }

        // ── 8. Reset counter if reached reset threshold ──
        if ($resetAfterCount > 0 && $counter['count'] >= $resetAfterCount) {
            $counter['count'] = 0;
            $counter['last_reset'] = $now;

            Log::info("WhatsApp anti-ban: Counter reset", [
                'gateway_id'       => $gatewayId,
                'reset_after_count' => $resetAfterCount,
            ]);
        }

        return $baseDelayMs;
    }

    /**
     * sendWithHandler
     *
     * @param Gateway $gateway
     * @param array|string $to
     * @param array|Collection|DispatchLog $dispatchLog
     * @param Message $message
     * @param string $body
     * 
     * @return bool
     */
    public function sendWithHandler(Gateway $gateway, array|string $to, array|Collection|DispatchLog $dispatchLog, Message $message, string $body): bool
    {
        try {
            $success = false;
            if($gateway->type == WhatsAppGatewayTypeEnum::NODE->value) {
                
                $success = $this->sendNodeMessages($dispatchLog, $gateway, $message, $body, $to);
            } elseif ($gateway->type == WhatsAppGatewayTypeEnum::CLOUD->value) {
    
                $success = $this->sendCloudApiMessages($dispatchLog, $gateway, $message, $body, $to);
            }
            if ($success && $dispatchLog) {
                $this->markAsDelivered($dispatchLog);
            }
            return $success;
        } catch (Exception $e) {
            // Invalidate session cache on failure so next attempt re-validates
            if ($gateway->type == WhatsAppGatewayTypeEnum::NODE->value) {
                \Illuminate\Support\Facades\Cache::forget("whatsapp_session_valid_{$gateway->id}");
            }
            $this->fail($dispatchLog, $e->getMessage());
            return false;
        }
    }

    /**
     * Replace variables in message with contact data
     * Falls back to WhatsApp profile name if contact name is not in database
     *
     * @param string $messageData
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param string $receiver
     *
     * @return string
     */
    private function replaceMessageVariables(string $messageData, DispatchLog $log, Gateway $gateway, string $receiver): string {

        // Check if message has variables
        if (strpos($messageData, '{{') === false && strpos($messageData, '{') === false) {
            return $messageData;
        }

        // Get contact from dispatch log
        $contact = $log->contact;

        // Build replacement map with contact data
        $variables = [
            'phone' => $receiver,
            'name' => $receiver, // Default to phone number
            'email' => ''
        ];

        if ($contact) {
            if (!empty($contact->name)) {
                $variables['name'] = $contact->name;
            } elseif (!empty($contact->first_name) || !empty($contact->last_name)) {
                $variables['name'] = trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? ''));
            }

            $variables['phone'] = $contact->phone_number ?? $receiver;
            $variables['email'] = $contact->email ?? '';
        }

        // Replace both {{variable}} and {variable} patterns
        $message = $messageData;
        foreach ($variables as $key => $value) {
            $message = str_replace('{{' . $key . '}}', $value, $message);
            $message = str_replace('{' . $key . '}', $value, $message);
        }

        return $message;
    }

    /**
     * Get WhatsApp profile name from Node service
     *
     * @param Gateway $gateway
     * @param string $receiver
     *
     * @return string|null
     */
    private function getWhatsAppProfileName(Gateway $gateway, string $receiver): ?string {

        try {
            $apiURL = env('WP_SERVER_URL') . '/messages/get-contact';

            $postInput = [
                'sessionId' => $gateway->name,
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
                $res = json_decode($response->getBody(), true);

                if (Arr::get($res, 'success') === true) {
                    // Try to get pushname (WhatsApp display name) or notify (contact name)
                    $contactData = Arr::get($res, 'data', []);
                    return Arr::get($contactData, 'pushname')
                        ?? Arr::get($contactData, 'notify')
                        ?? Arr::get($contactData, 'name')
                        ?? null;
                }
            }

            return null;

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate WhatsApp session before sending
     * Uses cache to avoid HTTP roundtrip on every single message
     * Cache TTL: 60 seconds — balances reliability vs performance
     *
     * @param Gateway $gateway
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validateSession(Gateway $gateway): array
    {
        $cacheKey = "whatsapp_session_valid_{$gateway->id}";

        // Check cache first — avoid HTTP call for every message in bulk campaigns
        if (\Illuminate\Support\Facades\Cache::get($cacheKey)) {
            return ['valid' => true, 'error' => null];
        }

        try {
            $apiURL = env('WP_SERVER_URL') . '/sessions/status/' . $gateway->name;
            $headers = [
                'Content-Type' => 'application/json',
                'X-API-Key' => env('WP_API_KEY', ''),
            ];

            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders($headers)
                ->get($apiURL);

            if ($response->status() === 200) {
                $res = json_decode($response->body(), true);

                // Check if session is authenticated
                if (Arr::get($res, 'data.isSession') === true) {
                    // Cache valid result for 60 seconds
                    \Illuminate\Support\Facades\Cache::put($cacheKey, true, 60);
                    return ['valid' => true, 'error' => null];
                }
            }

            // Session not found or not connected - update gateway status (retry-safe)
            try {
                Gateway::where('id', $gateway->id)->update(['status' => \App\Enums\Common\Status::INACTIVE]);
            } catch (\Throwable $e) {
                Log::warning('Gateway status update failed', ['gateway_id' => $gateway->id, 'error' => $e->getMessage()]);
            }

            // Clear session cache
            \Illuminate\Support\Facades\Cache::forget($cacheKey);

            Log::warning("WhatsApp session invalid for gateway: {$gateway->name}", [
                'gateway_id' => $gateway->id,
                'response_status' => $response->status(),
            ]);

            return [
                'valid' => false,
                'error' => 'WhatsApp session disconnected. Please scan QR code to reconnect.'
            ];

        } catch (\Exception $e) {
            Log::error("Session validation failed: {$e->getMessage()}", [
                'gateway_id' => $gateway->id,
            ]);

            // On connection error, don't immediately fail — Node service has its own
            // auto-reconnect. Let the message attempt proceed; it will fail with a
            // more specific error if the session is truly dead.
            return [
                'valid' => false,
                'error' => 'Cannot connect to WhatsApp service. Please check if service is running.'
            ];
        }
    }

    /**
     * sendNodeMessages
     *
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param Message $message
     * @param string|array $to
     *
     * @return bool
     */
    public function sendNodeMessages(DispatchLog $log, Gateway $gateway, Message $message, string $messageData, string|array $to): bool {

        // Validate session before sending
        $sessionCheck = $this->validateSession($gateway);
        if (!$sessionCheck['valid']) {
            throw new Exception($sessionCheck['error']);
        }

        // Use PhoneNumberService for professional phone number handling
        // This handles all formats: +880xxx, 880xxx, 0xxx, with spaces, dashes, etc.
        $phoneResult = PhoneNumberService::prepareForSending($to, $gateway, false);

        if (!$phoneResult['success']) {
            throw new Exception($phoneResult['error'] ?? 'Invalid phone number format');
        }

        $receiver = $phoneResult['formatted'];

        // Replace variables in message
        $messageData = $this->replaceMessageVariables($messageData, $log, $gateway, $receiver);

        $body = [];
        if(!is_null($message->file_info)) {
            
            $url  = Arr::get($message->file_info, 'url_file', null);
            $type = Arr::get($message->file_info, 'type', null);
            $name = Arr::get($message->file_info, 'name', null);

            if(!filter_var($url, FILTER_VALIDATE_URL)) {

                $url = url($url);
            }
            
            if($type == "image" ) {

                $body = [
                    'image'    => [
                        'url'=>$url
                    ],
                    'mimetype' => 'image/jpeg',
                    'caption'  => $messageData,
                ];
            }

            elseif($type == "audio" ) {

                $body = [
                    'audio' => [
                        'url' => $url
                    ],
                    'caption' => $messageData,
                ];
            }

            elseif($type == "video" ) {

                $body = [
                    'video' => [

                        'url' => $url
                    ],
                    'caption' => $messageData,
                ];
            }

            elseif($type == "document" ) {

                $body = [
                    'document' => [
                        'url' => $url
                    ],
                    'mimetype' => 'application/pdf',
                    'fileName' => $name,
                    'caption'  => $messageData,
                ];
            }
        } else {

            $body['text'] = $messageData;
        }
        
        // Enterprise anti-ban protection: only for bulk/campaign messages.
        // Single messages (no campaign_id) skip the delay for instant delivery.
        // This prevents browser timeout on single sends while protecting bulk sends.
        $isSingleMessage = empty($log->campaign_id);
        if (!$isSingleMessage) {
            $delayMs = $this->calculateAntiBanDelay($gateway, $receiver);
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        $apiURL = env('WP_SERVER_URL') . '/messages/send';

        $postInput = [
            'sessionId' => $gateway->name,
            'receiver'  => $receiver,
            'message'   => $body,
            'delay'     => 0,
        ];

        $headers = [
            'Content-Type' => 'application/json',
            'X-API-Key'    => env('WP_API_KEY', ''),
        ];

        // Retry with exponential backoff for transient failures (connection reset, timeout)
        $maxRetries = 2;
        $lastException = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            try {
                if ($attempt > 0) {
                    // Exponential backoff: 3s, 6s
                    $backoff = $attempt * 3;
                    Log::info("WhatsApp retry attempt {$attempt}/{$maxRetries} after {$backoff}s", [
                        'gateway_id' => $gateway->id,
                        'receiver' => $receiver,
                    ]);
                    sleep($backoff);
                }

                // Timeout: 20s for single messages, 30s for campaigns (with typing simulation)
                $httpTimeout = $isSingleMessage ? 20 : 30;
                $response = Http::timeout($httpTimeout)
                    ->withoutVerifying()
                    ->withHeaders($headers)
                    ->post($apiURL, $postInput);

                if ($response && $response->status() === 200) {
                    $res = json_decode($response->getBody(), true);
                    if (!Arr::has($res, "success") || $res['success'] !== true) {
                        $errorMessage = Arr::get($res, 'message', 'Unknown error');
                        throw new Exception("Node service error: " . $errorMessage);
                    }
                    return true;
                } else {
                    $errorBody = json_decode($response->body(), true);
                    $errorMessage = Arr::get($errorBody, 'message', 'Failed To Connect Gateway');
                    throw new Exception($errorMessage);
                }
            } catch (Exception $e) {
                $lastException = $e;
                $msg = $e->getMessage();

                // Only retry on transient/connection errors, not on business logic errors
                $isRetryable = str_contains($msg, 'cURL error')
                    || str_contains($msg, 'timed out')
                    || str_contains($msg, 'Connection refused')
                    || str_contains($msg, 'Could not connect')
                    || str_contains($msg, 'Message send timed out');

                if (!$isRetryable || $attempt >= $maxRetries) {
                    throw $lastException;
                }
            }
        }

        throw $lastException ?? new Exception('Failed to send message after retries');
    }

    /**
     * sendCloudApiMessages
     *
     * @param DispatchLog $log
     * @param Gateway $gateway
     * @param Message $message
     * @param string $body
     * @param string|array $to
     *
     * @return bool
     */
    public static function sendCloudApiMessages(DispatchLog $log, Gateway $gateway, Message $message, string $body, string|array $to): bool {

        $message                = $message->load(['template']);
        $template               = $message->template;

        if (!$template) {
            throw new Exception("Template not found for Cloud API message");
        }

        $default_credentials    = (object) config("setting.whatsapp_business_credentials.default");
        $gateway_credentials    = (object) $gateway->meta_data;
        $messageBody            = $message->message ? json_decode($message->message, true) : [];
        $apiVersion             = $gateway->getApiVersion();
        $url                    = "https://graph.facebook.com/{$apiVersion}/{$gateway_credentials->phone_number_id}/messages";

        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => "Bearer {$gateway_credentials->user_access_token}",
        ];

        // Build template data
        $templateData = [
            "name" => $template->name,
            "language" => [
                "code" => Arr::get($template->template_data, 'language', 'en')
            ]
        ];

        // Only include components if there are variable substitutions
        if (!empty($messageBody) && is_array($messageBody)) {
            $templateData["components"] = $messageBody;
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'template',
            'template'          => $templateData
        ];

        Log::info("WhatsApp Cloud API Request", [
            'url' => $url,
            'to' => $to,
            'template' => $template->name,
            'has_components' => !empty($messageBody)
        ]);

        $response           = Http::timeout(30)->withHeaders($headers)->post($url, $data);
        $responseBody       = $response->body();
        $responseData       = json_decode($responseBody, true);

        if (!$response->successful()) {
            $errorMessage = Arr::get($responseData, 'error.message', 'Failed to dispatch via Cloud API');
            Log::error("WhatsApp Cloud API Error", [
                'error' => $errorMessage,
                'response' => $responseData
            ]);
            throw new Exception($errorMessage);
        }

        // Check for errors in successful response (some API errors return 200)
        if (Arr::has($responseData, 'error.message')) {
            $errorMessage = Arr::get($responseData, 'error.message');
            Log::error("WhatsApp Cloud API Error in Response", [
                'error' => $errorMessage,
                'response' => $responseData
            ]);
            throw new Exception($errorMessage);
        }

        // Success - update log and return true
        $log->response_message = $responseData;
        $log->status           = SystemCommunicationStatusEnum::PROCESSING->value;
        $log->update();

        Log::info("WhatsApp Cloud API Success", [
            'message_id' => Arr::get($responseData, 'messages.0.id'),
            'dispatch_log_id' => $log->id
        ]);

        return true;
    }

    /**
     * @param CommunicationLog $log
     * @param $status
     * @param $errorMessage
     * @return void
     */
    public static function addedCreditLog(CommunicationLog $log, $status, $errorMessage = null): void {
        
        $log->status           = $status;
        $log->response_message = !is_null($errorMessage) ? $errorMessage : null;
        $log->save();
        $user = User::find($log->user_id);

        if ($user && $status == CommunicationStatusEnum::FAIL->value) {

            if($log->whatsapp_template_id) {

                CustomerService::addedCreditLog($user, 1, ServiceType::WHATSAPP->value);
            } else {

                $messages    = str_split($log->message["message_body"], site_settings('whatsapp_word_count'));
                $totalcredit = count($messages);
                CustomerService::addedCreditLog($user, $totalcredit, ServiceType::WHATSAPP->value);
            }
        }
    }

    private static function processFailed($whatsapp_log, $message = "Failed To Connect Gateway") {

        $status = (string) CommunicationStatusEnum::FAIL->value;
        if($whatsapp_log->user_id) {

            SendWhatsapp::addedCreditLog($whatsapp_log, $status, $message);
        } else {

            $whatsapp_log->response_message = $message;
            $whatsapp_log->status = $status;
            $whatsapp_log->save();
        }
    }
}
