<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Models\AndroidSession;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\Admin\AndroidApiRequest;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Services\System\Communication\GatewayService;

class AndroidSessionController extends Controller
{
    use ModelAction;
    protected $gatewayService;

    public function __construct()
    {
        $this->gatewayService = new GatewayService();
    }

    ## ------------- ##
    ## Web Functions ##
    ## ------------- ##

    /**
     * index
     *
     * @return View
     */
    public function index(): View|RedirectResponse
    {
        Session::put("menu_active", true);
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::SMS, type: SmsGatewayTypeEnum::ANDROID);
    }
    
    /**
     * store
     *
     * @param AndroidApiRequest $request
     * 
     * @return RedirectResponse
     */
    public function store(AndroidApiRequest $request): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->gatewayService->saveAndroidSession($data);

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
     * @param AndroidApiRequest $request
     * @param mixed $id
     * 
     * @return RedirectResponse
     */
    public function update(AndroidApiRequest $request, $id): RedirectResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            $data = Arr::set($data, "id", $id);
            return $this->gatewayService->saveAndroidSession($data);

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
            return $this->gatewayService->deleteAndroidSession($id);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     *
     * @param Request $request
     * 
     * @return \Illuminate\Http\RedirectResponse
     * 
     */
    public function bulk(Request $request): RedirectResponse {

        try {

            return $this->bulkAction($request, null,[
                "model" => new AndroidSession(),
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    ## ------------------ ##
    ##  Unused Functions  ##
    ## ------------------ ##
    public function create() {}
}
