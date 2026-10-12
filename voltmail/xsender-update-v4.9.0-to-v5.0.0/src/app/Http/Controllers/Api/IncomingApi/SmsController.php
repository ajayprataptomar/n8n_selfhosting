<?php

namespace App\Http\Controllers\Api\IncomingApi;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\CommunicationManager;
use App\Http\Resources\GetSmsLogResource;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Requests\ApiSmsDispatchRequest;
use App\Managers\GatewayManager;
use App\Services\System\Contact\ContactService;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Services\System\Communication\DispatchService;
use Illuminate\Support\Arr;

class SmsController extends Controller
{
    protected DispatchService $dispatchService;
    protected ContactService $contactService;
    protected GatewayManager $gatewayManager;
    protected CommunicationManager $communicationManager;

    public function __construct()
    {
        $this->dispatchService = new DispatchService();
        $this->contactService = new ContactService();
        $this->gatewayManager = new GatewayManager();
        $this->communicationManager = new CommunicationManager();
    }

    /**
     * Summary of getSmsLog
     * @param int|string|null $id
     * @return JsonResponse
     */
    public function getSmsLog(int|string|null $id = null): JsonResponse
    {
        $user = $this->authenticateUser();
        $smsLog = $this->communicationManager->getSpecificDispatchLog($id, $user);

        if (!$smsLog) {
            return ApiJsonResponse::notFound(translate("Invalid SMS Log ID"));
        }

        return ApiJsonResponse::success(
            translate('Successfully fetched Sms from Logs'),
            new GetSmsLogResource($smsLog)
        );
    }

    /**
     * Determine the best SMS method based on user preference and available gateways
     * Falls back intelligently if the preferred method has no active gateways
     *
     * @param User|Admin|null $user
     * @param string|null $requestedMethod - Optional method from API request (android/api)
     * @return array ['method' => string, 'gatewayId' => string]
     */
    protected function determineSmsMethod($user, ?string $requestedMethod = null): array
    {
        // If specific method requested in API, try that first
        if ($requestedMethod) {
            $requestedMethod = strtolower($requestedMethod);
            if ($requestedMethod === 'android') {
                return [
                    'method' => SmsGatewayTypeEnum::ANDROID->value,
                    'gatewayId' => '0'
                ];
            } elseif ($requestedMethod === 'api') {
                return [
                    'method' => SmsGatewayTypeEnum::API->value,
                    'gatewayId' => '-1'
                ];
            }
        }

        // Check if user/admin has a specific gateway preference set
        $gatewayPref = null;
        if ($this->isAdminApiRequest()) {
            // Admin uses site_settings for gateway preference
            $gatewayPref = site_settings('api_sms_gateway_id');
        } elseif ($user && $user->api_sms_gateway_id) {
            // User uses their own preference
            $gatewayPref = $user->api_sms_gateway_id;
        }

        if ($gatewayPref) {
            // Format is "api_123" or "android_456"
            if (str_starts_with($gatewayPref, 'api_')) {
                $gatewayId = str_replace('api_', '', $gatewayPref);
                return [
                    'method' => SmsGatewayTypeEnum::API->value,
                    'gatewayId' => $gatewayId
                ];
            } elseif (str_starts_with($gatewayPref, 'android_')) {
                $gatewayId = str_replace('android_', '', $gatewayPref);
                return [
                    'method' => SmsGatewayTypeEnum::ANDROID->value,
                    'gatewayId' => $gatewayId
                ];
            }
        }

        // Get user's preferred method from settings
        $preferredMethod = null;
        if ($this->isAdminApiRequest()) {
            $preferredMethod = site_settings("api_sms_method", "1") == "1"
                ? SmsGatewayTypeEnum::ANDROID->value
                : SmsGatewayTypeEnum::API->value;
        } else {
            $preferredMethod = @$user?->api_sms_method == "1"
                ? SmsGatewayTypeEnum::ANDROID->value
                : SmsGatewayTypeEnum::API->value;
        }

        // Check if preferred method has active gateways, if not, try the other
        $hasAndroidGateway = $this->hasActiveGateway($user, SmsGatewayTypeEnum::ANDROID->value);
        $hasApiGateway = $this->hasActiveGateway($user, SmsGatewayTypeEnum::API->value);

        // If preferred method is available, use it
        if ($preferredMethod === SmsGatewayTypeEnum::ANDROID->value && $hasAndroidGateway) {
            return ['method' => SmsGatewayTypeEnum::ANDROID->value, 'gatewayId' => '0'];
        }
        if ($preferredMethod === SmsGatewayTypeEnum::API->value && $hasApiGateway) {
            return ['method' => SmsGatewayTypeEnum::API->value, 'gatewayId' => '-1'];
        }

        // Fallback: use whatever is available
        if ($hasAndroidGateway) {
            return ['method' => SmsGatewayTypeEnum::ANDROID->value, 'gatewayId' => '0'];
        }
        if ($hasApiGateway) {
            return ['method' => SmsGatewayTypeEnum::API->value, 'gatewayId' => '-1'];
        }

        // No gateways available - return preferred method anyway (will show proper error later)
        $defaultMethod = $preferredMethod ?: SmsGatewayTypeEnum::API->value;
        return [
            'method' => $defaultMethod,
            'gatewayId' => $defaultMethod === SmsGatewayTypeEnum::ANDROID->value ? '0' : '-1'
        ];
    }

