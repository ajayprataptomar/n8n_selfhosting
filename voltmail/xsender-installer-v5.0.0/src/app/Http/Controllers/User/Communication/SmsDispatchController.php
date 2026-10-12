<?php

namespace App\Http\Controllers\User\Communication;

use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use App\Managers\GatewayManager;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\RedirectResponse;
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

    /**
     * __construct
     *
     */
    public function __construct() {
        
        $this->gatewayManager       = new GatewayManager();
        $this->dispatchService      = new DispatchService();
        $this->communicationManager = new CommunicationManager();
    }

    /**
     * index
     *
     * @param int|string|null|null $campaign_id
     * 
     * @return View
     */
    public function index(int|string|null $campaign_id = null): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->loadLogs(channel: ChannelTypeEnum::SMS, campaign_id: $campaign_id, user: $user);
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->createDispatchLog(channel: ChannelTypeEnum::SMS, user: $user);
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
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::SMS, request: $request, user: $user);

        } catch (ApplicationException $e) {

            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify)->withInput();

        } catch (Exception $e) {

            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify)->withInput();
        }
    }
}
