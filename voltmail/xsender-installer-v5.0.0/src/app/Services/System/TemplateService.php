<?php

namespace App\Services\System;

use App\Models\User;
use App\Models\Gateway;
use App\Models\Template;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Enums\Common\Status;
use Illuminate\Http\Response;
use App\Managers\TemplateManager;
use App\Enums\DefaultTemplateSlug;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Exceptions\ApplicationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\System\TemplateApprovalStatusEnum;

class TemplateService
{ 
     public $templateManager;
     public function __construct() {

          $this->templateManager = new TemplateManager();
     }

     /**
      * loadIndexView
      *
      * @param ChannelTypeEnum $channel
      * @param string|int|null $cloudId
      * @param User|null $user
      *
      * @return View
      */
     public function loadIndexView(ChannelTypeEnum $channel, string|int|null $cloudId, ?User $user = null): View {

          $title = translate("{$channel->value} Template List");

          // Initialize variables for WhatsApp
          $adminNodeTemplates = null;
          $adminCloudTemplates = null;
          $userNodeTemplates = null;
          $userCloudTemplates = null;
          $nodeTemplates = null;

          // For WhatsApp channel, separate Admin/User and Node/Cloud templates
          if ($channel == ChannelTypeEnum::WHATSAPP) {
               if ($user) {
                    // User Panel: Show only templates belonging to this user
                    // Node templates: WhatsApp templates WITHOUT cloud_id, owned by this user
                    $nodeTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNull("cloud_id")
                         ->where("user_id", $user->id)
                         ->where("default", false)
                         ->where("plugin", false)
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'node_page')
                         ->appends(request()->all());

                    // Cloud API templates: WhatsApp templates WITH cloud_id, owned by this user
                    $adminTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNotNull("cloud_id")
                         ->where("user_id", $user->id)
                         ->where("default", false)
                         ->where("plugin", false)
                         ->when($cloudId, fn(Builder $q): Builder => $q->where("cloud_id", $cloudId))
                         ->with('cloudApi')
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'cloud_page')
                         ->appends(request()->all());
               } else {
                    // Admin Panel: Show Admin and User templates separately
                    // Admin Node templates: WhatsApp templates WITHOUT cloud_id, admin-owned
                    $adminNodeTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNull("cloud_id")
                         ->whereNull("user_id")
                         ->where("default", false)
                         ->where("plugin", false)
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'admin_node_page')
                         ->appends(request()->all());

