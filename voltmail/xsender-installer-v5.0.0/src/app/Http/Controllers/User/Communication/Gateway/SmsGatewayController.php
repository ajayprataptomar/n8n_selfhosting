<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use Exception;
use App\Models\Gateway;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Http\Requests\SmsGatewayRequest;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Services\System\Communication\GatewayService;
use Illuminate\Http\JsonResponse;

class SmsGatewayController extends Controller
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
        Session::put("menu_active", false);
        $user = auth()->user();
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::SMS, type: SmsGatewayTypeEnum::API, user: $user);
    }

    /**
     * store
     *
     * @param SmsGatewayRequest $request
     * 
     * @return JsonResponse
     */
    public function store(SmsGatewayRequest $request): JsonResponse
    {
        try {
            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            
            $result = $this->gatewayService->saveGateway(channel: ChannelTypeEnum::SMS, data: $data, user: $user);

            return response()->json([
                'status' => Arr::get($result, key: "status"),
                'message' => Arr::get($result, "message"),
            ], 200);

        } catch (ApplicationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => translate($e->getMessage()),
            ], $e->getCode() ?: 400);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => getEnvironmentMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * update
     *
     * @param SmsGatewayRequest $request
     * @param mixed $id
     * 
     * @return JsonResponse
     */
    public function update(SmsGatewayRequest $request, $id): JsonResponse
    {
        try {
            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            
            $result = $this->gatewayService->saveGateway(ChannelTypeEnum::SMS, $data, $id, $user);

            return response()->json([
                'status' => Arr::get($result, key: "status"),
                'message' => Arr::get($result, "message"),
            ], 200);

        } catch (ApplicationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => translate($e->getMessage()),
            ], $e->getCode() ?: 400);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => getEnvironmentMessage($e->getMessage()),
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
            $user = auth()->user();
            return $this->gatewayService->destroyGateway(channel: ChannelTypeEnum::SMS, type: null, id: $id, user: $user);

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
            $user = auth()->user();
            $this->validateStatusUpdate(
                request: $request,
                tableName: 'gateways', 
                isJson: true,
                keyColumn: 'id'
            );

            $actionData = [
                'message' => translate('SMS Gateway status updated successfully'),
                'model'   => new Gateway,
                'column'  => $request->input('column'),
                'filterable_attributes' => [
                    'id' => $request->input('id'),
                    'channel' => ChannelTypeEnum::SMS,
                    'user_id' => $user->id
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
            $user = auth()->user();
            return $this->bulkAction($request, null,[
                "model" => new Gateway(),
                "filterable_attributes" => [
                    "user_id" => $user->id
                ]
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
