<?php

namespace App\Http\Utility;

use Exception;
use GuzzleHttp\Client;
use App\Models\Gateway;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Models\DispatchLog;
use App\Traits\Dispatchable;
use App\Enums\SmsProviderKey;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Database\Eloquent\Collection;

# Vonage or Nexmo
use Vonage\SMS\Message\SMS;
use Vonage\Client as VonageClient;
use Vonage\Client\Credentials\Basic;

# Twilio
use Twilio\Rest\Client as TwilioClient;

# Message Bird
use MessageBird\Objects\Message;
use MessageBird\Client as MessageBirdClient;

# Text Magic
use Textmagic\Services\TextmagicRestClient;

# Infobip
use Infobip\Configuration;
use Infobip\Api\SendSmsApi;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Infobip\Model\SmsAdvancedTextualRequest;

class SendSMS
{
    use Dispatchable;

    /**
     * Send SMS using the specified provider.
     *
     * @param string $provider
     * @param array|string $to
     * @param Gateway $gateway
     * @param array|Collection|DispatchLog $dispatchLog
     * @param array|string|null $message
     * @return bool
     */
    public function send(string $provider, array|string $to, Gateway $gateway, array|Collection|DispatchLog $dispatchLog, array|string|null $message = null): bool
    {
        $message = textSpinner($message);
        return $this->sendWithHandler($provider, $to, $gateway, $dispatchLog, $message);
    }

