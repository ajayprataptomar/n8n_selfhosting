<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use App\Models\Gateway;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Requests\GatewayRequest;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Services\System\Communication\GatewayService;

class EmailGatewayController extends Controller
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
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::EMAIL);
    }

    /**
     * store
     *
     * @param GatewayRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(GatewayRequest $request): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->gatewayService->saveGateway(ChannelTypeEnum::EMAIL, $data);

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
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function update(GatewayRequest $request, $id): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->gatewayService->saveGateway(ChannelTypeEnum::EMAIL, $data, $id);

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
            return $this->gatewayService->destroyGateway(channel: ChannelTypeEnum::EMAIL, type: null, id:$id);

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
    public function updateStatus(Request $request): string {
        
        try {
            $this->validateStatusUpdate(
                request: $request,
                tableName: 'gateways', 
                isJson: true,
                keyColumn: 'id'
            );
            $actionData = [
                'message' => translate('Email Gateway status updated successfully'),
                'model'   => new Gateway,
                'column'  => $request->input('column'),
                'filterable_attributes' => [
                    'id' => $request->input('id'),
                    'channel' => ChannelTypeEnum::EMAIL
                ],
                'reload' => true
            ];
            
            $isDefault = $request->input("column", "status") != "status";
            if($isDefault) $actionData = Arr::set($actionData, "additional_adjustments", "default_gateway");

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: $actionData
            );

            return $notify;

        } catch (Exception $e) {
            return response()->json([
                'status'    => false,
                'message'   => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * bulk
     *
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function bulk(Request $request) :RedirectResponse {

        try {

            return $this->bulkAction($request, null,[
                "model" => new Gateway(),
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * testGateway
     *
     * @return JsonResponse
     */
    public function testGateway():JsonResponse {
        
        try {

            $gatewayId = request()->input('gateway_id') ? (int) request()->input('gateway_id') : null;
            return $this->gatewayService->testEmailGateway(gatewayId: $gatewayId);
        } catch (ApplicationException $e) {
            
            return response()->json([
                'status' => false,
                'message' => translate($e->getMessage()),
            ], $e->getStatusCode()); 
        } catch (Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]); 
        }
    }
}
