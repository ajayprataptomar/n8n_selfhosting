<?php

namespace App\Http\Controllers\User\Communication\Gateway;

use Exception;
use Illuminate\View\View;
use Illuminate\Support\Arr;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\AndroidSession;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Enums\System\ChannelTypeEnum;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Requests\Admin\AndroidApiRequest;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Http\Requests\RegisterAndroidSessionRequest;
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
        Session::put("menu_active", false);
        $user = auth()->user();
        return $this->gatewayService->loadLogs(channel: ChannelTypeEnum::SMS, type: SmsGatewayTypeEnum::ANDROID, user: $user);
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
            $user = auth()->user();
            return $this->gatewayService->saveAndroidSession(data: $data, user: $user);

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
            $user = auth()->user();
            return $this->gatewayService->saveAndroidSession(data: $data, user: $user);

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
            $user = auth()->user();
            return $this->gatewayService->deleteAndroidSession(id: $id, user: $user);

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
            return $this->bulkAction(request: $request, dependentColumn: null,modelData: [
                "model" => new AndroidSession(),
                "filterable_attributes" => [
                    "user_id" => $user->id
                ]
            ]);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    ## ------------------- ##
    ## API ENDPOINTS START ##
    ## ------------------- ##

    /**
     * registerSession
     *
     * @param RegisterAndroidSessionRequest $request
     * 
     * @return JsonResponse
     */
    public function registerSession(RegisterAndroidSessionRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $result = $this->gatewayService->registerAndroidSessionRequest(request: $request, user: $user);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * logout
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        try {
            $user = auth()->user();
            $result = $this->gatewayService->logoutAndroidSession(request()->bearerToken(), $user);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    ## ------------------ ##
    ##  Unused Functions  ##
    ## ------------------ ##
    public function create() {}
}
