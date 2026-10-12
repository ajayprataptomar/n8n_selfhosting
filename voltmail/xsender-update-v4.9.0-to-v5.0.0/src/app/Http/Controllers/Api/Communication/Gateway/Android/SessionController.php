<?php

namespace App\Http\Controllers\Api\Communication\Gateway\Android;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Requests\RegisterAndroidSessionRequest;
use App\Services\System\Communication\GatewayService;
use App\Traits\ApiExceptionResponder;

class SessionController extends Controller
{
    use ApiExceptionResponder;

    protected $gatewayService;

    /**
     * __construct
     *
     */
    public function __construct()
    {
        $this->gatewayService = new GatewayService();
    }

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
            $result = $this->gatewayService->registerAndroidSessionRequest($request);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            return $this->jsonException($e, 'android.session');
        }
    }

    /**
     * logout
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $token = $request->bearerToken();
            
            $user = $this->resolveApiUser();
            
            $result = $this->gatewayService->logoutAndroidSession($token, $user);
            return $result; 

        } catch (ApplicationException $e) {
            
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );

        } catch (Exception $e) {
            return $this->jsonException($e, 'android.session');
        }
    }
}
