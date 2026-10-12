<?php

namespace App\Http\Controllers\Admin\Communication\Gateway;

use Exception;
use Illuminate\View\View;
use App\Models\AndroidSim;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Services\System\Communication\GatewayService;

class AndroidSessionSimController extends Controller
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
     * @param string|null|null $token
     * 
     * @return View|RedirectResponse
     */
    public function index(string|null $token = null): View|RedirectResponse
    {
        try {

            Session::put("menu_active", true);
            return $this->gatewayService->loadAndroidSims($token);
        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    public function update(Request $request, $id) {}

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
            return $this->gatewayService->deleteAndroidSim(id: $id);

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
                "model"         => new AndroidSim(),
                "redirect_url"  => route("admin.gateway.sms.android.index")
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
