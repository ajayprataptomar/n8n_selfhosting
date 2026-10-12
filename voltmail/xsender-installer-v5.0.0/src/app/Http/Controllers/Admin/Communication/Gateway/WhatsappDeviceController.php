<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Support\Arr;
use App\Enums\Common\Status;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Http\Requests\GatewayRequest;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\WhatsappServerRequest;
use App\Services\System\Communication\NodeService;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Services\System\Communication\GatewayService;

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
        Session::put("menu_active", true);
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::WHATSAPP, type: WhatsAppGatewayTypeEnum::NODE);
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
            $data = Arr::set($data, "type", WhatsAppGatewayTypeEnum::NODE);
            $data = Arr::set($data, "status", Status::INACTIVE->value);
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
            $data = Arr::set($data, "type", WhatsAppGatewayTypeEnum::NODE);
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
     * statusUpdate
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function statusUpdate(Request $request): JsonResponse {

        try {
            $message = $this->gatewayService->whatsappDeviceStatusUpdate($request);
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

            return $this->nodeService->generateQr($request);

        } catch (ApplicationException $e) {

            $data['status']  = $e->getStatusCode();
            $data['message'] = $e->getMessage();

            $response = [
                'response'  => $e->getMessage(),
                'data'      => $data
            ];
            return response()->json($response);
            // $notify[] = ["error", translate($e->getMessage())];
            // return back()->withNotify($notify);

        } catch (Exception $e) {

            $data['status']  = $e->getCode();
            $data['message'] = $e->getMessage();

            $response = [
                'response'  => $e->getMessage(),
                'data'      => $data
            ];
            return response()->json($response);
            
            // $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            // return back()->withNotify($notify);
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

            return $this->nodeService->confirmDeviceConnection($request);

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

            return $this->nodeService->reconnectDevice($request);

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
     * updateServer
     *
     * @param WhatsappServerRequest $request
     *
     * @return RedirectResponse|JsonResponse
     */
    public function updateServer(WhatsappServerRequest $request): RedirectResponse|JsonResponse {

        try {

            $data = $request->all();
            unset($data["_token"]);

            // Check if AJAX request
            if ($request->ajax() || $request->wantsJson()) {
                $result = $this->nodeService->updateNodeServerWithResult($data);

                return response()->json([
                    'success' => $result['success'],
                    'synced' => $result['synced'],
                    'message' => $result['message'],
                ]);
            }

            return $this->nodeService->updateNodeServer($data);

        } catch (Exception $e) {

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'synced' => false,
                    'message' => getEnvironmentMessage($e->getMessage()),
                ], 500);
            }

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Generate API Key
     *
     * @return JsonResponse
     */
    public function generateApiKey(): JsonResponse {

        try {

            $apiKey = NodeService::generateApiKey();

            return response()->json([
                'success' => true,
                'api_key' => $apiKey,
                'message' => translate('API key generated successfully')
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage())
            ], 500);
        }
    }

    /**
     * Check Node Service Health
     *
     * @return JsonResponse
     */
    public function checkServiceHealth(): JsonResponse {

        try {

            $health = $this->nodeService->checkHealth();

            return response()->json([
                'success' => $health['healthy'],
                'health' => $health,
                'message' => $health['message']
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage())
            ], 500);
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
            return $this->gatewayService->destroyGateway(channel: ChannelTypeEnum::WHATSAPP, type: null, id: $id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Reinitialize Node Service
     * Pushes fresh configuration to the Node service
     *
     * @return JsonResponse
     */
    public function reinitializeService(): JsonResponse {

        try {
            // Clear the cache to force fresh config push
            \Illuminate\Support\Facades\Cache::forget('wp_node_configured');

            // Push configuration to Node service
            $result = $this->nodeService->pushConfigToNode();

            if ($result) {
                // Also verify license
                $licenseResult = $this->nodeService->verifyLicense();

                return response()->json([
                    'success' => true,
                    'config_pushed' => true,
                    'license_valid' => $licenseResult['success'] ?? false,
                    'message' => translate('Node service reinitialized successfully')
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => translate('Failed to push configuration to Node service. Please check if the service is running.')
            ], 500);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage())
            ], 500);
        }
    }

    /**
     * Get detailed health report from Node service
     *
     * @return JsonResponse
     */
    public function getHealthReport(): JsonResponse {

        try {
            $health = $this->nodeService->getHealthReport();

            return response()->json([
                'success' => $health['healthy'] ?? false,
                'health' => $health,
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
                'health' => [
                    'healthy' => false,
                    'error' => $e->getMessage()
                ]
            ], 200); // Return 200 so frontend can display the error gracefully
        }
    }

    /**
     * Get logs from Node service
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getServiceLogs(Request $request): JsonResponse {

        try {
            $level = $request->get('level', 'info');
            $lines = (int) $request->get('lines', 100);

            $logs = $this->nodeService->getLogs($level, $lines);

            return response()->json([
                'success' => true,
                'logs' => $logs,
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
                'logs' => []
            ], 200); // Return 200 so frontend can display the error gracefully
        }
    }
}
