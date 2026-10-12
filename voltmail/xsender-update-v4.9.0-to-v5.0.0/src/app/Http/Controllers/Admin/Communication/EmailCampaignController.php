<?php

/**
 * @deprecated v4.9 — legacy per-channel campaign controller.
 *  See SmsCampaignController for full rationale. Unified equivalent:
 *      Route group : admin.campaign.*
 *      Controller  : App\Http\Controllers\Admin\Campaign\UnifiedCampaignController
 *      Model       : App\Models\UnifiedCampaign
 *  Scheduled for retirement in v5.0. Do NOT add new features here.
 */

namespace App\Http\Controllers\Admin\Communication;

use Exception;
use App\Models\Campaign;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\EmailCampaignRequest;
use App\Services\System\Communication\DispatchService;

class EmailCampaignController extends Controller
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
        Session::put("menu_active", true);
        return $this->dispatchService->loadCampaignLogs(ChannelTypeEnum::EMAIL);
    }

    /**
     * create
     *
     * @return View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        return $this->dispatchService->createCampaignLog(ChannelTypeEnum::EMAIL);
    }

    /**
     * store
     *
     * @param EmailCampaignRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(EmailCampaignRequest $request): RedirectResponse
    {
        try {
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(ChannelTypeEnum::EMAIL, $request, isCampaign: true);

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
        Session::put("menu_active", true);
        return $this->dispatchService->showCampaignLog(ChannelTypeEnum::EMAIL, $id);
    }

    /**
     * update
     *
     * @param EmailCampaignRequest $request
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function update(EmailCampaignRequest $request, $id): RedirectResponse
    {
        try {
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(ChannelTypeEnum::EMAIL, $request, isCampaign: true, campaignId: $id);

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
            return $this->dispatchService->destroyCampaignLog($id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    public function bulk(Request $request): RedirectResponse {

        try {

            return $this->bulkAction($request, null,[
                "model"         => new Campaign(),
                'redirect_url'  => route("admin.communication.sms.campaign.index"),
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
