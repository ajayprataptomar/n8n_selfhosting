<?php

namespace App\Http\Controllers\Api\IncomingApi;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Managers\GatewayManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\CommunicationManager;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Resources\GetWhatsAppLogResource;
use App\Services\System\Contact\ContactService;
use App\Http\Requests\ApiWhatsappDispatchRequest;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Services\System\Communication\DispatchService;

class WhatsAppController extends Controller
{
    protected ContactService $contactService;
    protected GatewayManager $gatewayManager;
    protected DispatchService $dispatchService;
    protected CommunicationManager $communicationManager;

    /**
     * __construct
     */
    public function __construct()
    {
        $this->contactService       = new ContactService();
        $this->gatewayManager       = new GatewayManager();
        $this->dispatchService      = new DispatchService();
        $this->communicationManager = new CommunicationManager();
    }

    /**
     * getWhatsAppLog
     *
     * @param int|string|null $id
     * @return JsonResponse
     */
    public function getWhatsAppLog(int|string|null $id = null): JsonResponse
    {
        $user = $this->authenticateUser();
        $whatsappLog = $this->communicationManager->getSpecificDispatchLog($id, $user);

        if (!$whatsappLog) {
            return ApiJsonResponse::notFound(translate("Invalid WHATSAPP Log ID"));
        }

        return ApiJsonResponse::success(
            translate('Successfully fetched WhatsApp from Logs'),
            new GetWhatsAppLogResource($whatsappLog)
        );
    }

