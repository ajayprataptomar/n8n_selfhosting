<?php

namespace App\Http\Controllers\Api\Communication;

use Exception;
use Illuminate\Http\Request;
use App\Models\AndroidSession;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Managers\CommunicationManager;
use App\Exceptions\ApplicationException;
use App\Http\Utility\Api\ApiJsonResponse;
use App\Services\System\Communication\DispatchService;
use App\Http\Requests\Api\UpdateDispatchLogStatusesRequest;

class SmsDispatchController extends Controller
{
    public $dispatchService;
    public $communicationManager;

    public function __construct() {
        
        $this->dispatchService      = new DispatchService();
        $this->communicationManager = new CommunicationManager();
    }
    
    /**
     * fetchPending
     *
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function fetchPending(Request $request): JsonResponse
    {
        
        try {
            $limit = (int) $request->header('X-Limit', 10);
            if ($limit <= 0) throw new ApplicationException('Limit must be a positive integer', 400);
            
            $token = $request->bearerToken();
            $androidSession = AndroidSession::where('token', $token)->first();
            if (!$androidSession) throw new ApplicationException('Invalid or expired token', 401);
            
            $simIds = $request->header('X-Sim-Ids');
            if(!$simIds) throw new ApplicationException('Invalid SIM IDs', 401);
            $simIdsArray = $simIds ? array_filter(explode(',', $simIds)) : null;
            
            $dispatchLogs = $this->communicationManager->fetchPendingSmsForAndroid($androidSession, $limit, $simIdsArray);
            if (empty($dispatchLogs)) throw new ApplicationException('No more logs are available', 404);

            return ApiJsonResponse::success('Pending SMS dispatch logs fetched successfully', $dispatchLogs);

        } catch (ApplicationException $e) {
            return ApiJsonResponse::error($e->getMessage(), null, $e->getStatusCode());
        } catch (\Exception $e) {
            return ApiJsonResponse::exception($e);
        }
    }

    /**
     * updateStatuses
     *
     * @param UpdateDispatchLogStatusesRequest $request
     * 
     * @return JsonResponse
     */
    public function updateStatuses(UpdateDispatchLogStatusesRequest $request): JsonResponse
    {
        try {

            $token          = $request->bearerToken();
            $androidSession = AndroidSession::where('token', $token)->first();
            if (!$androidSession) 
                throw new ApplicationException('Invalid or expired token', 401);
            
            $logs = $request->input('logs');
            return $this->dispatchService->updateStatusesForAndroid($androidSession, $logs);

        } catch (ApplicationException $e) {
            return ApiJsonResponse::error($e->getMessage(), null, $e->getStatusCode());
        } catch (\Exception $e) {
            return ApiJsonResponse::exception($e);
        }
    }
}
