<?php

namespace App\Http\Controllers\Api\Communication\Gateway\Android;

use App\Enums\Common\Status;
use Exception;
use App\Models\AndroidSim;
use App\Traits\ApiExceptionResponder;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Http\Requests\ManageAndroidSimRequest;
use App\Managers\GatewayManager;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use App\Services\System\Communication\GatewayService;

class SimController extends Controller
{
    use ApiExceptionResponder, ModelAction;

    protected $gatewayService;
    protected $gatewayManager;

    public function __construct()
    {
        $this->gatewayService = new GatewayService();
        $this->gatewayManager = new GatewayManager();
    }


    /**
     * index
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $token = request()->bearerToken();
        try {
            $user = $this->resolveApiUser();
            $sims = $this->gatewayService->getAndroidSims(token: $token, user: $user);
            
            return ApiJsonResponse::success(
                translate('Android SIMs retrieved successfully'),
                $sims
            );
        } catch (ApplicationException $e) {
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            return $this->jsonException($e, 'android.sim');
        }
    }

    /**
     * store
     *
     * @param ManageAndroidSimRequest $request
     * 
     * @return JsonResponse
     */
    public function store(ManageAndroidSimRequest $request): JsonResponse
    {
        try {
            $user = $this->resolveApiUser();
            return $this->gatewayService->storeAndroidSim(request: $request, user: $user);
        } catch (ApplicationException $e) {
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            return $this->jsonException($e, 'android.sim');
        }
    }

    /**
     * update
     *
     * @param ManageAndroidSimRequest $request
     * @param int $id
     * 
     * @return JsonResponse
     */
    public function update(ManageAndroidSimRequest $request, int $id): JsonResponse
    {
        try {
            $user = $this->resolveApiUser();
            return $this->gatewayService->updateAndroidSim(request: $request, id: $id, user: $user);
        } catch (ApplicationException $e) {
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            return $this->jsonException($e, 'android.sim');
        }
    }

    /**
     * destroy
     *
     * @param int $id
     * 
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $token  = request()->bearerToken();
            $user   = $this->resolveApiUser();
            return $this->gatewayService->deleteAndroidSimForApi(id: $id, user: $user, token: $token);
        } catch (ApplicationException $e) {
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );
        } catch (Exception $e) {
            return $this->jsonException($e, 'android.sim');
        }
    }

    /**
     * updateStatus
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        try {
            $user = $this->resolveApiUser();
            $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $token, user: $user, ignoreUser: false);
            if(!$androidSession) throw new ApplicationException("Android Session not found", Response::HTTP_UNAUTHORIZED);
            
            $this->validateStatusUpdate(
                request: $request,
                tableName: 'android_sims',
                isJson: true,
                keyColumn: 'id'
            );

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: [
                    'message' => translate('Android SIM status updated successfully'),
                    'model' => new AndroidSim(),
                    'column' => "status",
                    'filterable_attributes' => [
                        'id' => $request->input('id'),
                        "user_id" => @$user?->id ? $user->id : null
                    ],
                    'reload' => true
                ]
            );

            return ApiJsonResponse::success(
                translate('Android SIM status updated successfully'),
                json_decode($notify, true)
            );
        }  catch (ApplicationException $e) {
            return ApiJsonResponse::error(
                translate($e->getMessage()),
                null,
                $e->getStatusCode()
            );
        } catch (ValidationException $e) {

            return ApiJsonResponse::validationError(
                $e->errors(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Exception $e) {
            return $this->jsonException($e, 'android.sim');
        }
    }
}
