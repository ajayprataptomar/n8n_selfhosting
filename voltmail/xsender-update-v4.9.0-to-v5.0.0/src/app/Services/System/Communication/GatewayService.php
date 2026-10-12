<?php

namespace App\Services\System\Communication;

use App\Enums\Common\Status;
use App\Enums\DefaultTemplateSlug;
use App\Enums\MetaApiEndpoints;
use App\Enums\SettingKey;
use App\Models\User;
use App\Models\Gateway;
use App\Traits\Manageable;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Enums\StatusEnum;
use App\Models\SmsGateway;
use App\Models\AndroidSim;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\AndroidSession;
use App\Managers\GatewayManager;
use Illuminate\Http\JsonResponse;
use App\Services\Core\MailService;
use Illuminate\Support\Facades\DB;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\SessionStatusEnum;
use App\Services\System\TemplateService;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use Illuminate\Database\Eloquent\Collection;
use App\Http\Requests\ManageAndroidSimRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Http\Requests\RegisterAndroidSessionRequest;
use App\Http\Utility\SendEmail;
use App\Http\Utility\SendMail;
use App\Managers\TemplateManager;
use App\Models\DispatchDelay;
use App\Models\Template;
use App\Traits\MetaApiTrait;
use App\Models\MetaConfiguration;
use App\Models\WhatsappClientOnboarding;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class GatewayService
{ 
     use Manageable, MetaApiTrait;

     protected $sendMail;
     protected $mailService;
     protected $nodeService;
     protected $gatewayManager;
     protected $templateService;

     /**
      * __construct
      *
      */
     public function __construct()
     {
          $this->sendMail          = new SendMail();
          $this->mailService       = new MailService();
          $this->nodeService       = new NodeService();
          $this->gatewayManager    = new GatewayManager();
          $this->templateService   = new TemplateService();
     }

     /**
      * loadLogs
      *
      * @param ChannelTypeEnum $channel
      * @param SmsGatewayTypeEnum|WhatsAppGatewayTypeEnum|null $type
      * @param User|null $user
      * 
      * @return View
      */
     public function loadLogs(
          ChannelTypeEnum $channel, 
          SmsGatewayTypeEnum|WhatsAppGatewayTypeEnum|null $type = null, 
          ?User $user = null
     ): View {

          $title = translate("Logs");
          $gateways                = null;
          $credentials             = null;
          $serverStatus            = null;
          $serverHealth            = null;
          $gatewayCount            = null;
          $allowedAccess           = null;
          $customApiTranslations   = null;
          
          
          if ($channel == ChannelTypeEnum::SMS 
               && $type == SmsGatewayTypeEnum::API) {
                    
               $title         = translate("SMS Gateways");
               $gateways      = $this->gatewayManager->getGateways(channel: $channel, groupBy: false, user: $user);
               $credentials   = config('setting.gateway_credentials.sms');
               $customApiTranslations = $this->getCustomApiTranslations();
               if($user) {
                    $allowedAccess = planAccess($user);
                    if(!$allowedAccess) {
                         $notify[] = ['error', translate('Please Purchase A Plan')];
                         return redirect()->route('user.dashboard')->withNotify($notify);
                    }
                    $allowedAccess = (object) $allowedAccess;
                    $gatewayCount     = $gateways->groupBy('type')->map->count(); 
               }
               unset($credentials["default_gateway_id"]);

          } elseif ($channel == ChannelTypeEnum::SMS 
               && $type == SmsGatewayTypeEnum::ANDROID) {

               $title    = translate("Android Sessions");
               $gateways = $this->gatewayManager->getAndroidSessions(loadPaginated: true, user: $user);
               if($user) {
                    $allowedAccess = planAccess($user);
                    if(!$allowedAccess) {
                         $notify[] = ['error', translate('Please Purchase A Plan')];
                         return redirect()->route('user.dashboard')->withNotify($notify);
                    }
                    $allowedAccess = (object) $allowedAccess;
                    $gatewayCount     = $gateways->groupBy('type')->map->count(); 
               }

          } elseif ($channel == ChannelTypeEnum::EMAIL) {

               $title         = translate("Email Gateways");
               $gateways      = $this->gatewayManager->getGateways(channel: $channel, groupBy: false, user: $user);
               $credentials   = config('setting.gateway_credentials.email');
               if($user) {
                    $allowedAccess = planAccess($user);
                    if(!$allowedAccess) {
                         $notify[] = ['error', translate('Please Purchase A Plan')];
                         return redirect()->route('user.dashboard')->withNotify($notify);
                    }
                    $allowedAccess = (object) $allowedAccess;
                    $gatewayCount     = $gateways->groupBy('type')->map->count(); 
               }
               
          } elseif ($channel == ChannelTypeEnum::WHATSAPP
               && $type == WhatsAppGatewayTypeEnum::NODE) {

               $title    = translate("WhatsApp Node Devices");
               $gateways = $this->gatewayManager->getGateways(channel: $channel, groupBy: false, type: $type, user: $user);

               // Check health using new health endpoint
               $healthCheck = $this->nodeService->checkHealth();
               $serverStatus = $healthCheck['healthy'];
               $serverHealth = $healthCheck; // Pass full health data to view

          } elseif ($channel == ChannelTypeEnum::WHATSAPP 
               && $type == WhatsAppGatewayTypeEnum::CLOUD) {

               $title    = translate("WhatsApp Cloud APIs");
               $gateways = $this->gatewayManager->getGateways(channel: $channel, groupBy: false, type: $type, user: $user);
               $credentials = config('setting.whatsapp_business_credentials');
               
          } else {

               $notify[] = ["error", translate("Request for an unknown channel")];
               return back()->withNotify($notify);
          }

          $panelType = $user ? "user" : "admin";

          return view($type
               ? "{$panelType}.gateway.{$channel->value}.{$type->value}.index"
               : "{$panelType}.gateway.{$channel->value}.index",
               compact('title', 'gateways', 'credentials', 'serverStatus', 'serverHealth', 'allowedAccess', 'user', 'gatewayCount', 'customApiTranslations'));
     }

     /**
      * getCustomApiTranslations
      *
      * @return array
      */
     private function getCustomApiTranslations(): array {
          return [
               'add_sms_gateway' => translate("Add SMS Gateway"),
               'gateway_name' => translate("Gateway Name"),
               'enter_gateway_name' => translate("Enter Gateway Name"),
               'per_message_min_delay' => translate("Per Message Minimum Delay (Seconds)"),
               'per_message_min_delay_placeholder' => translate("e.g., 0.5 seconds minimum delay per message"),
               'per_message_max_delay' => translate("Per Message Maximum Delay (Seconds)"),
               'per_message_max_delay_placeholder' => translate("e.g., 0.5 seconds maximum delay per message"),
               'delay_after_count' => translate("Delay After Count"),
               'delay_after_count_placeholder' => translate("e.g., pause after 50 messages"),
               'delay_after_duration' => translate("Delay After Duration (Seconds)"),
               'delay_after_duration_placeholder' => translate("e.g., pause for 5 seconds"),
               'reset_after_count' => translate("Reset After Count"),
               'reset_after_count_placeholder' => translate("e.g., reset after 200 messages"),
               'built_in_api' => translate("Built-in API"),
               'custom_api' => translate("Custom API"),
               'gateway_type' => translate("Gateway Type"),
               'select_a_gateway' => translate("Select a Gateway"),
               'api_url_and_method' => translate("API URL And Method"),
               'api_url' => translate("API URL"),
               'api_url_placeholder' => translate("Enter API URL (e.g., Https://api.example.com/send)"),
               'http_method' => translate("HTTP Method"),
               'query_parameters' => translate("Query Parameters"),
               'query_key_placeholder' => translate("Query Key (e.g., key1)"),
               'query_value_placeholder' => translate("Query Value (e.g., {{recipient}} or {{message}})"),
               'add_query_parameter' => translate("Add Query Parameter"),
               'headers' => translate("Headers"),
               'header_key_placeholder' => translate("Header Key (e.g., Content-Type)"),
               'header_value_placeholder' => translate("Header Value (e.g., application/json)"),
               'add_header' => translate("Add Header"),
               'authorization' => translate("Authorization"),
               'authorization_type' => translate("Authorization Type"),
               'none' => translate("None"),
               'api_key' => translate("API Key"),
               'bearer_token' => translate("Bearer Token"),
               'api_key_name' => translate("API Key Name"),
               'api_key_name_placeholder' => translate("e.g., X-API-Key"),
               'api_key_value' => translate("API Key Value"),
               'api_key_value_placeholder' => translate("Enter API Key"),
               'bearer_token_label' => translate("Bearer Token"),
               'bearer_token_placeholder' => translate("Enter Bearer Token"),
               'body' => translate("Body"),
               'body_type' => translate("Body Type"),
               'form_data' => translate("form-data"),
               'url_encoded_data' => translate("x-www-form-urlencoded"),
               'raw' => translate("raw"),
               'form_data_label' => translate("Form Data"),
               'form_data_key_placeholder' => translate("Key (e.g., to)"),
               'form_data_value_placeholder' => translate("Value (e.g., {{recipient}} or {{message}})"),
               'add_form_data' => translate("Add Form Data"),
               'url_encoded_data_label' => translate("URL Encoded Data"),
               'url_encoded_key_placeholder' => translate("Key (e.g., to)"),
               'url_encoded_value_placeholder' => translate("Value (e.g., {{recipient}} or {{message}})"),
               'add_url_encoded_data' => translate("Add URL Encoded Data"),
               'raw_type' => translate("Raw Type"),
               'text' => translate("Text"),
               'javascript' => translate("JavaScript"),
               'json' => translate("JSON"),
               'html' => translate("HTML"),
               'xml' => translate("XML"),
               'raw_body' => translate("Raw Body"),
               'raw_body_placeholder' => '{"to": "{{recipient}}", "message": "{{message}}"}',
               'determine_status_by' => translate("Response Status"),
               'status_type' => translate("Status Type"),
               'default_disabled_status_type' => translate("Select A Type"),
               'http_status_code' => translate("HTTP Status Code"),
               'response_body_key' => translate("Response Body Key"),
               'success_codes' => translate("Success Codes"),
               'success_codes_placeholder' => translate("e.g., 200"),
               'failure_codes' => translate("Failure Codes"),
               'failure_codes_placeholder' => translate("e.g., 400, 500"),
               'status_key' => translate("Status Key"),
               'status_key_placeholder' => translate("e.g., status"),
               'success_values' => translate("Success Values"),
               'success_values_placeholder' => translate("e.g., success, delivered"),
               'failure_values' => translate("Failure Values"),
               'failure_values_placeholder' => translate("e.g., error, failed"),
               'error_message_key' => translate("Error Message Key"),
               'error_message_key_placeholder' => translate("e.g., message"),
               'fallback_error_message' => translate("Fallback Error Message"),
               'fallback_error_message_placeholder' => translate("e.g., Failed to send SMS: Unknown error"),
               'previous' => translate("Previous"),
               'next' => translate("Next"),
               'finish' => translate("Finish"),
               'close' => translate("Close"),
               'save' => translate("Save"),
               'custom_api_save_note' => translate("Hitting save while keeping this tab on will save custom API data"),
               'built_in_save_note' => translate("Hitting save while this tab on will save Built-in gateway data"),
               'use' => translate("Use"),
               'for_recipient_comma' => translate("for recipient, "),
               'for_sms_body' => translate("for SMS body")
           ];
     }

     /**
      * loadAndroidSims
      *
      * @param string|null $token
      * @param User|null $user
      * 
      * @return View
      */
     public function loadAndroidSims(string|null $token, ?User $user = null): View {
          
          $title = translate("Connected SIMs");
          $sims  = $this->getAndroidSims(token: $token, loadPaginated: true, user: $user);
          
          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.gateway.sms.android.sim", 
               compact('title', 'sims', 'token'));
     }

     /**
      * saveAndroidSession
      *
      * @param array $data
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function saveAndroidSession(array $data, ?User $user = null): RedirectResponse {

          $token = generate_unique_token();
          $data = Arr::set($data, "token", $token);
          $data = Arr::set($data, "qr_code", $this->returnUniqueQRCode(Arr::get($data, "name"), $token));
          if($user) {

               if(!Arr::has($data, "id")) {

                    $planAccess = (object) planAccess($user);
                    $existingSessionCount = AndroidSession::where("user_id", $user->id)
                                                                 ->count();
                    if(Arr::get($planAccess->android, "gateway_limit", "-1") != "-1" 
                         && Arr::get($planAccess->android, "gateway_limit", "-1") <= $existingSessionCount)
                    throw new ApplicationException("You have already reached maximum session limit according to your plan", Response::HTTP_NOT_FOUND);
               }
               $data = Arr::set($data, "user_id", $user->id);
          }

          $androidSession = null;
          $sessionId     = Arr::get($data, "id");
          $status        = Arr::get($data, "status");
          if($sessionId && $status == SessionStatusEnum::DISCONNECTED->value) {

               $androidSession = AndroidSession::when($user, 
                                                       fn(Builder $q): Builder =>
                                                            $q->where("user_id", $user->id))
                                                       ->where("id", $sessionId)
                                                       ->first();
               $androidSession?->androidSims()?->update(["status" => Status::INACTIVE]);
          } 

          AndroidSession::updateOrCreate([
               'id' => $sessionId,
          ], $data);

          $notify[] = [
               "success", 
               request()->method() == "PATCH" 
                    ? translate('Android Session Updated Successfully')
                    : translate('Android Session Added Successfully')
          ];
          return back()->withNotify($notify);
     }

     /**
      * deleteAndroidSession
      *
      * @param int|string|null $id
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function deleteAndroidSession(int|string|null $id = null, ?User $user = null): RedirectResponse {

          $androidSession = $this->gatewayManager->getAndroidSession(column:"id", value: $id, isConnected: false, user: $user);
          if(!$androidSession) throw new ApplicationException("Invalid Session. Please try disconnecting the session then try again", Response::HTTP_NOT_FOUND);

          DB::transaction(function() use($androidSession) {
               $androidSession?->androidSims()?->delete();
               $androidSession->delete();
          });
          
          $notify[] = ['success', translate("Android Session along with its sims are deleted successfully")];
          return back()->withNotify($notify);
     }

     /**
      * destroyGateway
      *
      * @param ChannelTypeEnum $channel
      * @param string|null|null $type
      * @param int|string|null|null $id
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function destroyGateway(ChannelTypeEnum $channel, string|null $type = null, int|string|null $id = null, ?User $user = null): RedirectResponse {

          $gateway = $this->gatewayManager->getSpecificGateway(channel: $channel, type: $type, column: "id", value: $id, user: $user);
          if(!$gateway) throw new ApplicationException("Invalid Gateway. Please try disconnecting the session then try again", Response::HTTP_NOT_FOUND);
          
          $gateway->delete();
          
          $notify[] = ['success', translate("{$channel->value} Gateway deleted successfully")];
          return back()->withNotify($notify);
     }

     /**
      * saveSmsGateway
      *
      * @param ChannelTypeEnum $channel
      * @param array $data
      * @param int|string|null|null $id
      * @param user|null $user
      * 
      * @return RedirectResponse
      */
     public function saveGateway(ChannelTypeEnum $channel, array $data, int|string|null $id = null, ?User $user = null): RedirectResponse|array
     {
          $data = Arr::set($data, "channel", $channel->value);
          $type = Arr::get($data, "type");

          if ($channel == ChannelTypeEnum::SMS && Arr::get($data, "gateway_mode") == "custom") {
               $data = Arr::set($data, "type", "custom");
          }
          if ($user) {
               $planAccess = (object) planAccess($user);
               $existingGatewayCount = Gateway::where("channel", $channel)
                                             ->where("user_id", $user->id)
                                             ->count();

               if (Arr::get($planAccess->{$channel->value}, "gateway_limit", "-1") != "-1" 
                    && Arr::get($planAccess->{$channel->value}, "gateway_limit", "-1") < $existingGatewayCount) {
                    throw new ApplicationException("You have already reached maximum gateway limit according to your plan", Response::HTTP_NOT_FOUND);
               }

               $data = Arr::set($data, "user_id", $user->id);
          }

          $this->gatewayManager->createOrUpdateGateway($data, $id);

          $message = request()->method() == "PATCH" 
               ? translate("{$channel->value} Gateway Updated Successfully")
               : translate("{$channel->value} Gateway Added Successfully");

          if ($channel == ChannelTypeEnum::SMS) {
               return [
                    'status' => 'success',
                    'message' => $message,
               ];
          }

          $notify[] = ["success", $message];
          return back()->withNotify($notify);
     }

     /**
      * registerAndroidSessionRequest
      *
      * @param RegisterAndroidSessionRequest $request
      * @param User|null $user
      * 
      * @return JsonResponse
      */
     public function registerAndroidSessionRequest(RegisterAndroidSessionRequest $request, ?User $user = null): JsonResponse {
          
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $request->bearerToken(), ignoreUser:true, isConnected:false, user: $user);
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);

          if ($androidSession->user) {
               Auth::guard('api')->setUser($androidSession->user);
               $user = Auth::guard('api')->user();
               
               if($user) {
                    $planAccess = (object) planAccess($user);
                    $existingSessionCount = AndroidSession::where("user_id", $user->id)
                                                                      ->connected()
                                                                      ->count();
                    if(Arr::get($planAccess->android, "gateway_limit") <= $existingSessionCount)
                         throw new ApplicationException("You have already reached maximum session limit for your plan", Response::HTTP_NOT_FOUND);
               }
          }
          $androidSession->status = $request->input("status");
          $androidSession->save();
          return ApiJsonResponse::success(
            translate('Session status updated successfully'),
            ['status' => $request->input('status')]
        );
     }

     /**
      * logoutAndroidSession
      *
      * @param string $token
      * @param User|null $user
      * 
      * @return JsonResponse
      */
     public function logoutAndroidSession(string $token, ?User $user = null): JsonResponse {
          
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $token, user: $user, ignoreUser: true);
          
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);

          $androidSession->status = SessionStatusEnum::DISCONNECTED;
          $androidSession->save();

          return ApiJsonResponse::success(translate('Successfully logged out from Android Session') );
     }

     /**
      * getAndroidSims
      *
      * @param string $token
      * @param bool $loadPaginated
      * @param User|null $user
      * 
      * @return Collection
      */
     public function getAndroidSims(string $token, bool $loadPaginated = false, ?User $user = null): Collection|LengthAwarePaginator
     {
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $token, user: $user, ignoreUser: true);
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);

          $sims = $this->gatewayManager->getAndroidSims(token: $token, loadPaginated: $loadPaginated, user: $user);
          if($sims->isEmpty()) throw new ApplicationException("Android SIM not found", Response::HTTP_NOT_FOUND);
          return $sims;
     }

     /**
      * storeAndroidSim
      *
      * @param ManageAndroidSimRequest $request
      * @param User|null $user
      * 
      * @return JsonResponse
      */
     public function storeAndroidSim(ManageAndroidSimRequest $request, ?User $user = null): JsonResponse
     {
          $data = $request->validated();
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $request->bearerToken(), user: $user, ignoreUser: true);
          
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);

          // Pass enum ->value (not the enum instance) so query builder can bind
          // the string on older Laravel patches that don't auto-cast backed enums.
          $existingAndroidSim = AndroidSim::when($user,
                                                  fn(Builder $q): Builder =>
                                                       $q->where("user_id", $user->id),
                                                            fn(Builder $q): Builder =>
                                                                 $q->whereNull("user_id"))
                                             ->where("sim_number", $request->input("sim_number"))
                                             ->where("status", Status::ACTIVE->value)
                                             ->exists();
                                             
          if($existingAndroidSim) throw new ApplicationException("SIM is already assigned or active for a session", Response::HTTP_NOT_FOUND);
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $request->bearerToken(), user: $user);
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_NOT_FOUND);
          $data["android_session_id"] = $androidSession->id;

          if($user) $data = Arr::set($data, "user_id", $user->id);
          $sim = $this->gatewayManager->storeAndroidSim($data);

          return ApiJsonResponse::created(
               translate('Android SIM created successfully'),
               $sim
          );
     }

     /**
      * updateAndroidSim
      *
      * @param ManageAndroidSimRequest $request
      * @param int $id
      * @param User|null $user
      * 
      * @return JsonResponse
      */
     public function updateAndroidSim(ManageAndroidSimRequest $request, int $id, ?User $user = null): JsonResponse
     {
          $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $request->bearerToken(), user: $user, ignoreUser: true);
          if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);
          
          $sim = $this->gatewayManager->getAndroidSim(id: $id, userSpecificGateways: true, user: $user, androidSession: $androidSession);
          if (!$sim) throw new ApplicationException("Android SIM not found", Response::HTTP_NOT_FOUND);
          

          $data = $request->validated();
          if($user) $data['user_id'] = $user->id;

          $this->gatewayManager->updateAndroidSim($sim, $data);

          return ApiJsonResponse::success(
               translate('Android SIM updated successfully'),
               $sim->fresh()
          );
     }

     /**
      * Perform the core logic for deleting an Android SIM.
      *
      * @param int|string|null $id
      * @param User|null $user
      * @param int|string|null $token
      * @throws ApplicationException
      */
     private function performAndroidSimDeletion(int|string|null $id, ?User $user, int|string|null $token = null): void
     {
          $androidSession = null;
          if($token) {

               $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $token, user: $user, ignoreUser: true);
               if (!$androidSession) {
                    throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);
               }
          }

          $sim = $this->gatewayManager->getAndroidSim(id: $id, userSpecificGateways: true, user: $user, androidSession: $androidSession);
          if (!$sim) {
               throw new ApplicationException("Android SIM not found", Response::HTTP_NOT_FOUND);
          }

          $this->gatewayManager->deleteAndroidSim($sim);
     }

     /**
      * Delete an Android SIM (used for both web and API contexts).
      *
      * @param int|string|null $id
      * @param User|null $user
      * @param int|string|null $token
      * @return JsonResponse|RedirectResponse
      */
     public function deleteAndroidSim(int|string|null $id = null, ?User $user = null, int|string|null $token = null): JsonResponse|RedirectResponse
     {
          $this->performAndroidSimDeletion($id, $user, $token);
          $notify = [['success', "Android SIM deleted successfully"]];
          $authUser = $user ? "user" : "admin";

          return request()->expectsJson()
               ? ApiJsonResponse::success(translate('Android SIM deleted successfully'))
               : redirect()->route("{$authUser}.gateway.sms.android.index")->withNotify($notify);
     }

     /**
      * Delete an Android SIM specifically for API context.
      *
      * @param int|string|null $id
      * @param User|null $user
      * @param int|string|null $token
      * @return JsonResponse
      */
     public function deleteAndroidSimForApi(int|string|null $id = null, ?User $user = null, int|string|null $token = null): JsonResponse
     {
          $this->performAndroidSimDeletion($id, $user, $token);
          return ApiJsonResponse::success(translate('Android SIM deleted successfully'));
     }

     /**
      * returnUniqueQRCode
      *
      * @param string $name
      * @param string|null $token
      * 
      * @return string
      */
     private function returnUniqueQRCode(string $name, ?string $token = null): string {

          $qrData = [
               'name'              => $name, 
               'base_url'          => config('app.url'), 
               'unique_token'      => $token ?? generate_unique_token(), 
               'version'           => site_settings("app_version"),
               'purchase_key'      => env('PURCHASE_KEY', ''),
               'envato_username'   => env('ENVATO_USERNAME', ''), 
               'software_id'       => config("installer.software_id", ""), 
          ];
          return base64_encode(json_encode($qrData));
     }

     /**
      * assignGateway
      *
      * @param ChannelTypeEnum $type
      * @param array $dispatchLogs
      * @param Request $request
      * @param string|null|null $method
      * @param User|null $user
      * 
      * @return array
      */
     public function assignGateway(ChannelTypeEnum $type, array $dispatchLogs, Request $request, string|null $method = null, ?User $user = null): array
     {
          
          $userSpecificGateways = false;
          $adminSpecificGateways = false;
          $gatewayId     = $request->input("gateway_id");
          $method        = $request->input("method");
          $gatewayData   = null; 
          
          if($user) {
               
               $planAccess = (object) planAccess($user);
               
               if($planAccess->type == StatusEnum::FALSE->status()) $userSpecificGateways = true;

               if($planAccess->type == StatusEnum::TRUE->status()) { 

                    $adminSpecificGateways = true;
                    $userGatewayConfiguration = $user->gateway_credentials;

                    $settingInApplicationSmsMethod          = site_settings('in_application_sms_method');
                    $settingAccessibleSmsApiGateways        = site_settings('accessible_sms_api_gateways');
                    $settingAccessibleSmsAndroidGateways    = site_settings('accessible_sms_android_gateways');
                    $settingAccessibleEmailGateways         = site_settings('accessible_email_gateways');
                    
                    $defaultGatewayConfiguration = (object)[
                         "in_application_sms_method"        => $settingInApplicationSmsMethod,
                         "accessible_sms_api_gateways"      => json_decode($settingAccessibleSmsApiGateways, true),
                         "accessible_sms_android_gateways"  => json_decode($settingAccessibleSmsAndroidGateways, true),
                         "accessible_email_gateways"        => json_decode($settingAccessibleEmailGateways, true),
                    ];

                    
                    $gatewayConfiguration = (isset($userGatewayConfiguration->specific_gateway_access)
                                                  && $userGatewayConfiguration->specific_gateway_access == StatusEnum::TRUE->status())
                                                       ? $userGatewayConfiguration
                                                       : $defaultGatewayConfiguration;

                    if($type == ChannelTypeEnum::SMS) {

                         $accessibleAndroid = (array) ($gatewayConfiguration->accessible_sms_android_gateways ?? []);
                         $accessibleApi     = (array) ($gatewayConfiguration->accessible_sms_api_gateways ?? []);

                         $method = $gatewayConfiguration->in_application_sms_method == StatusEnum::TRUE->status()
                                        ? "api"
                                        : "android";

                         $pool      = $method == "android" ? $accessibleAndroid : $accessibleApi;
                         $gatewayId = !empty($pool) ? $pool[array_rand($pool)] : '-1';

                         if($method == "android" && $gatewayId !== '-1') {
                              $sim = AndroidSim::where("android_session_id", $gatewayId)
                                                            ->active()
                                                            ->inRandomOrder()
                                                            ->select('id')
                                                            ->first();
                              $gatewayId = $sim?->id ?? '-1';
                         }

                    } elseif($type == ChannelTypeEnum::EMAIL) {

                         $accessibleEmail = (array) ($gatewayConfiguration->accessible_email_gateways ?? []);
                         $gatewayId = !empty($accessibleEmail)
                                        ? $accessibleEmail[array_rand($accessibleEmail)]
                                        : '-1';
                    } elseif($type == ChannelTypeEnum::WHATSAPP) {

                        $adminSpecificGateways = false;
                    }
               }
          }
          if($gatewayId != "-2") $gatewayData = $this->gatewayManager->getGatewayForDispatch(channel: $type, adminSpecificGateways:$adminSpecificGateways, userSpecificGateways: $userSpecificGateways, gatewayId: $gatewayId, method: $method, user: $user);
          if($gatewayId == "-2") $gatewayData = $this->gatewayManager->storeDispatchGateway(type: $type, request: $request, user: $user);
          
          if(!$gatewayData) throw new ApplicationException('Gateway could not be assigned');
          
          $gatewayModel = ($type == ChannelTypeEnum::SMS && $method == 'android') 
                              ? AndroidSim::class 
                              : Gateway::class;
                              
          if ($gatewayId === '0') {

               return $this->distributeGatewaysEqually($dispatchLogs, $gatewayData, $gatewayModel);
               
          } else {

               return array_map(function ($log) use ($gatewayData, $gatewayModel) {
                    Arr::set($log, 'gatewayable_id', $gatewayData->id);
                    Arr::set($log, 'gatewayable_type', $gatewayModel);
                    return $log;
               }, $dispatchLogs);
          }
     }

     public function distributeGatewaysEqually(array $dispatchLogs, Collection|AndroidSim|Gateway $gatewayData, string $gatewayModel): array
     {
          $gatewayCount       = $gatewayData->count();
          $logsCount          = count($dispatchLogs);
          $baseLogsPerGateway = (int) floor($logsCount / $gatewayCount);
          $extraLogs          = $logsCount % $gatewayCount; 

          $gatewayIndex = 0;
          $logsAssigned = 0;

          return array_map(function ($log) use ($gatewayData, $gatewayModel, &$gatewayIndex, &$logsAssigned, $baseLogsPerGateway, $extraLogs, $gatewayCount) {
               
               $logsForThisGateway = $baseLogsPerGateway + ($gatewayIndex < $extraLogs ? 1 : 0);
               $currentGateway = $gatewayData[$gatewayIndex];
               Arr::set($log, 'gatewayable_id', $currentGateway->id);
               Arr::set($log, 'gatewayable_type', $gatewayModel);

               $logsAssigned++;
               if ($logsAssigned >= $logsForThisGateway && $gatewayIndex < $gatewayCount - 1) {
                    $gatewayIndex++;
                    $logsAssigned = 0;
               }

               return $log;
          }, $dispatchLogs);
     }

     /**
      * testEmailGateway
      *
      * @param User|null $user
      * 
      * @return JsonResponse
      */
     public function testEmailGateway(?User $user = null, ?int $gatewayId = null): JsonResponse|ApplicationException {

          if($gatewayId) {
               $gateway = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::EMAIL,
                    type: null,
                    column: "id",
                    value: $gatewayId,
                    user: $user);
               if(!$gateway) throw new ApplicationException('Selected email gateway not found');
          } else {
               $gateway = $this->gatewayManager->getSpecificGateway(
                    channel: ChannelTypeEnum::EMAIL,
                    type: null,
                    column: "is_default",
                    value: StatusEnum::TRUE->status(),
                    user: $user);
               if(!$gateway) throw new ApplicationException('No default email gateway found');
          }

          $template = $this->getSpecificLogByColumn(
               model: new Template(), 
               column: "slug",
               value: DefaultTemplateSlug::TEST_MAIL->value,
               attributes: [
                    "user_id" => null,
                    "channel" => ChannelTypeEnum::EMAIL->value,
                    "default" => true,
                    "status"  => Status::ACTIVE
               ]
          );
          if(!$template) throw new ApplicationException('Template could not be found');

          $mailCode = [
               "name"    => site_settings(SettingKey::SITE_NAME->value, "Xsender"),
               "time"    => Carbon::now()->toDateTimeString()
          ];

          $messageBody  = $this->templateService->processTemplate(
               template: $template, 
               variables: $mailCode);
               
          $response = $this->sendMail->send(
               $gateway,
               request()->input("email"), 
               Arr::get($template->template_data, "subject"), 
               $messageBody);
               
          if($response) {

               return response()->json([
                    'reload'    => false,
                    'status'    => true,
                    'message'   => translate('Successfully sent mail to: ').request()->input("email"). translate(' via: '). $gateway->name. translate(" Gateway"),
               ]);
          }

          // Return the actual error from the SMTP/provider for easier debugging
          $errorDetail = $this->sendMail->getLastError();
          return response()->json([
               'reload'    => false,
               'status'    => false,
               'message'   => $errorDetail
                    ? translate("Failed to send via '") . $gateway->name . "': " . $errorDetail
                    : translate("Mail Configuration Error, Please check your '").$gateway->name.translate("' gateway configuration properly"),
          ]);
     }

     public function whatsappDeviceStatusUpdate(Request $request, ?User $user = null) {

          $gateway = Gateway::when($user, fn(Builder $q): Builder =>
                                        $q->where("user_id", $user->id), 
                                             fn(Builder $q): Builder =>
                                                  $q->whereNull("user_id"))
                                    ->select(["id", "name", "meta_data"])
                                    ->where("channel", ChannelTypeEnum::WHATSAPP)
                                    ->where("type", WhatsAppGatewayTypeEnum::NODE)
                                    ->where('id', $request->input('id'))
                                    ->first();
          if(!$gateway) throw new ApplicationException("Invalid whatsapp device",    Response::HTTP_NOT_FOUND);   

          list($gateway, $message) = $this->nodeService->sessionStatusUpdate($gateway, $request->input('status'));

          $gateway->update();
          return $message;
     }

     /**
      * calculateDispatchDelay
      *
      * @param string|int $gatewayId
      * @param ChannelTypeEnum|string $channel
      * @param int $messagesToSend
      * @param string $dispatchType
      * @param int|null $userId
      * 
      * @return float
      */
     public function calculateDispatchDelay(string|int $gatewayId, ChannelTypeEnum|string $channel, int $messagesToSend, string $dispatchType, ?int $userId = null): float
     {
          $gateway = Gateway::active()
                                   ->where("id",$gatewayId)
                                   ->first();        
          
          if (!$gateway) throw new ApplicationException("Gateway not found", Response::HTTP_NOT_FOUND);
          
          $channel = $channel instanceof ChannelTypeEnum ? $channel : ChannelTypeEnum::from($channel);
          $currentCount = DispatchDelay::where('gateway_id', $gatewayId)
                                             ->where("dispatch_type", $dispatchType)
                                             ->where('channel', $channel->value)
                                             ->when($userId, fn(Builder $query) :Builder =>
                                                  $query->where('user_id', $userId), fn(Builder $query) :Builder =>
                                                       $query->whereNull('user_id'))
                                             ->latest('applies_from')
                                             ->count();

          $delay         = 0.0;
          $remaining     = $messagesToSend;
          $count         = $currentCount;

          
          while ($remaining > 0) {

               $window        = $gateway->reset_after_count > 0 ? $gateway->reset_after_count : PHP_INT_MAX;
               $windowLeft    = $window - ($count % $window);
               $batch         = min($remaining, $windowLeft);
               $batchStart    = $count;
               $batchEnd      = $count + $batch;
               
               if ($gateway->per_message_min_delay > 0 || $gateway->per_message_max_delay > 0) {

                    $min = max(0, (float)$gateway->per_message_min_delay);
                    $max = max($min, (float)$gateway->per_message_max_delay);
                    for ($i = 0; $i < $batch; $i++) {
                         $delay += mt_rand((int)($min * 1000), (int)($max * 1000)) / 1000.0;
                    }
               }
               if ($gateway->delay_after_count > $remaining && $gateway->delay_after_duration > 0) {

                    $cycles = intdiv($batchEnd, $gateway->delay_after_count) - intdiv($batchStart, $gateway->delay_after_count);
                    
                    if ($cycles > 0) {
                         $delay += $cycles * (float)$gateway->delay_after_duration;
                    }
               }
               $count         += $batch;
               $remaining     -= $batch;

               if ($gateway->reset_after_count > 0 && $count >= $gateway->reset_after_count) {
                    $count = $count % $gateway->reset_after_count;
               }
          }
          return $delay;
     }

     ## -------------------------------------------------- ##
     ## WhatsApp Cloud API Embedded Login System Functions ##
     ## -------------------------------------------------- ##

     /**
      * Initiate WhatsApp Cloud API Embedded Signup
      *
      * Supports both legacy mode (using site settings) and Meta 2025 mode (using MetaConfiguration with config_id)
      *
      * @param Request $request
      * @param User|null $user
      * @return JsonResponse
      */
     public function initiateEmbeddedSignup(Request $request, ?User $user = null): JsonResponse
     {
          $metaConfig = null;

          if ($user) {
               // User panel: always use the platform's default Meta App config (plug-and-play)
               // Users don't choose which Meta App to use — the admin configures this
               $metaConfig = $this->getDefaultMetaConfiguration();
          } else {
               // Admin panel: allow selecting a specific config, or use default
               $configId = $request->input('meta_configuration_id');
               if ($configId) {
                    $metaConfig = MetaConfiguration::where('id', $configId)
                         ->where('status', 'active')
                         ->first();

                    if (!$metaConfig) {
                         return response()->json([
                              'success' => false,
                              'message' => translate('Selected Meta configuration not found or inactive.')
                         ], 404);
                    }
               } else {
                    $metaConfig = $this->getDefaultMetaConfiguration();
               }
          }

          // Determine callback route based on user type
          $callbackRoute = $user
               ? 'user.gateway.whatsapp.cloud.api.embedded.callback'
               : 'admin.gateway.whatsapp.cloud.api.embedded.callback';

          // Use Meta 2025 compliant flow if MetaConfiguration is available
          if ($metaConfig) {
               $result = $this->initiateEmbeddedSignupWithConfig($request, $metaConfig, $callbackRoute, $user);
          } else {
               // Legacy mode - use site settings
               $result = $this->initiateMetaEmbeddedSignup($request, $callbackRoute);
          }

          return response()->json([
               'success' => Arr::get($result, 'success'),
               'signup_url' => Arr::get($result, 'signup_url'),
               'onboarding_id' => Arr::get($result, 'onboarding_id'),
               'message' => Arr::get($result, 'message'),
               'mode' => $metaConfig ? 'meta_2025' : 'legacy'
          ], Arr::get($result, 'success') ? 200 : 400);
     }

     /**
      * Get available Meta configurations for embedded signup
      *
      * @param User|null $user
      * @return JsonResponse
      */
     public function getAvailableMetaConfigurations(?User $user = null): JsonResponse
     {
          $configs = MetaConfiguration::where('status', 'active')
               ->select(['id', 'uid', 'name', 'environment', 'is_default', 'api_version'])
               ->get()
               ->map(function ($config) {
                    return [
                         'id' => $config->id,
                         'uid' => $config->uid,
                         'name' => $config->name,
                         'environment' => $config->environment,
                         'is_default' => $config->is_default,
                         'api_version' => $config->api_version,
                         'has_config_id' => !empty($config->config_id),
                    ];
               });

          $hasLegacyConfig = !empty(site_settings(SettingKey::META_APP_ID->value))
               && !empty(site_settings(SettingKey::META_APP_SECRET->value));

          return response()->json([
               'success' => true,
               'configurations' => $configs,
               'has_legacy_config' => $hasLegacyConfig,
               'recommended' => $configs->firstWhere('is_default', true) ?? ($hasLegacyConfig ? 'legacy' : null)
          ]);
     }

     /**
      * Summary of handleEmbeddedCallback
      * @param \Illuminate\Http\Request $request
      * @param \App\Models\User|null $user
      * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
      */
     public function handleEmbeddedCallback(Request $request, User|null $user = null): View
     {
          if ($request->has('error')) {
               // Log::error('OAuth error: ' . json_encode($request->all()));
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => translate('Embedded signup failed: ') . ($request->error_description ?? $request->error)
               ]);
          }

          $stateResult = $this->validateState($request->state);
          if (!Arr::get($stateResult, 'success')) {
               // Log::error('State validation failed');
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => Arr::get($stateResult, 'message', translate('Invalid or expired signup session'))
               ]);
          }

          $state         = Arr::get($stateResult, 'data');

          // Resolve MetaConfiguration from state (if initiated via Meta 2025 compliant flow)
          $metaConfig = null;
          $metaConfigId = Arr::get($state, 'meta_configuration_id');
          if ($metaConfigId) {
               $metaConfig = MetaConfiguration::find($metaConfigId);
          }

          $callbackRoute = Arr::get($state, 'user_type') === 'user'
               ? 'user.gateway.whatsapp.cloud.api.embedded.callback'
               : 'admin.gateway.whatsapp.cloud.api.embedded.callback';
          $tokenResult   = $this->exchangeCodeForToken($request->code, $metaConfig, $callbackRoute);
          if (!Arr::get($tokenResult, 'success')) {
               // Log::error('Token exchange failed');
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => Arr::get($tokenResult, 'message', translate('Failed to exchange code for token'))
               ]);
          }

          $tokenData     = Arr::get($tokenResult, 'data');
          $accountResult = $this->getCompleteAccountInfo($tokenData, $metaConfig);
          if (!Arr::get($accountResult, 'success')) {
               // Log::error('Account info fetch failed');
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => Arr::get($accountResult, 'message', translate('Failed to get account info'))
               ]);
          }

          $accountInfo        = Arr::get($accountResult, 'data');
          $gatewayDataResult  = $this->prepareGatewayData($state, $tokenData, $accountInfo, $user);
          if (!Arr::get($gatewayDataResult, 'success')) {
               // Log::error('Gateway data preparation failed');
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => Arr::get($gatewayDataResult, 'message', translate('Failed to prepare gateway data'))
               ]);
          }

          $gatewayData   = Arr::get($gatewayDataResult, 'data');
          $saveResult    = $this->saveGatewayData($gatewayData);

          if (!Arr::get($saveResult, 'success')) {
               // Log::error('Gateway save failed');
               return view('partials.pop-up.callback', [
                    'success' => false,
                    'message' => Arr::get($saveResult, 'message', translate('Failed to save gateway'))
               ]);
          }
          return view('partials.pop-up.callback', [
               'success' => true,
               'message' => translate('WhatsApp Business Account connected successfully')
          ]);
     }

     /**
      * Validate state for embedded signup
      *
      * @param string|null $stateParam
      * @return array
      */
     private function validateState(string|null $stateParam): array
     {
          try {
               
               $state = json_decode(base64_decode($stateParam), true);
               // Log::info('Decoded state: ', $state ?? []);

               if (!$state || !is_array($state)) {
                    // Log::warning('Invalid state structure');
                    return [
                         'success' => false, 
                         'message' => translate('Invalid state structure')
                    ];
               }

               $required = ['user_type', 'timestamp', 'nonce'];
               $missing  = collect($required)->filter(fn($field) => !array_key_exists($field, $state))->values()->all();
               
               if (!empty($missing)) {
                    // Log::warning('Missing fields: ' . implode(', ', $missing));
                    return [
                         'success' => false, 
                         'message' => translate('Missing field: ') . implode(', ', $missing)];
               }

               if (now()->timestamp - Arr::get($state, 'timestamp') > 3600) {
                    // Log::warning('Timestamp expired');
                    return [
                         'success' => false, 
                         'message' => translate('Timestamp expired')
                    ];
               }

               return [
                    'success' => true, 
                    'data' => $state
               ];

          } catch (Exception $e) {
               // Log::error('State validation error: ' . $e->getMessage());
               return [
                    'success' => false, 
                    'message' => translate('State validation error: ') . $e->getMessage()
               ];
          }
     }

     /**
      * Exchange code for token
      *
      * @param string $code
      * @return array
      */
     private function exchangeCodeForToken(string $code, ?MetaConfiguration $metaConfig = null, string $callbackRoute = 'admin.gateway.whatsapp.cloud.api.embedded.callback'): array
     {
          // Use MetaConfiguration credentials if available, otherwise fall back to site_settings
          $clientId     = $metaConfig?->app_id ?: site_settings(SettingKey::META_APP_ID->value);
          $clientSecret = $metaConfig?->app_secret ?: site_settings(SettingKey::META_APP_SECRET->value);

          if (empty($clientId) || empty($clientSecret)) {
               return [
                    'success' => false,
                    'message' => translate('Meta App ID or App Secret not configured. Please check your WhatsApp Cloud API settings.')
               ];
          }

          $params = [
               'client_id'         => $clientId,
               'client_secret'     => $clientSecret,
               'code'              => $code,
               'redirect_uri'      => route($callbackRoute),
          ];

          return $this->makeMetaApiRequest(MetaApiEndpoints::OAUTH_ACCESS_TOKEN, $params, 'post');
     }

     /**
      * Get complete account information
      *
      * @param array $tokenData
      * @return array
      */
     private function getCompleteAccountInfo(array $tokenData, ?MetaConfiguration $metaConfig = null): array
     {
          $accessToken = Arr::get($tokenData, 'access_token');

          // Step 1: Get granular_scopes from debug_token (Meta's current recommended approach)
          // This returns the WABA IDs the user authorized via config_id during embedded signup
          $appId     = $metaConfig?->app_id ?: site_settings(SettingKey::META_APP_ID->value);
          $appSecret = $metaConfig?->app_secret ?: site_settings(SettingKey::META_APP_SECRET->value);
          $appAccessToken = $appId . '|' . $appSecret;

          $debugResult = $this->makeMetaApiRequest(MetaApiEndpoints::OAUTH_DEBUG_TOKEN, [
               'input_token'  => $accessToken,
               'access_token' => $appAccessToken,
          ]);

          $wabaIds = [];
          if (Arr::get($debugResult, 'success')) {
               $granularScopes = Arr::get($debugResult, 'data.data.granular_scopes', []);
               foreach ($granularScopes as $scope) {
                    if (in_array(Arr::get($scope, 'scope'), ['whatsapp_business_management', 'whatsapp_business_messaging'])) {
                         $wabaIds = array_merge($wabaIds, Arr::get($scope, 'target_ids', []));
                    }
               }
               $wabaIds = array_unique($wabaIds);
          }

          // Step 2: Fallback — try the legacy whatsapp_business_accounts field
          // (works for older apps where user IS the WABA owner)
          $businessAccount = null;
          $userData = null;

          if (empty($wabaIds)) {
               $userParams = [
                    'access_token' => $accessToken,
                    'fields'       => 'id,name',
               ];
               $userResult = $this->makeMetaApiRequest(MetaApiEndpoints::USER_INFO, $userParams);
               $userData = Arr::get($userResult, 'data');

               // Try legacy field
               $legacyParams = [
                    'access_token' => $accessToken,
                    'fields'       => 'whatsapp_business_accounts{id,name,currency,timezone_id,message_template_namespace,account_review_status}',
               ];
               $legacyResult = $this->makeMetaApiRequest(MetaApiEndpoints::USER_INFO, $legacyParams);
               if (Arr::get($legacyResult, 'success')) {
                    $legacyWabas = Arr::get($legacyResult, 'data.whatsapp_business_accounts.data', []);
                    if (!empty($legacyWabas)) {
                         $businessAccount = $legacyWabas[0];
                    }
               }

               if (!$businessAccount) {
                    return [
                         'success' => false,
                         'message' => 'No WhatsApp Business Account found. Please ensure you completed the embedded signup correctly and the Meta App has Tech Provider permissions.'
                    ];
               }
          } else {
               // Use first WABA from granular_scopes — fetch its details
               $wabaId = $wabaIds[0];
               $wabaDetailParams = [
                    'waba_id'      => $wabaId,
                    'access_token' => $accessToken,
                    'fields'       => 'id,name,currency,timezone_id,message_template_namespace,account_review_status',
               ];
               $wabaResult = $this->makeMetaApiRequest(MetaApiEndpoints::WABA_INFO, $wabaDetailParams);

               if (!Arr::get($wabaResult, 'success')) {
                    return ['success' => false, 'message' => 'Failed to fetch WABA details: ' . Arr::get($wabaResult, 'message', 'Unknown error')];
               }

               $businessAccount = Arr::get($wabaResult, 'data');
               $userData = ['id' => null, 'name' => Arr::get($businessAccount, 'name')];
          }

          // Step 3: Get phone numbers for the WABA
          $phoneParams = [
               'access_token'        => $accessToken,
               'business_account_id' => Arr::get($businessAccount, 'id'),
               'fields'              => 'id,display_phone_number,verified_name,quality_rating,status',
          ];

          $phoneResult = $this->makeMetaApiRequest(MetaApiEndpoints::PHONE_NUMBERS, $phoneParams);

          if (!Arr::get($phoneResult, 'success')) {
               return $phoneResult;
          }

          $phoneNumbers = Arr::get($phoneResult, 'data.data', []);

          return [
               'success' => true,
               'data' => [
                    'user'              => $userData,
                    'business_account'  => $businessAccount,
                    'phone_numbers'     => $phoneNumbers,
               ]
          ];
     }

     /**
      * Summary of prepareGatewayData
      * @param array $state
      * @param array $tokenData
      * @param array $accountInfo
      * @param \App\Models\User|null $user
      * @return array
      */
     private function prepareGatewayData(array $state, array $tokenData, array $accountInfo, User|null $user = null): array
     {
          try {
               $businessAccount    = Arr::get($accountInfo, 'business_account');
               $phoneNumbers       = Arr::get($accountInfo, 'phone_numbers');
               $primaryPhone       = Arr::first($phoneNumbers);
               
               $metaData = [
                    'user_access_token'            => Arr::get($tokenData, 'access_token'),
                    'whatsapp_business_account_id' => Arr::get($businessAccount, 'id'),
               ];
               
               if ($primaryPhone) {
                    $metaData['phone_number_id'] = Arr::get($primaryPhone, 'id');
               }

               $gatewayData = [
                    'user_id' => @$user?->id,
                    'type'    => WhatsAppGatewayTypeEnum::CLOUD->value,
                    'channel' => ChannelTypeEnum::WHATSAPP->value,
                    'name'    => Arr::get($primaryPhone, 'verified_name', Arr::get($businessAccount, 'name', 'WhatsApp Gateway')),
                    'address'      => Arr::get($primaryPhone, 'display_phone_number'),
                    'meta_data'    => json_encode($metaData),
                    'payload'      => json_encode([
                         'token_data' => $tokenData,
                         'account_info' => $accountInfo,
                         'embedded_signup_completed_at' => now()->toISOString(),
                         'state_info' => $state,
                    ]),
                    'api_version'            => site_settings(SettingKey::META_API_VERSION->value, MetaApiEndpoints::CURRENT_VERSION),
                    'setup_method'           => 'embedded',
                    'bulk_contact_limit'     => 1,
                    'last_sync_at'           => null,
               ];

               return [
                    'success' => true, 
                    'data' => $gatewayData
               ];
          } catch (Exception $e) {
               // Log::error('Gateway preparation error: ' . $e->getMessage());
               return [
                    'success' => false, 
                    'message' => $e->getMessage()
               ];
          }
     }

     /**
      * Save gateway data
      *
      * @param array $gatewayData
      * @return array
      */
     private function saveGatewayData(array $gatewayData): array
     {
          try {
               $gateway = new Gateway();
               $gateway->fill($gatewayData);
               $gateway->save();

               // Log::info('Gateway saved to database with ID: ' . $gateway->id);
               return [
                    'success' => true
               ];
          } catch (Exception $e) {
               // Log::error('Gateway save error: ' . $e->getMessage());
               return [
                    'success' => false, 
                    'message' => $e->getMessage()
               ];
          }
     }
}