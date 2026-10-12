<?php

namespace App\Http\Controllers\Admin;

use App\Enums\System\ChannelTypeEnum;
use App\Exceptions\ApplicationException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\TemplateApprovalRequest;
use App\Http\Requests\TemplateRequest;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Services\System\TemplateService;
use App\Traits\ModelAction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TemplateController extends Controller
{
    use ModelAction;

    public $templateService;
    public function __construct() {

        $this->templateService = new TemplateService();
    }
    /**
     * index
     *
     * @param string $channel
     * @param int|string|null|null $cloudId
     * 
     * @return View
     */
    public function index(string $channel, int|string|null $cloudId = null): View
    {
        Session::put("menu_active", true);
        return $this->templateService->loadIndexView(channel: ChannelTypeEnum::from($channel), cloudId: $cloudId);
    }

    /**
     * create
     *
     * @param string $channel
     * 
     * @return View
     */
    public function create(string $channel): View
    {
        Session::put("menu_active", true);
        return $this->templateService->loadCreateView(channel: ChannelTypeEnum::from($channel));
    }

    /**
     * store
     *
     * @param TemplateRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(TemplateRequest $request): RedirectResponse
    {
        try {
            
            $data = $request->all();
            unset($data["_token"]);
            return $this->templateService->save($data);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * edit
     *
     * @param string $uid
     * 
     * @return View
     */
    public function edit(string $uid): View
    {
        try {
            Session::put("menu_active", true);
            return $this->templateService->loadEditView(uid: $uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * update
     *
     * @param TemplateRequest $request
     * @param string $uid
     * 
     * @return RedirectResponse
     */
    public function update(TemplateRequest $request, string $uid): RedirectResponse
    {
        try {
            
            $data = $request->all();
            unset($data["_token"]);
            
            return $this->templateService->save(data: $data, uid: $uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * approve
     *
     * @param TemplateApprovalRequest $request
     * 
     * @return RedirectResponse
     */
    public function approve(TemplateApprovalRequest $request): RedirectResponse {

        try {
            
            $data = $request->all();
            unset($data["_token"]);
            
            return $this->templateService->updateApproval(data: $data);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

     /**
     * updateStatus
     *
     * @param Request $request
     * 
     * @return string
     */
    public function updateStatus(Request $request): string
    {
        try {
            $this->validateStatusUpdate(
                request: $request,
                tableName: 'templates', 
                isJson: true,
                keyColumn: 'uid'
            );

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: [
                    'message'               => translate('Template status updated successfully'),
                    'model'                 => new Template,
                    'column'                => $request->input('column'),
                    'filterable_attributes' => [
                        'uid' => $request->input('uid')
                    ],
                    'reload'                => true
                ]
            );

            return $notify;

        } catch (Exception $e) {
            
            return response()->json([
                'status'    => false,
                'message'   => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    public function destroy(string $uid)
    {
        try {
            return $this->templateService->destroyTemplate($uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * templateJson
     *
     * @param string|int|null|null $uid
     * 
     * @return [type]
     */
    public function templateJson(string|int|null $uid = null) {

        return $this->templateService->returnTemplateData($uid);
    }

   
    /**
     * editTemplateJson
     *
     * @param string|int|null|null $uid
     * 
     * @return string
     */
    public function editTemplateJson(string|int|null $uid = null): string {

        return $this->templateService->returnEditTemplateData($uid);
    }

    /**
     * fetch
     *
     * @param string $channel
     *
     * @return JsonResponse
     */
    public function fetch(string $channel): JsonResponse {

        $cloudId = request()->query('cloud_id');
        $templates = $this->templateService->getChannelSpecificTemplates(
            channel: ChannelTypeEnum::from(value: $channel),
            cloudId: $cloudId
        );
		return response()->json(['templates' => $templates]);
	}

    /**
     * emailTemplates
     *
     * @return JsonResponse
     */
    public function emailTemplates(): JsonResponse {

        $templates = $this->templateService->getChannelSpecificTemplates(channel: ChannelTypeEnum::EMAIL);
        
        return response()->json([
            'view' => view('admin.email_template.data',[
                'templates' => $templates
                ]
            )->render()
        ],'200' );
        // if($request->ajax()) {
        // }
    }


    ## Old Function

    public function refresh(Request $request) {

		$status  = true;
		$reload  = true;
		$message = translate("Templates are added");
		try {

			$template_data = $this->templateService->whatsappCloudApiData($request);

			if(array_key_exists("error", $template_data)) {

				$message = $template_data['error']['message'];
				$status = false;
			} elseif(array_key_exists("data", $template_data)) {

				$this->templateService->saveWhatsappTemplates($template_data, $request);

			} else {
				$status = false;
				$message = translate("Something went wrong");
			}
			return json_encode([

				'reload'  => $status,
				'status'  => $reload,
				'message' => $message
			]);

		} catch (\Exception $e) {

			return json_encode([
				'reload'  => true,
				'status'  => false,
				'message' => $e->getMessage()
			]);
		}
	}

    /**
     * Store Node WhatsApp Template
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeNodeTemplate(Request $request): RedirectResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'channel' => 'required|string',
                'template_data.message' => 'required|string',
                'template_data.image_url' => 'nullable|url',
            ]);

            $templateData = [
                'message' => $request->input('template_data.message'),
            ];

            // Add image URL if provided
            if ($request->filled('template_data.image_url')) {
                $templateData['image_url'] = $request->input('template_data.image_url');
            }

            // Process buttons if provided
            if ($request->has('template_data.buttons')) {
                $buttons = [];
                foreach ($request->input('template_data.buttons') as $button) {
                    $buttonData = [
                        'type' => $button['type'],
                        'text' => $button['text'],
                    ];

                    if (isset($button['value']) && !empty($button['value'])) {
                        if ($button['type'] === 'url') {
                            $buttonData['url'] = $button['value'];
                        } elseif ($button['type'] === 'phone') {
                            $buttonData['phone'] = $button['value'];
                        }
                    }

                    $buttons[] = $buttonData;
                }
                $templateData['buttons'] = $buttons;
            }

            // Extract variables from message
            preg_match_all('/\{\{(\w+)\}\}/', $templateData['message'], $matches);
            $templateData['variables'] = $matches[1] ?? [];

            Template::create([
                'uid' => \Illuminate\Support\Str::random(32),
                'user_id' => null, // Admin template
                'channel' => $request->input('channel'),
                'name' => $request->input('name'),
                'slug' => \Illuminate\Support\Str::slug($request->input('name')),
                'template_data' => $templateData,
                'status' => 'active',
                // Note: Node templates don't have cloud_id, which distinguishes them from Cloud API templates
            ]);

            $notify[] = ['success', translate('Node template created successfully')];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Update Node WhatsApp Template
     *
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function updateNodeTemplate(Request $request, int $id): RedirectResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'template_data.message' => 'required|string',
                'template_data.image_url' => 'nullable|url',
                'status' => 'required|in:active,inactive',
            ]);

            $template = Template::findOrFail($id);

            $templateData = [
                'message' => $request->input('template_data.message'),
            ];

            // Add image URL if provided
            if ($request->filled('template_data.image_url')) {
                $templateData['image_url'] = $request->input('template_data.image_url');
            }

            // Process buttons if provided
            if ($request->has('template_data.buttons')) {
                $buttons = [];
                foreach ($request->input('template_data.buttons') as $button) {
                    $buttonData = [
                        'type' => $button['type'],
                        'text' => $button['text'],
                    ];

                    if (isset($button['value']) && !empty($button['value'])) {
                        if ($button['type'] === 'url') {
                            $buttonData['url'] = $button['value'];
                        } elseif ($button['type'] === 'phone') {
                            $buttonData['phone'] = $button['value'];
                        }
                    }

                    $buttons[] = $buttonData;
                }
                $templateData['buttons'] = $buttons;
            }

            // Extract variables from message
            preg_match_all('/\{\{(\w+)\}\}/', $templateData['message'], $matches);
            $templateData['variables'] = $matches[1] ?? [];

            $template->update([
                'name' => $request->input('name'),
                'slug' => \Illuminate\Support\Str::slug($request->input('name')),
                'template_data' => $templateData,
                'status' => $request->input('status'),
            ]);

            $notify[] = ['success', translate('Node template updated successfully')];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Delete Node WhatsApp Template
     *
     * @param int $id
     * @return RedirectResponse
     */
    public function destroyNodeTemplate(int $id): RedirectResponse
    {
        try {
            $template = Template::findOrFail($id);
            $template->delete();

            $notify[] = ['success', translate('Node template deleted successfully')];
            return back()->withNotify($notify);

        } catch (\Exception $e) {
            $notify[] = ['error', getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