                    // Admin Cloud API templates: WhatsApp templates WITH cloud_id, admin-owned
                    $adminCloudTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNotNull("cloud_id")
                         ->whereNull("user_id")
                         ->where("default", false)
                         ->where("plugin", false)
                         ->when($cloudId, fn(Builder $q): Builder => $q->where("cloud_id", $cloudId))
                         ->with('cloudApi')
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'admin_cloud_page')
                         ->appends(request()->all());

                    // User Node templates: WhatsApp templates WITHOUT cloud_id, user-owned
                    $userNodeTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNull("cloud_id")
                         ->whereNotNull("user_id")
                         ->where("default", false)
                         ->where("plugin", false)
                         ->with('user')
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'user_node_page')
                         ->appends(request()->all());

                    // User Cloud API templates: WhatsApp templates WITH cloud_id, user-owned
                    $userCloudTemplates = Template::where("global", false)
                         ->where("channel", $channel)
                         ->whereNotNull("cloud_id")
                         ->whereNotNull("user_id")
                         ->where("default", false)
                         ->where("plugin", false)
                         ->when($cloudId, fn(Builder $q): Builder => $q->where("cloud_id", $cloudId))
                         ->with(['cloudApi', 'user'])
                         ->search(['name'])
                         ->latest()
                         ->paginate(site_settings('paginate_number', 10), ['*'], 'user_cloud_page')
                         ->appends(request()->all());

                    // For backward compatibility, set adminTemplates to adminCloudTemplates
                    $adminTemplates = $adminCloudTemplates;
                    $nodeTemplates = $adminNodeTemplates;
               }
          } else {
               // For non-WhatsApp channels, use the original logic
               $adminTemplates = $this->templateManager->logs(
                    channel: $channel,
                    paginated: true,
                    adminSpecific: true,
                    cloudId: $cloudId,
                    user: $user);
          }

          $userTemplates = $this->templateManager->logs(
               channel: $channel,
               paginated: true,
               userSpecific: true,
               cloudId: $cloudId,
               user: $user);

          $defaultTemplates = $this->templateManager->logs(
               channel: $channel,
               paginated: true,
               adminSpecific: true,
               isDefault: true,
               user: $user);

          $globalTemplate     = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "slug",
               value: DefaultTemplateSlug::GLOBAL_TEMPLATE->value,
               attributes: [
                    "global" => true,
                    "status" => Status::ACTIVE,
                    "user_id" => null,
               ]
          );

          $panelType = $user ? "user" : "admin";

          return view("{$panelType}.template.index", compact(
               'title',
               'adminTemplates', 'userTemplates', 'defaultTemplates', 'globalTemplate',
               'channel', 'cloudId', 'nodeTemplates',
               'adminNodeTemplates', 'adminCloudTemplates', 'userNodeTemplates', 'userCloudTemplates'));
     }

     /**
      * loadCreateView
      *
      * @param ChannelTypeEnum $channel
      * @param User|null $user
      * 
      * @return View
      */
     public function loadCreateView(ChannelTypeEnum $channel, ?User $user = null): View {

          $title = translate("Create {$channel->value} Template");
          $pluginTemplates = $this->templateManager->logs(
               channel: $channel, 
               adminSpecific: true, 
               isPlugin: true);

          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.template.create", compact(
               'title', 
               'pluginTemplates', 'channel'));
     }

     /**
      * loadEditView
      *
      * @param string $uid
      * @param User|null $user
      * 
      * @return View
      */
     public function loadEditView(string $uid, ?User $user = null): View {

          $template = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "uid",
               value: $uid,
               attributes: [
                    "plugin" => false,
                    "default" => false,
                    "global" => false,
                    "user_id" => @$user?->id,
               ]
          );
          if(!$template) throw new ApplicationException("Invalid Template");
          $channel = $template->channel;
          $title = translate("Create {$channel->value} Template");
          $pluginTemplates = $this->templateManager->logs(
               channel: $channel, 
               adminSpecific: true, 
               isPlugin: true,
               user: $user);

          $panelType = $user ? "user" : "admin";
          return view("{$panelType}.template.edit", compact(
               'title', 
               'pluginTemplates', 'channel', 'template'));
     }

     /**
      * save
      *
      * @param array $data
      * @param string|null $uid
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function save(array $data, string|null $uid = null, ?User $user = null): RedirectResponse {

          if($uid) $data = Arr::set($data, "uid", $uid);
          if($user) {
               $data = Arr::set($data, "user_id", $user->id);
               $data = Arr::set($data, "approval_status", TemplateApprovalStatusEnum::PENDING->value);
          } 
          
          Template::updateOrCreate([
               "uid" => $uid,
               "user_id" => @$user?->id
          ], $this->prepEmailData($data));
          $notify[] = ['success', translate("Template updated successfully")];
          return back()->withNotify($notify);
     }

     /**
      * updateApproval
      *
      * @param array $data
      * 
      * @return RedirectResponse
      */
     public function updateApproval(array $data): RedirectResponse {

          $template = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "uid",
               value: Arr::get($data, "uid")
          );
          if(!$template) throw new ApplicationException("Invalid Template", Response::HTTP_NOT_FOUND);
          $template->approval_status = Arr::get($data, "approval_status");
          $template->remarks = Arr::get($data, "remarks");
          $template->save();
          $notify[] = ['success', translate("Template updated successfully")];
          return back()->withNotify($notify);
     }

     /**
      * destroyTemplate
      *
      * @param string|null|null $uid
      * @param User|null $user
      * 
      * @return RedirectResponse
      */
     public function destroyTemplate(string|null $uid = null, ?User $user = null): RedirectResponse { 

          $template = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "uid",
               value: $uid
          );
          if(!$template) throw new ApplicationException("Invalid Template", Response::HTTP_NOT_FOUND);
          $template->delete();
          $notify[] = ['success', translate("Template deleted successfully")];
          return back()->withNotify($notify);
     }

     /**
      * prepEmailData
      *
      * @param array $template
      * 
      * @return array
      */
     public function prepEmailData(array $template): array
     {
          $templateJson = Arr::get($template, 'template_json');
          $templateHtml = Arr::get($template, 'template_html');
          $templateData = Arr::get($template, 'template_data');

          if (!is_null($templateJson) && !is_null($templateHtml)) {
               $templateData = [
                    'mail_body' => $templateHtml,
                    'template_json' => $templateJson,
               ];
          }

          $data = Arr::except($template, ['template_data', 'template_json', 'template_html', "_method", "uid"]);
          $data = Arr::set($data, 'template_data', $templateData);

          if(Arr::has($template, "name")) {

               $data = Arr::add($data, 'slug', strtoupper(textFormat([' '], Arr::get($template, 'name', ''), '_')));
          }
          return $data;
     }

     /**
      * returnTemplateData
      *
      * @param string|int|null|null $uid
      * @param User|null $user
      * 
      * @return array
      */
     public function returnTemplateData(string|int|null $uid = null, ?User $user = null) : array {

          $template = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "uid",
               value: $uid,
               attributes: [
                    "plugin" => true,
                    "user_id" => @$user?->id
               ]
          );

          return $template->template_data;
     }

     /**
      * returnEditTemplateData
      *
      * @param string|int|null|null $uid
      * @param User|null $user
      * 
      * @return string
      */
     public function returnEditTemplateData(string|int|null $uid = null, ?User $user = null) : string {

          $template = $this->templateManager->getSpecificLogByColumn(
               model: new Template(),
               column: "uid",
               value: $uid,
               attributes: [
                    "user_id" => @$user?->id
               ]
          );

          return Arr::get($template->template_data, "template_json");
     }

     /**
      * processTemplate
      *
      * @param Template $template
      * @param array $variables
      * 
      * @return string
      */
     public function processTemplate(Template $template, array $variables): string
     {
          $mailBody = Arr::get($template->template_data, 'mail_body', '');

          return preg_replace_callback(
               '/{{(\w+)}}/',
               function ($matches) use ($variables) {
                   $key = $matches[1]; 
                   return Arr::get($variables, $key, $matches[0]); 
               },
               $mailBody
           );
     }
     
     
     /**
      * getChannelSpecificTemplates
      *
      * @param ChannelTypeEnum $channel
      * @param User|null $user
      * @param int|string|null $cloudId - For WhatsApp Cloud API templates
      *
      * @return Collection
      */
     public function getChannelSpecificTemplates(ChannelTypeEnum $channel, ?User $user = null, int|string|null $cloudId = null): Collection {

          $query = Template::when($user, fn(Builder $q): Builder =>
                                   $q->where("user_id", $user->id),
                                        fn(Builder $q): Builder =>
                                             $q->whereNull("user_id"))
                              ->where([
                                   'status'  => Status::ACTIVE,
                                   'channel' => $channel,
                                   'plugin'  => false,
                                   'default' => false,
                                   'global'  => false,
                              ]);

          // For WhatsApp channel, filter by cloud_id
          if ($channel == ChannelTypeEnum::WHATSAPP) {
               if ($cloudId) {
                    // Return Cloud API templates for specific cloud account
                    $query->where('cloud_id', $cloudId);
               } else {
                    // Return Node templates (templates without cloud_id)
                    $query->whereNull('cloud_id');
               }
          }

          return $query->latest()->get();
     }


     ## Old functions

     public function whatsappCloudApiData($request) {

          $itemId = $request->input("itemId");
          Template::where('cloud_id', $itemId)->delete();
  
          $whatsapp_business_account = Gateway::find($itemId);
          $credentials 			  = $whatsapp_business_account->meta_data;
          $token 				  = $credentials['user_access_token'];
          $waba_id 				  = $credentials['whatsapp_business_account_id'];
          $apiVersion              = $whatsapp_business_account->getApiVersion();
          $url 					   = "https://graph.facebook.com/{$apiVersion}/$waba_id/message_templates";
          $queryParams = [
              'fields' => 'name,category,language,quality_score,components,status',
              'limit'  => 100
          ];
  
          $headers = [
              'Authorization' => "Bearer $token"
          ];
  
          $response 	  = Http::withHeaders($headers)->get($url, $queryParams);
          $responseData = $response->json();
  
          return $responseData;
  
     }

     public function saveWhatsappTemplates(array $data, $request, int|null $user_id = null) {

          foreach ($data["data"] as $template) {
                  
              $data = [
  
                  'user_id'       => $user_id,
                  'channel'       => ChannelTypeEnum::WHATSAPP->value,
                  'cloud_id'      => $request->input("itemId"),
                  'name' 	    => $template["name"],
                  'plugin'        => false,
                  'global'        => false,
                  'default'       => false,
                  'slug'          => strtoupper(textFormat([' '], $template["name"], '_')), 
                  'template_data' => $template,
                  'status'        => $template["status"] == 'APPROVED' 
                                        ? Status::ACTIVE->value 
                                        : Status::INACTIVE->value
              ];
              
              $this->addCloudTemplate($data, $request);
          }
  
          return $data;
     }

     public function addCloudTemplate($data, $request, $user_id = null) {

          if($user_id) {
  
              $data["user_id"] = $user_id;
          }
  
          $template = Template::updateOrCreate([
  
              'id' => $request->input("id"),
              
          ], $data);
  
          return $template;
      }
}