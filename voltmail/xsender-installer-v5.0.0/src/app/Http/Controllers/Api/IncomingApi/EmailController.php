<?php

namespace App\Http\Controllers\Api\IncomingApi;

use App\Enums\Common\Status;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Models\User;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\CommunicationManager;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Resources\GetEmailLogResource;
use App\Http\Requests\ApiEmailDispatchRequest;
use App\Managers\GatewayManager;
use App\Services\Core\MailService;
use App\Services\System\Contact\ContactService;
use App\Services\System\Communication\DispatchService;
use Illuminate\Support\Arr;

class EmailController extends Controller
{
    protected MailService $mailService;
    protected ContactService $contactService;
    protected GatewayManager $gatewayManager;
    protected DispatchService $dispatchService;
    protected CommunicationManager $communicationManager;

    /**
     * Summary of __construct
     */
    public function __construct()
    {
        $this->mailService = new MailService();
        $this->contactService = new ContactService();
        $this->gatewayManager = new GatewayManager();
        $this->dispatchService = new DispatchService();
        $this->communicationManager = new CommunicationManager();
    }

    /**
     * Summary of getEmailLog
     * @param int|string|null $id
     * @return JsonResponse
     */
    public function getEmailLog(int|string|null $id = null): JsonResponse
    {
        $user = $this->authenticateUser();
        $emailLog = $this->communicationManager->getSpecificDispatchLog($id, $user);

        if (!$emailLog) {
            return ApiJsonResponse::notFound(translate("Invalid Email Log ID"));
        }

        return ApiJsonResponse::success(
            translate('Successfully fetched Email from Logs'),
            new GetEmailLogResource($emailLog)
        );
    }

    /**
     * Summary of store
     * @param \App\Http\Requests\ApiEmailDispatchRequest $request
     * @return JsonResponse
     */
    public function store(ApiEmailDispatchRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            $user = $this->authenticateUser();
            $isAdmin = $this->isAdminApiRequest();

            // Upload attachments once for all contacts
            $attachmentInfo = null;
            if ($request->hasFile('attachments')) {
                $attachmentInfo = $this->dispatchService->uploadEmailAttachments($request, 'attachments');
            }

            $contacts = $request->input('contact');
            $contactCount = count($contacts);
            $logs = collect($contacts)
                ->map(function ($contact) use ($user, $isAdmin, &$contactCount, $attachmentInfo) {
                    $messageData = [
                        'subject' => Arr::get($contact, "subject"),
                        'main_body' => Arr::get($contact, "message"),
                    ];
                    $metaData = [
                        'email_from_name' => Arr::get($contact, 'sender_name'),
                        'reply_to_address' => Arr::get($contact, 'reply_to_email'),
                    ];

                    // Determine gateway: contact-specific > user/admin preference > automatic
                    // Always validate the chosen gateway is reachable for the caller. Otherwise
                    // fall back to '-1' so the plan-aware logic in GatewayService::assignGateway
                    // can pick an admin gateway (for admin-access plans) or surface a clean error.
                    $gatewayId = '-1';
                    if (Arr::get($contact, 'gateway_identifier')) {
                        $gateway = $this->gatewayManager
                            ->getSpecificGateway(
                                channel: ChannelTypeEnum::EMAIL,
                                type: null,
                                column: "uid",
                                value: Arr::get($contact, 'gateway_identifier'),
                                user: $isAdmin ? null : $user
                            );
                        $gatewayId = $gateway?->id ?? '-1';
                    } elseif ($isAdmin) {
                        $gatewayId = site_settings('api_email_gateway_id') ?: '-1';
                    } elseif ($user && $user->api_email_gateway_id) {
                        $pref = $this->gatewayManager
                            ->getSpecificGateway(
                                channel: ChannelTypeEnum::EMAIL,
                                type: null,
                                column: "id",
                                value: $user->api_email_gateway_id,
                                user: $user
                            );
                        $gatewayId = $pref?->id ?? '-1';
                    }

                    $requestParams = [
                        'contacts' => $contact['email'],
                        'message' => $messageData,
                        'schedule_at' => Arr::get($contact, 'schedule_at'),
                        'email_from_name' => Arr::get($metaData, "email_from_name"),
                        'reply_to_address' => Arr::get($metaData, "reply_to_address"),
                        'gateway_id' => $gatewayId,
                    ];
                    if ($attachmentInfo) {
                        $requestParams['email_attachment_info'] = $attachmentInfo;
                    }
                    $requestData = new Request($requestParams);

                    $log = $this->dispatchService->storeDispatchLogs(
                        type: ChannelTypeEnum::EMAIL,
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
                message: translate('Email dispatch request created successfully'),
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
            $mainBody = $request->query('message', '');
            $subject = $request->query('subject', '');
            $emailFromName = $request->query('sender_name', '');
            $replyToEmail = $request->query('reply_to_email', '');
            $scheduleAt = $request->query('schedule_at', '');
            $gatewayIdentifier = $request->query('gateway_identifier', '');

            if (empty($contacts) || !$mainBody || !$subject) {
                return ApiJsonResponse::validationError(
                    ['contacts' => 'Contacts, message, and subject are required']
                );
            }

            if (
                site_settings(SettingKey::EMAIL_CONTACT_VERIFICATION->value) == StatusEnum::TRUE->status()
                || site_settings(SettingKey::EMAIL_CONTACT_VERIFICATION->value) == Status::ACTIVE->value
            ) {
                foreach ($contacts as $contact) {
                    $result = $this->mailService->verifyEmail($contact);

                    if (!Arr::get($result, "valid")) {
                        return ApiJsonResponse::validationError(
                            ['email' => "Invalid email: $contact"]
                        );
                    }
                }
            }

            $user = $this->authenticateUser();
            $isAdmin = $this->isAdminApiRequest();
            $group = $this->contactService->createGroupFromApiContacts(
                type: ChannelTypeEnum::EMAIL,
                contacts: array_map(fn($email) => ['email' => $email], $contacts),
                user: $isAdmin ? null : $user
            );

            // Determine gateway: query param > user/admin preference > automatic
            $gatewayId = '-1';
            if ($gatewayIdentifier) {
                $gateway = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::EMAIL,
                    type: null,
                    column: "uid",
                    value: $gatewayIdentifier,
                    user: $isAdmin ? null : $user
                );
                $gatewayId = $gateway?->id ?? '-1';
            } elseif ($isAdmin) {
                $gatewayId = site_settings('api_email_gateway_id') ?: '-1';
            } elseif ($user && $user->api_email_gateway_id) {
                $pref = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::EMAIL,
                    type: null,
                    column: "id",
                    value: $user->api_email_gateway_id,
                    user: $user
                );
                $gatewayId = $pref?->id ?? '-1';
            }

            $messageData = [
                'subject' => $subject,
                'main_body' => $mainBody,
            ];
            $metaData = [
                'email_from_name' => $emailFromName,
                'reply_to_address' => $replyToEmail,
            ];

            $apiRequest = new Request([
                'contacts' => [$group->id],
                'message' => $messageData,
                'schedule_at' => $scheduleAt,
                'email_from_name' => $metaData['email_from_name'],
                'reply_to_address' => $metaData['reply_to_address'],
                'gateway_id' => $gatewayId,
            ]);

            $logs = $this->dispatchService->storeDispatchLogs(
                type: ChannelTypeEnum::EMAIL,
                request: $apiRequest,
                isCampaign: false,
                campaignId: null,
                user: $isAdmin ? null : $user,
                isApi: true
            );

            return ApiJsonResponse::success(
                message: translate('Email dispatch request created successfully'),
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