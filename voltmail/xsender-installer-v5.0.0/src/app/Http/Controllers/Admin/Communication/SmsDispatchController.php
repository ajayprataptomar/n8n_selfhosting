<?php

namespace App\Http\Controllers\Admin\Communication;

use Exception;
use Illuminate\View\View;
use App\Models\DispatchLog;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Managers\GatewayManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use App\Managers\CommunicationManager;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\SmsDispatchRequest;
use App\Services\System\Communication\DispatchService;

class SmsDispatchController extends Controller
{
    use ModelAction;

    public $gatewayManager;
    public $dispatchService;
    public $communicationManager;

    public function __construct() {
        
        $this->gatewayManager = new GatewayManager();
        $this->dispatchService = new DispatchService();
        $this->communicationManager = new CommunicationManager();
    }

    public function index(int|string|null $campaign_id = null): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->loadLogs(ChannelTypeEnum::SMS, $campaign_id);
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->createDispatchLog(ChannelTypeEnum::SMS);
    }

    /**
     * store
     *
     * @param SmsDispatchRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(SmsDispatchRequest $request): RedirectResponse
    {
        try {
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(ChannelTypeEnum::SMS, $request);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }
    }

    /**
     * destroy
     *
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function destroy($id): RedirectResponse
    {
        
        try {
            return $this->dispatchService->destroyDispatchLog($id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    // /**
    //  * updateStatus
    //  *
    //  * @param Request $request
    //  * 
    //  * @return RedirectResponse
    //  */
    // public function updateStatus(Request $request): RedirectResponse
    // {
    //     try {

    //         $request->merge([
    //             'column'    => 'id',
    //             'channel'   => ChannelTypeEnum::SMS,
    //             'value'     => $request->input('status'),
    //         ]);

    //         $this->validateStatusUpdate(
    //             isJson: false,
    //             request: $request,
    //             tableName: 'dispatch_logs', 
    //             keyColumn: 'id',
    //             additionalRules: [
    //                 'gateway_id'    => ['nullable', 'numeric', 'gte:-1'],
    //                 'method'        => ['nullable', new Enum(SmsGatewayTypeEnum::class)],
    //                 'status'        => ['required', new Enum(CommunicationStatusEnum::class)],
    //                 'value'        => ['required', new Enum(CommunicationStatusEnum::class)],
    //             ]
    //         );

    //         $notify = $this->statusUpdate(
    //             request: $request->except('_token'),
    //             actionData: [
    //                 'message'               => translate('Log status updated successfully'),
    //                 'model'                 => new DispatchLog,
    //                 'column'                => 'status',
    //                 'filterable_attributes' => [
    //                     'id' => $request->input('id')
    //                 ],
    //                 'redirect'               => true,
    //                 'additional_adjustments' => "channel",
    //                 'additional_data'        => "gateway_id",
    //                 'reload'                 => false
    //             ]
    //         );

    //         return returnBackWithResponse(status: "success", message: "Successfully updated dispatch log");

    //     } catch (ApplicationException $e) {

    //         return returnBackWithResponse(message: $e->getMessage());
    //     } catch (ValidationException $e) {

    //         return returnRedirectWithResponse(route:route("admin.communication.sms.index"), message: $e->getMessage());
    //     } catch (Exception $e) {
            
    //         return returnBackWithResponse(message: getEnvironmentMessage($e->getMessage()));
    //     }
    // }

    /**
     * bulk
     *
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function bulk(Request $request): RedirectResponse {

        try {
            $request->merge([
                'column'    => 'id',
                'channel'   => ChannelTypeEnum::SMS,
                'value'     => $request->input('status'),
            ]);

            return $this->bulkAction($request, null,[
                "model" => new DispatchLog(),
                'additional_adjustments' => "channel",
                'additional_data'        => "gateway_id",
                'redirect_url'           => route("admin.communication.sms.index"),
            ]);

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
     * @return RedirectResponse
     */
    public function updateStatus(Request $request): RedirectResponse {
        
        try {
            return $this->dispatchService->updateDispatchLogStatus(ChannelTypeEnum::SMS, $request);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
