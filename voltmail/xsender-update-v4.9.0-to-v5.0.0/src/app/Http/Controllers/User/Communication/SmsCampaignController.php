<?php

/**
 * @deprecated v4.9 — legacy per-channel campaign controller.
 *  Writes to the legacy `campaigns` + `dispatch_logs` tables. Its routes
 *  remain wired for backward compatibility, but new development must target
 *  the unified stack:
 *
 *      Route group : user.campaign.*
 *      Controller  : App\Http\Controllers\User\Campaign\CampaignController
 *      Model       : App\Models\UnifiedCampaign
 *      Dispatch    : App\Models\CampaignDispatch
 *      Service     : App\Services\Campaign\UnifiedCampaignService
 *
 *  Full retirement + data migration to `campaign_dispatches` is scheduled
 *  for v5.0. Do NOT add new features here; extend UnifiedCampaignService.
 */

namespace App\Http\Controllers\User\Communication;

use Exception;
use App\Models\Campaign;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\SmsCampaignRequest;
use App\Services\System\Communication\DispatchService;

class SmsCampaignController extends Controller
{
    use ModelAction;

    public $dispatchService;

    public function __construct() {
        $this->dispatchService = new DispatchService();
    }

    /**
     * index
     *
     * @return View
     */
    public function index(): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->loadCampaignLogs(channel: ChannelTypeEnum::SMS, user: $user);
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
        return $this->dispatchService->createCampaignLog(channel: ChannelTypeEnum::SMS, user: $user);
    }

    /**
     * store
     *
     * @param SmsCampaignRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(SmsCampaignRequest $request): RedirectResponse
    {
        try {
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::SMS, request: $request, isCampaign: true, user: $user);

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
     * @param mixed $id
     * 
     * @return View
     */
    public function edit($id): View
    {
        $user = auth()->user();
        Session::put("menu_active", true);
        return $this->dispatchService->showCampaignLog(channel: ChannelTypeEnum::SMS, id: $id, user: $user);
    }

    /**
     * update
     *
     * @param SmsCampaignRequest $request
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function update(SmsCampaignRequest $request, $id): RedirectResponse
    {
        try {
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::SMS, request: $request, isCampaign: true, campaignId: $id, user: $user);

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
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function destroy($id): RedirectResponse
    {
        try {
            $user = auth()->user();
            return $this->dispatchService->destroyCampaignLog(id: $id, user: $user);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * bulk
     *
     * @param Request $request
     * 
     * @return RedirectResponse
     */
    public function bulk(Request $request): RedirectResponse {

        try {
            $user = auth()->user();
            return $this->bulkAction($request, null,[
                "model"         => new Campaign(),
                'redirect_url'  => route("user.communication.sms.campaign.index"),
                "user_id"       => $user->id
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