    /**
     * Handle SMS sending with the appropriate provider handler.
     *
     * @param string $provider
     * @param array|string $to
     * @param Gateway $gateway
     * @param array|Collection|DispatchLog $dispatchLog
     * @param array|string|null $message
     * @return bool
     */
    protected function sendWithHandler(string $provider, array|string $to, Gateway $gateway, array|Collection|DispatchLog $dispatchLog, array|string|null $message = null): bool
    {
        $creds = null;
        if ($provider == SmsProviderKey::CUSTOM->value) {
            $creds = $gateway->meta_data;
        } else {
            $creds = $this->getCredentials(ChannelTypeEnum::SMS, $provider, $gateway);
        }

        if (!$creds) {
            $this->fail($dispatchLog, translate("Gateway credentials are not available"));
            return false;
        }

        $success = false;

        try {
            switch ($provider) {
                case SmsProviderKey::CUSTOM->value:
                    $success = $this->sendCustom($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::NEXMO->value:
                    $success = $this->sendNexmo($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::TWILIO->value:
                    $success = $this->sendTwilio($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::MESSAGEBIRD->value:
                    $success = $this->sendMessagebird($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::TEXTMAGIC->value:
                    $success = $this->sendTextmagic($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::CLICKATELL->value:
                    $success = $this->sendClickatell($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::INFOBIP->value:
                    $success = $this->sendInfobip($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::SMSBROADCAST->value:
                    $success = $this->sendSmsbroadcast($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::MIMSMS->value:
                    $success = $this->sendMimsms($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::AJURASMS->value:
                    $success = $this->sendAjurasms($creds, $to, $message, $dispatchLog);
                    break;
                case SmsProviderKey::MSG91->value:
                    $success = $this->sendMsg91($creds, $to, $message, $dispatchLog);
                    break;
            }

            if ($success && $dispatchLog) {
                $this->markAsDelivered($dispatchLog);
            }
            return $success;
        } catch (Exception $e) {
            
            $this->fail($dispatchLog, $e->getMessage());
            return false;
        }
    }

    /**
     * sendCustom
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendCustom(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $url        = $this->buildUrl($creds, $to, $message);
        $method     = Arr::get($creds, 'method', 'POST');
        $headers    = $this->buildHeaders($creds, $to, $message);
        $body       = $this->buildBody($creds, $to, $message, $dispatchLog);
        $headers    = array_merge($headers, $this->applyAuthentication($creds));

        $bodyType = Arr::get($creds, 'body_type', 'form-data');
        $client = Http::timeout(10)
            ->withoutVerifying();

        // Conditionally set headers and client options
        if ($bodyType === 'x-www-form-urlencoded') {
            $client = $client->asForm()->withHeaders($headers);
        } else {
            $client = $client->withHeaders($headers);
        }

        if (strtoupper($method) === 'GET') {
            $response = $client->get($url);
        } else {
            if ($bodyType === 'raw' && is_string($body) && json_decode($body, true) !== null) {
                // JSON raw body
                $body = json_decode($body, true);
                Arr::set($headers, 'Content-Type', 'application/json');
                $client = $client->withHeaders($headers);
                $response = $client->{strtolower($method)}($url, $body);
            } else {
                $response = $client->{strtolower($method)}($url, $body);
            }
        }

        $statusCode     = $response->status();
        $responseBody   = $response->body();
        
        $isSuccess = $this->validateResponse($creds, $statusCode, $responseBody);

        if (!$isSuccess) {
            $errorMessage = $this->extractErrorMessage($creds, $responseBody);
            throw new Exception($errorMessage);
        }

        return true;
    }

    /**
     * sendNexmo
     *
     * @param array $creds
     * @param string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendNexmo(array $creds, string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $basic = new Basic(Arr::get($creds, 'api_key'), Arr::get($creds, 'api_secret'));
        $client = new VonageClient($basic);
        $response = $client->sms()
                           ->send(new SMS($to, Arr::get($creds, 'sender_id'), $message));
        if ($response->current()->getStatus() != 0) {
            throw new Exception("Vonage API failed with status: " . $response->current()->getStatus());
        }
        return true;
    }

    /**
     * sendTwilio
     *
     * @param array $creds
     * @param string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendTwilio(array $creds, string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $client = new TwilioClient(Arr::get($creds, 'account_sid'), Arr::get($creds, 'auth_token'));
        $result = $client->messages->create('+' . $to, [
            'from' => Arr::get($creds, 'from_number'),
            'body' => $message
        ]);
        if (!in_array($result->status, ['queued', 'sent', 'delivered'])) {
            throw new Exception("Twilio API failed with status: " . $result->status . " - " . $result->errorMessage);
        }
        return true;
    }

    /**
     * sendMessagebird
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendMessagebird(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $smsType = $dispatchLog instanceof Collection 
                        ? $dispatchLog->first()->sms_type 
                        : $dispatchLog->sms_type;
        $unicode            = $smsType != "plain" ;
        $client             = new MessageBirdClient(Arr::get($creds, 'access_key'));
        $msg                = new Message();
        $msg->originator    = Arr::get($creds, 'sender_id');
        $msg->recipients    = $to; 
        $msg->datacoding    = $unicode ? "unicode" : "plain";
        $msg->body          = $message;
        $result = $client->messages->create($msg);
        if (!isset($result->id)) {
            throw new Exception("MessageBird API failed: No message ID returned");
        }
        return true;
    }

    /**
     * sendTextmagic
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendTextmagic(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $client = new TextmagicRestClient(Arr::get($creds, 'text_magic_username'), Arr::get($creds, 'api_key'));
        $result = $client->messages->create([
            'text' => $message,
            'phones' => is_array($to) ? implode(',', $to) : $to 
        ]);
        if (!Arr::has($result, "id")) {
            throw new Exception("Textmagic API failed: No message ID returned");
        }
        return true;
    }

    /**
     * sendClickatell
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendClickatell(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $key = Arr::get($creds, 'clickatell_api_key');
        $message = urlencode($message);
        $toString = is_array($to) ? urlencode(implode(',', $to)) : urlencode($to); 
        $response = @file_get_contents("https://platform.clickatell.com/messages/http/send?apiKey=$key&to=$toString&content=$message");
        if ($response === false) {
            throw new Exception("Clickatell API Error");
        }
        return true;
    }

    /**
     * sendInfobip
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendInfobip(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $configuration = (new Configuration())
            ->setHost(Arr::get($creds, 'infobip_base_url'))
            ->setApiKeyPrefix('Authorization', 'App')
            ->setApiKey('Authorization', Arr::get($creds, 'infobip_api_key'));
        $client = new Client();
        $sendSmsApi = new SendSmsApi($client, $configuration);

        $destinations = is_array($to) 
                            ? array_map(fn($recipient) => (new SmsDestination())->setTo($recipient), $to) 
                            : [(new SmsDestination())->setTo($to)]; 
        $messageObj = (new SmsTextualMessage())
            ->setFrom(Arr::get($creds, 'sender_id'))
            ->setText(is_array($message) ? $message[0] : $message)
            ->setDestinations($destinations);
        $request = (new SmsAdvancedTextualRequest())->setMessages([$messageObj]);
        $result = $sendSmsApi->sendSmsMessage($request);
        if (!$result->getMessages()[0]->getMessageId()) {
            throw new Exception("Infobip API failed: " . $result->getMessages()[0]->getStatus()->getDescription());
        }
        return true;
    }

    /**
     * sendSmsbroadcast
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendSmsbroadcast(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $message = urlencode($message);
        $toString = is_array($to) ? implode(',', $to) : $to; 
        $response = @file_get_contents("https://api.smsbroadcast.com.au/api-adv.php?username=" . Arr::get($creds, 'sms_broadcast_username') . "&password=" . Arr::get($creds, 'sms_broadcast_password') . "&to=$toString&from=" . Arr::get($creds, 'sender_id') . "&message=$message&ref=112233&maxsplit=5&delay=15");
        if (Str::contains($response, 'ERROR:') || Str::contains($response, 'BAD:')) {
            $errorMessage = explode(':', $response)[1] ?? "Unknown error";
            throw new Exception("SMSBroadcast API failed: " . $errorMessage);
        }
        return true;
    }

    /**
     * sendMimsms
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendMimsms(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $smsType = $dispatchLog instanceof Collection 
                        ? $dispatchLog->first()->sms_type 
                        : $dispatchLog->sms_type;
        $unicode = $smsType != "plain" ;
            
        $message = $unicode 
                        ? rawurlencode($message) 
                        : $message;
        $data = [
            "api_key" => Arr::get($creds, 'api_key'),
            "type" => $smsType == 'plain' ? 'plain' : 'unicode',
            "contacts" => is_array($to) ? implode(',', $to) : $to, 
            "senderid" => Arr::get($creds, 'sender_id'),
            "msg" => $message,
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, Arr::get($creds, 'api_url'));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        curl_close($ch);
        if (in_array($response, ['1002', '1003', '1004', '1005', '1006', '1007', '1008', '1009', '1010', '1011'])) {
            throw new Exception("MimSMS API failed with error code: " . $response);
        }
        return true;
    }

    /**
     * sendAjurasms
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendAjurasms(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $message = urlencode($message);
        $toString = is_array($to) ? implode(',', $to) : $to; 
        $response = @file_get_contents("https://smpp.ajuratech.com:7790/sendtext?apikey=" . Arr::get($creds, 'api_key') . "&secretkey=" . Arr::get($creds, 'secret_key') . "&callerID=" . Arr::get($creds, 'sender_id') . "&toUser=$toString&messageContent=$message");
        $response = json_decode($response);
        if (@$response?->Status != '0') {
            throw new Exception(@$response?->Text ?? "Unknown error");
        }
        return true;
    }

    /**
     * sendMsg91
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return bool
     */
    private function sendMsg91(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog): bool
    {
        $smsType = $dispatchLog instanceof Collection 
                        ? $dispatchLog->first()->sms_type 
                        : $dispatchLog->sms_type;
        $unicode = $smsType == "plain" 
                    ? 0
                    : 1;
        $unicode = (is_array($dispatchLog) ? $dispatchLog[0]->sms_type : $dispatchLog->sms_type) == "plain" ? 0 : 1;
        $recipients = [
            "mobiles" => $to,
            "VAR1" => $message
        ];
        $postData = [
            "sender" => Arr::get($creds, 'sender_id'),
            "flow_id" => Arr::get($creds, 'flow_id'),
            "recipients" => $recipients,
            "unicode" => $unicode
        ];
        $postDataJson = json_encode($postData);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => Arr::get($creds, 'api_url'),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $postDataJson,
            CURLOPT_HTTPHEADER => [
                "authkey: " . Arr::get($creds, 'auth_key'),
                "content-type: application/json"
            ],
        ]);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err || json_decode($response)->type != "success") {
            $errorMessage = $err ? "cURL Error: " . $err : "MSG91 API failed: " . (json_decode($response)->message ?? "Unknown error");
            throw new Exception($errorMessage);
        }
        return true;
    }


    ## ------------------ ##
    ##  Helper Functions  ##
    ## ------------------ ##

    /**
     * buildUrl
     *
     * @param array $creds
     * @param mixed $to
     * @param mixed $message
     * 
     * @return string
     */
    private function buildUrl(array $creds, $to, $message): string
    {
        $url            = Arr::get($creds, 'url');
        $queryParams    = Arr::get($creds, 'query_params', []);

        if (empty($queryParams)) return $url;

        $filteredParams = collect($queryParams)
            ->filter(fn($param) => Arr::get($param, 'enabled', true) && !empty(Arr::get($param, 'key')))
            ->mapWithKeys(function ($param) use ($to, $message) {
                $value = $this->replaceTemplateVariables(
                    Arr::get($param, 'value'),
                    is_array($to) ? implode(',', $to) : $to,
                    is_array($message) ? implode(',', $message) : $message
                );
                return [Arr::get($param, 'key') => $value];
            })
            ->toArray();

        if (empty($filteredParams)) return $url;
        return $url . (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . http_build_query($filteredParams);
    }

    /**
     * buildHeaders
     *
     * @param array $creds
     * @param mixed $to
     * @param mixed $message
     * 
     * @return array
     */
    private function buildHeaders(array $creds, $to, $message): array
    {
        $filteredHeaders = collect(Arr::get($creds, 'headers', []))
            ->filter(fn($header) => Arr::get($header, 'enabled', true) && !empty(Arr::get($header, 'key')))
            ->mapWithKeys(function ($header) use ($to, $message) {
                $value = $this->replaceTemplateVariables(
                    Arr::get($header, 'value'),
                    is_array($to) ? implode(',', $to) : $to,
                    is_array($message) ? implode(',', $message) : $message
                );
                return [Arr::get($header, 'key') => $value];
            })
            ->toArray();

        $bodyType = Arr::get($creds, 'body_type', 'none');
        if ($bodyType === 'raw') {
            $rawType = Arr::get($creds, 'raw_type', 'text');
            $contentTypeMap = [
                'text'          => 'text/plain',
                'json'          => 'application/json',
                'javascript'    => 'application/javascript',
                'html'          => 'text/html',
                'xml'           => 'application/xml',
            ];
            Arr::set($filteredHeaders, 'Content-Type', $contentTypeMap[$rawType] ?? 'text/plain');
        } elseif ($bodyType === 'form-data') {
            Arr::set($filteredHeaders, 'Content-Type', 'multipart/form-data');
        } elseif ($bodyType === 'x-www-form-urlencoded') {
            Arr::set($headers, 'Content-Type', 'application/x-www-form-urlencoded');
        }

        return $filteredHeaders;
    }

    /**
     * buildBody
     *
     * @param array $creds
     * @param array|string $to
     * @param array|string $message
     * @param array|Collection|DispatchLog $dispatchLog
     * 
     * @return [type]
     */
    private function buildBody(array $creds, array|string $to, array|string $message, array|Collection|DispatchLog $dispatchLog)
    {
        $bodyType = Arr::get($creds, 'body_type', 'form-data');
        $toString = is_array($to) ? implode(',', $to) : $to;
        $messageString = is_array($message) ? implode(',', $message) : $message;

        if ($bodyType === 'raw') {
            $rawBody = Arr::get($creds, 'raw_body', '');
            $decodedBody = json_decode($rawBody, true);
            if ($decodedBody !== null) {
                $processedBody = $this->replaceTemplateVariables($decodedBody, $toString, $messageString);
                return json_encode($processedBody);
            }
            return $this->replaceTemplateVariables($rawBody, $toString, $messageString);
        }

        if ($bodyType === 'form-data') {
            $formData = Arr::get($creds, 'form_data', []);
            return collect($formData)
                ->filter(fn($data) => Arr::get($data, 'enabled', true) && !empty(Arr::get($data, 'key')))
                ->mapWithKeys(fn($data) => [
                    Arr::get($data, 'key') => $this->replaceTemplateVariables(Arr::get($data, 'value'), $toString, $messageString)
                ])
                ->toArray();
        }

        if ($bodyType === 'x-www-form-urlencoded') {
            $urlencodedData = Arr::get($creds, 'urlencoded_data', []);
            return collect($urlencodedData)
                ->filter(fn($data) => Arr::get($data, 'enabled', true) && !empty(Arr::get($data, 'key')))
                ->mapWithKeys(fn($data) => [
                    Arr::get($data, 'key') => $this->replaceTemplateVariables(Arr::get($data, 'value'), $toString, $messageString)
                ])
                ->toArray();
        }

        return [];
    }

    /**
     * applyAuthentication
     *
     * @param array $creds
     * 
     * @return array
     */
    private function applyAuthentication(array $creds): array
    {
        $headers    = [];
        $authType   = Arr::get($creds, 'auth_type', 'none');

        switch ($authType) {
            case 'none':
                break;
                
            case 'api_key':
                $keyName    = Arr::get($creds, 'api_key_name');
                $keyValue   = Arr::get($creds, 'api_key_value');
                if ($keyName && $keyValue) Arr::set($headers, $keyName, $keyValue);
                break;

            case 'bearer':
                $token = Arr::get($creds, 'bearer_token');
                if ($token) Arr::set($headers, 'Authorization', 'Bearer ' . $token);
                break;
        }

        return $headers;
    }

    /**
     * validateResponse
     *
     * @param array $creds
     * @param int $statusCode
     * @param string $responseBody
     * 
     * @return bool
     */
    private function validateResponse(array $creds, int $statusCode, string $responseBody): bool
    {
        $statusType = Arr::get($creds, 'status_type', 'http_code');
        
        if ($statusType === 'http_code') {
            $successCodes = collect(explode(',', Arr::get($creds, 'success_codes', '')))->map(fn($code) => (int)$code)->toArray();
            $failureCodes = collect(explode(',', Arr::get($creds, 'failure_codes', '')))->map(fn($code) => (int)$code)->toArray();
            
            if (!empty($failureCodes) && in_array($statusCode, $failureCodes)) return false;
            
            return in_array($statusCode, $successCodes) || ($statusCode >= 200 && $statusCode < 300);
        }

        if ($statusType === 'response_key') {
            $statusKey      = Arr::get($creds, 'status_key');
            $successValues  = collect(explode(',', Arr::get($creds, 'success_values', '')))->map(fn($value) => trim($value))->toArray();
            $failureValues  = collect(explode(',', Arr::get($creds, 'failure_values', '')))->map(fn($value) => trim($value))->toArray();
            $responseData   = json_decode($responseBody, true) ?: [];
            $statusValue    = Arr::get($responseData, $statusKey);

            if (!empty($failureValues) && in_array($statusValue, $failureValues)) return false;
            
            return in_array($statusValue, $successValues);
        }

        return false;
    }

    /**
     * extractErrorMessage
     *
     * @param array $creds
     * @param string $responseBody
     * 
     * @return string
     */
    private function extractErrorMessage(array $creds, string $responseBody): string
    {
        $errorKey = Arr::get($creds, 'error_key');
        if ($errorKey) {
            $responseData = json_decode($responseBody, true) ?: [];
            $errorMessage = Arr::get($responseData, $errorKey);
            if ($errorMessage) {
                return $errorMessage;
            }
        }
        return Arr::get($creds, 'fallback_message', 'Failed to send SMS via custom API');
    }

    /**
     * replaceTemplateVariables
     *
     * @param mixed $data
     * @param string $to
     * @param string $message
     * 
     * @return [type]
     */
    private function replaceTemplateVariables($data, string $to, string $message)
    {
        if (is_string($data)) {
            $data = trim($data);
            return str_replace(['{{recipient}}', '{{message}}'], [$to, $message], $data);
        }

        if (is_array($data)) {
            return collect($data)
                ->map(fn($item) => $this->replaceTemplateVariables($item, $to, $message))
                ->toArray();
        }

        return $data;
    }
}