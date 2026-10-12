<?php

namespace App\Http\Controllers\Admin\Payment;

use Exception;
use Illuminate\View\View;
use App\Traits\ModelAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\WithdrawMethod;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Session;
use App\Exceptions\ApplicationException;
use App\Http\Requests\Admin\Payment\WithdrawMethodRequest;
use App\Service\Admin\Payment\PaymentGatewayService;

class WithdrawMethodController extends Controller
{
    use ModelAction;

    public $paymentGatewayService;
    public function __construct() {

        $this->paymentGatewayService = new PaymentGatewayService();
    }

    /**
     * Summary of index
     * @return View
     */
    public function index(): View {
        
        Session::put("menu_active", true);
        return $this->paymentGatewayService->getWithdrawMethods();
    }

    /**
     * Summary of create
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function create(): View
    {
        Session::put("menu_active", true);
        return $this->paymentGatewayService->createdWithdrawMethods();
    }

    /**
     * Summary of store
     * @param \App\Http\Requests\Admin\Payment\WithdrawMethodRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(WithdrawMethodRequest $request): RedirectResponse {

        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->paymentGatewayService->WithdrawMethodSave($data);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }

    /**
     * Summary of updateStatus
     * @param \Illuminate\Http\Request $request
     * @return string|\Illuminate\Http\JsonResponse
     */
    public function updateStatus(Request $request): string {
        
        try {

            $this->validateStatusUpdate(
                request: $request,
                tableName: 'withdraw_methods', 
                isJson: true,
                keyColumn: 'uid'
            );

            $notify = $this->statusUpdate(
                request: $request->except('_token'),
                actionData: [
                    'message' => translate('Withdraw method status updated successfully'),
                    'model'   => new WithdrawMethod(),
                    'column'  => $request->input('column'),
                    'filterable_attributes' => [
                        'uid' => $request->input('uid')
                    ],
                    'reload' => true
                ]
            );

            return $notify;

        } catch (Exception $e) {
            
            return response()->json([
                'status'    => false,
                'message'   => getEnvironmentMessage($e->getMessage()),
            ], Response::HTTP_INTERNAL_SERVER_ERROR); 
        }
    }

    /**
     * Summary of edit
     * @param string|null $uid
     * @return \Illuminate\View\View
     */
    public function edit(string|null $uid = null): View
    {
        Session::put("menu_active", true);
        return $this->paymentGatewayService->editWithdrawMethods($uid);
    }

    /**
     * Summary of update
     * @param \App\Http\Requests\Admin\Payment\WithdrawMethodRequest $request
     * @param string|null $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(WithdrawMethodRequest $request, string|null $uid = null): RedirectResponse {

        try {
            $data = $request->all();
            unset($data["_token"]);
            return $this->paymentGatewayService->WithdrawMethodSave($data, $uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
    
    /**
     * Summary of destroy
     * @param \Illuminate\Http\Request $request
     * @param string $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, string $uid): RedirectResponse {
        
        try {

            $data = $request->all();
            unset($data["_token"]);
            return $this->paymentGatewayService->WithdrawMethodDelete($uid);

        } catch (ApplicationException $e) {
            
            $notify[] = ["error", translate($e->getMessage())];
            return back()->withNotify($notify);

        } catch (Exception $e) {
            
            $notify[] = ["error", getEnvironmentMessage($e->getMessage())];
            return back()->withNotify($notify);
        }
    }
}
