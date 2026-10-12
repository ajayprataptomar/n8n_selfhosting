<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use App\Exceptions\ApplicationException;
use App\Http\Controllers\Controller;
use App\Models\AndroidSim;
use App\Services\System\Communication\GatewayService;
use App\Traits\ModelAction;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

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
            $user = auth()->user();
            Session::put("menu_active", false);
            return $this->gatewayService->loadAndroidSims(token: $token, user: $user);
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
            $user = auth()->user();
            return $this->gatewayService->deleteAndroidSim(id: $id, user: $user);

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
            $user = auth()->user();
            return $this->bulkAction($request, null,[
                "model"         => new AndroidSim(),
                "redirect_url"  => route("admin.gateway.sms.android.index"),
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
