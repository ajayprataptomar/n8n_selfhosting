<?php

namespace App\Http\Controllers\user\Communication\Gateway;

use App\Enums\Common\Status;
use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\WhatsappServerRequest;
use App\Services\System\Communication\NodeService;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Http\Requests\GatewayRequest;
use App\Services\System\Communication\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class WhatsappCloudApiController extends Controller
{
    use ModelAction;
    protected $gatewayService;

    public function __construct()
    {
        $this->gatewayService = new GatewayService();
    }

    /**
     * index
     *
     * @return View
     */
    public function index(): View
    {
        $user = auth()->user();
        Session::put("menu_active", false);
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::WHATSAPP, type: WhatsAppGatewayTypeEnum::CLOUD, user: $user);
    }

    /**
     *
     * @param GatewayRequest $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function store(GatewayRequest $request): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            return $this->gatewayService->saveGateway(channel: ChannelTypeEnum::WHATSAPP, data: $data, user: $user);

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
     * @param GatewayRequest $request
     * @param string|int $id
     * 
     * @return RedirectResponse
     */
    public function update(GatewayRequest $request, string|int $id): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            return $this->gatewayService->saveGateway(channel: ChannelTypeEnum::WHATSAPP, data: $data, id: $id, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * destroy
     *
     * @param string|int|null|null $id
     * 
     * @return RedirectResponse
     */
    public function destroy(string|int|null $id = null): RedirectResponse
    {
        try {
            $user = auth()->user();
            return $this->gatewayService->destroyGateway(channel: ChannelTypeEnum::WHATSAPP, type: null, id: $id, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    public function webhook(Request $request) {

        $user = Auth::user();
        $user->webhook_token = $request->input("verify_token");
        $user->save();
        $notify[] = ['success', translate('Whatsapp Cloud Webhook verify token saved successfully')];
        return back()->withNotify($notify);
    }

    /**
     * Initiate WhatsApp Cloud API Embedded Signup
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initiateEmbeddedSignup(Request $request): JsonResponse
    {
        return $this->gatewayService->initiateEmbeddedSignup($request);
    }

    /**
     * Handle WhatsApp Cloud API Embedded Callback
     *
     * @param Request $request
     * @return View
     */
    public function handleEmbeddedCallback(Request $request): View
    {
        return $this->gatewayService->handleEmbeddedCallback($request, Auth::user());
    }
}