    /**
     * Check if user has active gateway of specified type
     *
     * @param User|Admin|null $user
     * @param string $type
     * @return bool
     */
    protected function hasActiveGateway($user, string $type): bool
    {
        if ($type === SmsGatewayTypeEnum::ANDROID->value) {
            // Check for active Android SIMs with connected sessions
            $query = \App\Models\AndroidSim::where('status', 'active')
                ->where('send_sms', true)
                ->whereHas('androidSession', fn($q) => $q->connected());

            if ($user && !$this->isAdminApiRequest()) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                });
            }

            return $query->exists();
        } else {
            // Check for active API gateways
            $query = \App\Models\Gateway::where('channel', ChannelTypeEnum::SMS->value)
                ->where('status', 'active')
                ->where('type', SmsGatewayTypeEnum::API->value);

            if ($user && !$this->isAdminApiRequest()) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->orWhereNull('user_id');
                });
            }

            return $query->exists();
        }
    }

    /**
     * store
     *
     * @param ApiSmsDispatchRequest $request
     *
     * @return JsonResponse
     */
    public function store(ApiSmsDispatchRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $user = $this->authenticateUser();
            $isAdmin = $this->isAdminApiRequest();

            // Get method from request or determine automatically
            $requestedMethod = $request->input('method'); // Optional: 'android' or 'api'
            $methodConfig = $this->determineSmsMethod($user, $requestedMethod);
            $method = $methodConfig['method'];
            $gatewayId = $methodConfig['gatewayId'];
            $contacts = $request->input('contact');
            $contactCount = count($contacts);
            $logs = collect($contacts)
                ->map(function ($contact) use ($user, $isAdmin, $method, $gatewayId, &$contactCount) {
                    $messageData = [
                        'message_body' => Arr::get($contact, "message"),
                    ];
                    $metaData = [
                        'sms_type' => Arr::get($contact, 'sms_type'),
                    ];
                    
                    if (Arr::get($contact, 'gateway_identifier')) {
                        
                        $gateway = $this->gatewayManager
                            ->getSpecificGateway(
                                channel: ChannelTypeEnum::SMS,
                                type: null,
                                column: "uid",
                                value: Arr::get($contact, 'gateway_identifier'),
                                user: $isAdmin ? null : $user
                            );
                        $contact = Arr::set($contact, "gateway_identifier", @$gateway?->id);
                    }
                    
                    
                    $requestData = new Request([
                        'contacts' => $contact['number'],
                        'message' => $messageData,
                        'schedule_at' => Arr::get($contact, 'schedule_at'),
                        'sms_type' => Arr::get($metaData, "sms_type"),
                        'method' => $method,
                        'gateway_id' => Arr::get($contact, 'gateway_identifier', $gatewayId),
                    ]);
                    $log = $this->dispatchService->storeDispatchLogs(
                        type: ChannelTypeEnum::SMS,
                        request: $requestData,
                        isCampaign: false,
                        campaignId: null,
                        user: $isAdmin ? null : $user,
                        isApi: true,
                        apiLogCount: $contactCount,
                    );
                    $contactCount--;
                    return $log;
                })->flatten(1)
                ->toArray();

            DB::commit();

            return ApiJsonResponse::success(
                message: translate('Sms dispatch request created successfully'),
                data: $logs
            );
        } catch (\App\Exceptions\ApplicationException $e) {
            DB::rollBack();
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                [],
                $e->getStatusCode()
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiJsonResponse::validationError(
                $e->getMessage()
            );
        }
    }

    /**
     * Summary of sendWithQuery
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function sendWithQuery(Request $request): JsonResponse
    {
        try {
            $contacts = explode(',', $request->query('contacts', ''));
            $message = $request->query('message', '');
            $scheduleAt = $request->query('schedule_at', '');
            $smsType = $request->query('sms_type', '');
            $gatewayIdentifier = $request->query('gateway_identifier', '');
            $requestedMethod = $request->query('method', ''); // Optional: 'android' or 'api'

            if (empty($contacts) || !$message) {
                return ApiJsonResponse::validationError(
                    ['contacts' => 'Contacts, message & sms_type are required']
                );
            }

            $user = $this->authenticateUser();
            $isAdmin = $this->isAdminApiRequest();

            // Use intelligent method detection with optional override
            $methodConfig = $this->determineSmsMethod($user, $requestedMethod ?: null);
            $method = $methodConfig['method'];
            $gatewayId = $methodConfig['gatewayId'];

            $group = $this->contactService->createGroupFromApiContacts(
                type: ChannelTypeEnum::SMS,
                contacts: array_map(fn($sms) => ['sms' => $sms], $contacts),
                user: $isAdmin ? null : $user
            );

            if ($gatewayIdentifier) {
                $gateway = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::SMS,
                    type: null,
                    column: "uid",
                    value: $gatewayIdentifier,
                    user: $isAdmin ? null : $user
                );
                $gatewayIdentifier = @$gateway?->id;
            }

            $messageData = [
                'message_body' => $message,
            ];
            $metaData = [
                'sms_type' => $smsType,
            ];

            $apiRequest = new Request([
                'contacts' => [$group->id],
                'message' => $messageData,
                'schedule_at' => $scheduleAt,
                'sms_type' => $metaData['sms_type'],
                'method' => $method,
                'gateway_id' => $gatewayIdentifier ?: $gatewayId,
            ]);

            $logs = $this->dispatchService->storeDispatchLogs(
                type: ChannelTypeEnum::SMS,
                request: $apiRequest,
                isCampaign: false,
                campaignId: null,
                user: $isAdmin ? null : $user,
                isApi: true
            );

            return ApiJsonResponse::success(
                message: translate('Sms dispatch request created successfully'),
                data: $logs
            );
        } catch (\Exception $e) {
            
            return ApiJsonResponse::validationError(
                $e->getMessage()
            );
        }
    }

    /**
     * Authenticate API user. Returns User model for user keys, null for admin keys.
     */
    protected function authenticateUser(): ?User
    {
        $apiKey = request()->header('Api-key')
            ?? request()->query('api_key')
            ?? request()->input('api_key')
            ?? null;

        if (!$apiKey) {
            throw new \App\Exceptions\ApplicationException(translate('Invalid API key'), 401);
        }

        $user = User::where('api_key', $apiKey)->first();
        if ($user) return $user;

        $admin = Admin::where('api_key', $apiKey)->first();
        if ($admin) return null; // Admin context

        throw new \App\Exceptions\ApplicationException(translate('Invalid API key'), 401);
    }

    protected function isAdminApiRequest(): bool
    {
        $apiKey = request()->header('Api-key')
            ?? request()->query('api_key')
            ?? request()->input('api_key')
            ?? null;
        return $apiKey && Admin::where('api_key', $apiKey)->exists();
    }
}