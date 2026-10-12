<?php

namespace App\Http\Controllers\User\Communication\Gateway;

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

class WhatsappDeviceController extends Controller
{
    use ModelAction;
    
    protected $nodeService;
    protected $gatewayService;

    public function __construct()
    {
        $this->nodeService = new NodeService();
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
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::WHATSAPP, type: WhatsAppGatewayTypeEnum::NODE, user: $user);
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
            $data = Arr::set($data, "type", WhatsAppGatewayTypeEnum::NODE);
            $data = Arr::set($data, "status", Status::INACTIVE->value);
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
            $data = Arr::set($data, "type", WhatsAppGatewayTypeEnum::NODE);
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
     * statusUpdate
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function statusUpdate(Request $request): JsonResponse {

        try {
            $user = auth()->user();
            $message = $this->gatewayService->whatsappDeviceStatusUpdate(request: $request, user: $user);
            return response()->json([
               'success' => $message
            ]); 

        } catch (ApplicationException $e) {
            
            return response()->json([
                'success' =>  translate($e->getMessage()),
            ], $e->getStatusCode()); 

        } catch (Exception $e) {
            
            return response()->json([
                'success' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * whatsappQRGenerate
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function whatsappQRGenerate(Request $request): JsonResponse {
        
        try {
            $user = auth()->user();
            return $this->nodeService->generateQr(request: $request, user:$user);

        } catch (ApplicationException $e) {

            $data['status']  = $e->getCode();
            $data['qr']      = '';
            $data['message'] = translate($e->getMessage());

            return response()->json([
                'response' => null,
                'data'     => $data,
            ]);

        } catch (Exception $e) {

            $data['status']  = $e->getCode();
            $data['qr']      = '';
            $data['message'] = getEnvironmentMessage($e->getMessage());

            return response()->json([
                'response' => null,
                'data'     => $data,
            ]);
        }
    }

    /**
     * getDeviceStatus
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function getDeviceStatus(Request $request): JsonResponse {

        try {
            $user = auth()->user();
            return $this->nodeService->confirmDeviceConnection(request: $request, user: $user);

        } catch (ApplicationException $e) {

            return response()->json([
                'response' => null,
                'data'     => [
                    'status'  => $e->getCode(),
                    'qr'      => '',
                    'message' => $e->getMessage(),
                ],
            ]);

        } catch (Exception $e) {

            return response()->json([
                'response' => null,
                'data'     => [
                    'status'  => $e->getCode(),
                    'qr'      => '',
                    'message' => $e->getMessage(),
                ],
            ]);
        }
    }

    /**
     * reconnectDevice - Reconnect a disconnected session using saved credentials
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function reconnectDevice(Request $request): JsonResponse {

        try {
            $user = auth()->user();
            return $this->nodeService->reconnectDevice(request: $request, user: $user);

        } catch (ApplicationException $e) {

            return response()->json([
                'success' => false,
                'message' => translate($e->getMessage()),
            ], $e->getStatusCode());

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
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
}
