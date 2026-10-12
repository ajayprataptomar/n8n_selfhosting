<?php

namespace App\Http\Controllers\User;

use App\Enums\System\ChannelTypeEnum;
use App\Exceptions\ApplicationException;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->templateService->loadIndexView(channel: ChannelTypeEnum::from($channel), cloudId: $cloudId, user: $user);
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
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->templateService->loadCreateView(channel: ChannelTypeEnum::from($channel), user: $user);
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
            $user = auth()->user();
            $data = $request->all();
            unset($data["_token"]);
            return $this->templateService->save(data: $data, user: $user);

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
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->templateService->loadEditView(uid: $uid, user: $user);

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
            $user = auth()->user();
            $data = $request->all();
            unset($data["_token"]);
            
            return $this->templateService->save(data: $data, uid: $uid, user: $user);

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
            $user = auth()->user();
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
                        'uid' => $request->input('uid'),
                        "user_id" => @$user?->id
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
            $user = auth()->user();
            return $this->templateService->destroyTemplate(uid: $uid, user: $user);

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

        $user = auth()->user();
        return $this->templateService->returnTemplateData(uid: $uid, user: $user);
    }

   
    /**
     * editTemplateJson
     *
     * @param string|int|null|null $uid
     * 
     * @return string
     */
    public function editTemplateJson(string|int|null $uid = null): string {

        $user = auth()->user();
        return $this->templateService->returnEditTemplateData(uid: $uid, user: $user);
    }

    /**
     * fetch
     *
     * @param string $channel
     * 
     * @return JsonResponse
     */
    public function fetch(string $channel): JsonResponse {

        $user = auth()->user();
        $cloudId = request()->query('cloud_id');
        $templates = $this->templateService->getChannelSpecificTemplates(
            channel: ChannelTypeEnum::from(value: $channel),
            user: $user,
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

        $user = auth()->user();
        $templates = $this->templateService->getChannelSpecificTemplates(channel: ChannelTypeEnum::EMAIL, user: $user);
        
        return response()->json([
            'view' => view('user.email_template.data',[
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
        $user = auth()->user();
		try {

			$template_data = $this->templateService->whatsappCloudApiData($request);
			
			if(array_key_exists("error", $template_data)) {
				
				$message = $template_data['error']['message'];
				$status = false;
			} elseif(array_key_exists("data", $template_data)) {

				$this->templateService->saveWhatsappTemplates($template_data, $request, $user->id);
				
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
}
