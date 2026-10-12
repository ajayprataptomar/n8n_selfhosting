<?php

namespace App\Http\Controllers\Admin\Communication\Gateway\Api;

use App\Exceptions\ApplicationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManageAndroidSimRequest;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Models\AndroidSim;
use App\Services\System\Communication\GatewayService;
use App\Traits\ModelAction;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class AndroidSessionSimController extends Controller
{
    use ModelAction;

    protected $gatewayService;

    public function __construct()
    {
        $this->gatewayService = new GatewayService();
    }

    public function index(): JsonResponse
    {
        $token = request()->bearerToken();
        try {
            $user = Auth::guard('api')->user();
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
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }//
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
            $user = Auth::guard('api')->user();
            
            return $this->gatewayService->storeAndroidSim(request: $request, user: $user);
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
            $user = Auth::guard('api')->user();
            return $this->gatewayService->updateAndroidSim(request: $request, id: $id, user: $user);
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
     * destroy
     *
     * @param int $id
     * 
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();
            return $this->gatewayService->deleteAndroidSim(id: $id, user: $user);
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
     * updateStatus
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function updateStatus(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();
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
        } catch (ValidationException $e) {

            return ApiJsonResponse::validationError(
                $e->errors(),
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } catch (Exception $e) {
            
            return ApiJsonResponse::error(
                getEnvironmentMessage($e->getMessage()),
                null,
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}