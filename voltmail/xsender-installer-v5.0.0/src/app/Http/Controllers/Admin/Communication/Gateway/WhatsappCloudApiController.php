<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Traits\ModelAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Http\Requests\GatewayRequest;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Services\System\Communication\GatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
        Session::put("menu_active", true);
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::WHATSAPP, type: WhatsAppGatewayTypeEnum::CLOUD);
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
            return $this->gatewayService->saveGateway(ChannelTypeEnum::WHATSAPP, $data);

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
            return $this->gatewayService->saveGateway(ChannelTypeEnum::WHATSAPP, $data, $id);

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
            return $this->gatewayService->destroyGateway(channel: ChannelTypeEnum::WHATSAPP, type:null, id: $id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
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
        return $this->gatewayService->handleEmbeddedCallback($request);
    }
}