    /**
     * store
     *
     * @param ApiWhatsappDispatchRequest $request
     * @return JsonResponse
     */
    public function store(ApiWhatsappDispatchRequest $request): JsonResponse
    {
        $tempFiles = []; // Track temporary files for cleanup

        try {
            DB::beginTransaction();

            $user = $this->authenticateUser(); // null = admin, User = user
            $isAdmin = $this->isAdminApiRequest();
            $method = WhatsAppGatewayTypeEnum::NODE->value;

            // Use gateway preference based on user type (Admin uses site_settings, User uses their preference).
            // Validate the preference is still reachable; fall through to '-1' if not, so the plan-aware
            // logic in GatewayService::assignGateway can pick an admin gateway for admin-access plans.
            if ($isAdmin) {
                $gatewayId = site_settings('api_whatsapp_gateway_id') ?: "-1";
            } elseif ($user && $user->api_whatsapp_gateway_id) {
                $pref = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::WHATSAPP,
                    type: $method,
                    column: "id",
                    value: $user->api_whatsapp_gateway_id,
                    user: $user
                );
                $gatewayId = $pref?->id ?? "-1";
            } else {
                $gatewayId = "-1";
            }
            $contacts = $request->input('contact');

            // Create a new request to hold files
            $modifiedRequest = new Request($request->all());

            // MIME type mapping for common extensions
            $mimeTypes = [
                'webp' => 'image/webp',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'mp3' => 'audio/mpeg',
                'mp4' => 'video/mp4',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'pdf' => 'application/pdf',
            ];

            // Prepare files for the request
            foreach ($contacts as $index => $contact) {
                if (Arr::has($contact, 'media') && Arr::has($contact, 'url')) {
                    $mediaType = Arr::get($contact, 'media');
                    $url = Arr::get($contact, 'url');

                    // Download the file from the URL
                    $response = Http::get($url);
                    if ($response->successful()) {
                        $fileContent = $response->body();
                        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'bin';
                        $tempPath = sys_get_temp_dir() . '/' . uniqid() . '.' . $extension;
                        file_put_contents($tempPath, $fileContent);
                        $tempFiles[] = $tempPath; // Track for cleanup

                        // Determine MIME type
                        $mimeType = mime_content_type($tempPath) ?: 'application/octet-stream';
                        if (isset($mimeTypes[$extension]) && $mimeType === 'text/html') {
                            $mimeType = $mimeTypes[$extension]; // Fallback to extension-based MIME
                        }

                        // Use custom filename if provided, otherwise extract clean name from URL
                        $customFilename = Arr::get($contact, 'filename');
                        if ($customFilename) {
                            // Ensure filename has proper extension
                            $filenameExt = pathinfo($customFilename, PATHINFO_EXTENSION);
                            if (!$filenameExt && $extension) {
                                $customFilename .= '.' . $extension;
                            }
                            $originalName = $this->sanitizeFilename($customFilename);
                        } else {
                            // Extract clean filename from URL path
                            $urlPath = parse_url($url, PHP_URL_PATH);
                            $urlFilename = basename($urlPath);
                            // Clean up URL-encoded characters
                            $urlFilename = urldecode($urlFilename);
                            // Ensure it has extension
                            if (!pathinfo($urlFilename, PATHINFO_EXTENSION) && $extension) {
                                $urlFilename .= '.' . $extension;
                            }
                            $originalName = $this->sanitizeFilename($urlFilename);
                        }

                        // Create UploadedFile instance with proper filename
                        $uploadedFile = new UploadedFile(
                            $tempPath,
                            $originalName,
                            $mimeType,
                            null,
                            true
                        );

                        $modifiedRequest->files->set("contact.$index.$mediaType", $uploadedFile);

                    } else {
                        throw new \App\Exceptions\ApplicationException(
                            translate("Failed to download file from URL: {$url}"),
                            400
                        );
                    }
                }
            }
            $contactCount = count($contacts);
            $logs = collect($contacts)
                        ->map(function ($contact, $index) use ($user, $isAdmin, $method, $gatewayId, $modifiedRequest, &$contactCount) {
                            $messageData = [
                                'message_body' => Arr::get($contact, "message"),
                            ];
                            $metaData = [
                                'sms_type' => Arr::get($contact, 'sms_type'),
                            ];
                    
                            if (Arr::get($contact, 'gateway_identifier')) {
                                $gateway = $this->gatewayManager
                                    ->getSpecificGateway(
                                        channel: ChannelTypeEnum::WHATSAPP,
                                        type: $method,
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
                                'method' => $method,
                                'gateway_id' => Arr::get($contact, 'gateway_identifier', $gatewayId),
                            ]);
                    
                            // Attach file to requestData if present
                            $mediaType = Arr::get($contact, 'media');
                            if ($mediaType) {
                                $fileKey    = "contact.$index.$mediaType";
                                $allFiles   = $modifiedRequest->allFiles();
                                $file       = Arr::get($allFiles, $fileKey);
                                
                                if ($file instanceof UploadedFile) {
                                    
                                    $requestData->files->set($mediaType, $file);
                                }
                            }
                            
                            $log = $this->dispatchService->storeDispatchLogs(
                                type: ChannelTypeEnum::WHATSAPP,
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
                message: translate('WhatsApp dispatch request created successfully'),
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
        } finally {

            foreach ($tempFiles as $tempPath) {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }
        }
    }

    /**
     * sendWithQuery
     *
     * @param Request $request
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

            if (empty($contacts) || !$message) {
                return ApiJsonResponse::validationError(
                    ['contacts' => 'Contacts & message are required']
                );
            }

            $user = $this->authenticateUser();
            $isAdmin = $this->isAdminApiRequest();
            $method = WhatsAppGatewayTypeEnum::NODE->value;

            // Use gateway preference based on user type
            if ($isAdmin) {
                $gatewayId = site_settings('api_whatsapp_gateway_id') ?: "-1";
            } else {
                $gatewayId = $user && $user->api_whatsapp_gateway_id ? $user->api_whatsapp_gateway_id : "-1";
            }

            $group = $this->contactService->createGroupFromApiContacts(
                type: ChannelTypeEnum::WHATSAPP,
                contacts: array_map(fn($whatsapp) => ['whatsapp' => $whatsapp], $contacts),
                user: $isAdmin ? null : $user
            );

            if ($gatewayIdentifier) {
                $gateway = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::WHATSAPP,
                    type: $method,
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
                type: ChannelTypeEnum::WHATSAPP,
                request: $apiRequest,
                isCampaign: false,
                campaignId: null,
                user: $isAdmin ? null : $user,
                isApi: true
            );

            return ApiJsonResponse::success(
                message: translate('WhatsApp dispatch request created successfully'),
                data: $logs
            );
        } catch (\Exception $e) {
            return ApiJsonResponse::validationError(
                $e->getMessage()
            );
        }
    }

    /**
     * authenticateUser
     *
     * @return User|Admin|null
     */
    /**
     * Authenticate API user by API key.
     * Returns User model for user keys, null for admin keys.
     * Admin is treated as null (admin-level) since downstream methods accept ?User.
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
        if ($admin) return null; // Admin-level: null means admin context

        throw new \App\Exceptions\ApplicationException(translate('Invalid API key'), 401);
    }

    /**
     * Check if the current API request is from an admin
     */
    protected function isAdminApiRequest(): bool
    {
        $apiKey = request()->header('Api-key')
            ?? request()->query('api_key')
            ?? request()->input('api_key')
            ?? null;

        return $apiKey && Admin::where('api_key', $apiKey)->exists();
    }

    /**
     * Sanitize filename to remove potentially dangerous characters
     * and prevent path traversal attacks
     *
     * @param string $filename
     * @return string
     */
    protected function sanitizeFilename(string $filename): string
    {
        // Remove path traversal attempts
        $filename = str_replace(['../', '..\\', '/', '\\'], '', $filename);

        // Remove null bytes and control characters
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '', $filename);

        // Remove potentially dangerous characters but keep unicode letters
        $filename = preg_replace('/[<>:"|?*]/', '', $filename);

        // Replace multiple spaces/underscores with single
        $filename = preg_replace('/[\s_]+/', '_', $filename);

        // Trim whitespace and dots from start/end
        $filename = trim($filename, " \t\n\r\0\x0B.");

        // If filename is empty after sanitization, generate a default
        if (empty($filename)) {
            $filename = 'document_' . time();
        }

        // Limit length (keep extension intact)
        $maxLength = 200;
        if (strlen($filename) > $maxLength) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $nameWithoutExt = substr($nameWithoutExt, 0, $maxLength - strlen($extension) - 1);
            $filename = $nameWithoutExt . '.' . $extension;
        }

        return $filename;
    }
}