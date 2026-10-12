<?php

/**
 * @deprecated v4.9 — legacy per-channel campaign controller.
 *  See SmsCampaignController for full rationale. Unified equivalent:
 *      Route group : user.campaign.*
 *      Controller  : App\Http\Controllers\User\Campaign\CampaignController
 *      Model       : App\Models\UnifiedCampaign
 *  Scheduled for retirement in v5.0. Do NOT add new features here.
 */

namespace App\Http\Controllers\User\Communication;

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
        $user = auth()->user();
        Session::put("menu_active", true);
        try {
            return $this->dispatchService->loadCampaignLogs(channel: ChannelTypeEnum::EMAIL, user: $user);
        } catch (\Throwable $e) {
            \Log::error('Email campaign list render failed', [
                'user_id' => $user?->id,
                'query'   => request()->fullUrl(),
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ]);
            throw $e;
        }
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
        return $this->dispatchService->createCampaignLog(channel: ChannelTypeEnum::EMAIL, user: $user);
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
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::EMAIL, request: $request, isCampaign: true, user: $user);

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
        return $this->dispatchService->showCampaignLog(channel: ChannelTypeEnum::EMAIL, id: $id, user: $user);
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
            $user = auth()->user();
            Session::put("menu_active", true);
            return $this->dispatchService->storeDispatchLogs(type: ChannelTypeEnum::EMAIL, request: $request, isCampaign: true, campaignId: $id, user: $user);

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
