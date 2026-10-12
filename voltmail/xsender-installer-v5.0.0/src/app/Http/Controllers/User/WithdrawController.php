<?php

namespace App\Http\Controllers\User;

use App\Exceptions\ApplicationException;
use App\Http\Requests\User\WithdrawRequest;
use Illuminate\View\View;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Session;
use App\Services\System\WithdrawService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class WithdrawController extends Controller
{
    public WithdrawService $withdrawService;

    public function __construct(WithdrawService $withdrawService) { 

        $this->withdrawService = $withdrawService;
    }

    public function index(): View
    {
        Session::put("menu_active", true);
        $user = auth()->user();
        return $this->withdrawService->getWithdrawLogs($user); 
    }

    /**
     * Summary of create
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        $user = auth()->user();
        return $this->withdrawService->createWithdrawRequest($user); 
    }

    public function store(WithdrawRequest $request): JsonResponse
    {
        try {

            $data = $request->all();
            unset($data["_token"]);
            $user = auth()->user();
            return $this->withdrawService->saveWithdrawRequest(user: $user, data: $data);

        } catch (ApplicationException $e) {
            
            return response()->json([
                'status' => false,
                'message' => translate($e->getMessage()),
            ], $e->getStatusCode()); 

        } catch (Exception $e) {
            
            return response()->json([
                'status' => false,
                'message' => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }
}
