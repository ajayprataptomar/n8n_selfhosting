<?php
namespace App\Service\Admin\Payment;

use App\Enums\StatusEnum;
use App\Managers\PaymentManager;
use Illuminate\Support\Str;
use App\Models\PaymentMethod;
use App\Models\WithdrawMethod;
use App\Service\Admin\Core\FileService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class PaymentGatewayService
{
    protected $paymentManager;

    public function __construct()
    {
        $this->paymentManager = new PaymentManager();
    }

    ## ------------------------- ##
    ## Withdraw Method Functions ##
    ## ------------------------- ##

    /**
     * Summary of getWithdrawMethods
     * @param string|null $uid
     * @return \Illuminate\Contracts\View\View
     */
    public function getWithdrawMethods(string|null $uid = null): View {

        $title = translate("Withdraw Methods");
        $logs  = $this->paymentManager->fetchWithdrawMethods(uid: $uid); 
        return view('admin.payment.withdraw.index', 
        compact('title', 'logs', 'uid'));
    }

    /**
     * Summary of createdWithdrawMethods
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function createdWithdrawMethods(): View {

        $title      = translate("Create Withdraw Method");
        $currencies = getActiveCurrencies();
        return view('admin.payment.withdraw.create', 
        compact('title', 'currencies'));
    }
    

    /**
     * Summary of WithdrawMethodSave
     * @param array $data
     * @param string|null $uid
     * @return \Illuminate\Http\RedirectResponse
     */
    public function WithdrawMethodSave(array $data, string|null $uid = null): RedirectResponse {

        $image = Arr::get($data,'image');
        unset($data['image']);
        $data = $this->processCustomFields($data);
        
        $withdrawMethod = DB::transaction(function () use ($data, $uid, $image) {

            $withdrawMethod = WithdrawMethod::updateOrCreate([
                "uid" => $uid
            ], $data);

            if($image) {

                try { 
                    $fileService            = new FileService();
                    $withdrawMethod->image  = $fileService->uploadFile(
                        file: $image,
                        key: 'withdraw_method', 
                        file_path: filePath()['withdraw_method']['path'], 
                        file_size: filePath()['withdraw_method']['size'], 
                        delete_file: false);
                    $withdrawMethod->save();
                }catch (Exception) {
                    $notify[] = ['error', 'Image could not be uploaded.'];
                    return back()->withNotify($notify);
                }
            }
            
            return $withdrawMethod;
        });


        $notify[] = ['success', translate("Successfully saved withdraw method: "). $withdrawMethod->name];
        return back()->withNotify($notify);

    }

    /**
     * Summary of editWithdrawMethods
     * @param string|null $uid
     * @return \Illuminate\View\View
     */
    public function editWithdrawMethods(string|null $uid = null): View {

        if(!$uid) {
            $notify[] = ['error', translate("Invalid Withdraw Method")];
            return back()->withNotify($notify);
        }
        
        $title      = translate("Create Withdraw Method");
        $currencies = getActiveCurrencies();
        $log        = $this->paymentManager->findByKey($uid);
        
        return view('admin.payment.withdraw.edit', 
        compact('title', 'currencies', 'log'));
    }

    public function WithdrawMethodDelete(string|null $uid = null): RedirectResponse { 

        if(!$uid) {
            $notify[] = ['error', translate("Invalid Withdraw Method")];
            return back()->withNotify($notify);
        }
        $log = $this->paymentManager->findByKey($uid);
        $log->delete();
        //todo: Check withdrawal report logs
        $notify[] = ['success', translate("Successfully deleted withdraw method: "). $log->name];
        return back()->withNotify($notify);
    }


    ## --------------------------- ##
    ## Additional Helper Functions ##
    ## --------------------------- ##

    /**
     * Summary of processCustomFields
     * @param array $data
     * @return array
     */
    private function processCustomFields(array $data): array
    {
        $parameters = [];
        
        foreach (Arr::get($data, 'field_name', []) as $i => $fieldName) {
            $field = [
                'field_label' => $fieldName,
                'field_name' => strtolower(str_replace(' ', '_', $fieldName)),
                'field_type' => Arr::get($data, "field_type.{$i}")
            ];
            $parameters[$field['field_name']] = $field;
        }
        
        $data['parameters'] = $parameters;
        Arr::forget($data, ['field_name', 'field_type']);
        
        return $data;
    }


    ## Old Functions ##

    public function automaticGatewayUpdate($request, $id) {
        
        $fileService                    = new FileService();
        $payment_method                 = PaymentMethod::findOrFail($id);
        $payment_method->currency_code  = $request->input('currency_code');
        $payment_method->percent_charge = $request->input('percent_charge');
        $payment_method->rate           = $request->input('rate');

        $parameter = [];
        foreach ($payment_method->payment_parameter as $key => $value) {
            $parameter[$key] = $request->input("method.{$key}");
        }
        $payment_method->payment_parameter = $parameter;

        if($request->hasFile('image')){
            try {
                $payment_method->image = $fileService->uploadFile($request->file('image'), 'automatic_payment', null, null, false);
            }catch (\Exception) {
                $notify[] = ['error', 'Image could not be uploaded.'];
                return back()->withNotify($notify);
            }
        }
        $payment_method->save();

    }

    public function manualGatewayUpdate($request, $id) {
        
        $fileService                   = new FileService();
        $paymentMethod                 = PaymentMethod::findOrFail($id);
        $paymentMethod->name           = $request->input('name');
        $paymentMethod->currency_code  = $request->input('currency_code');
        $paymentMethod->percent_charge = $request->input('percent_charge');
        $paymentMethod->rate           = $request->input('rate');
        $parameter = [];
       
        if($request->has('field_name')){
            for($i=0; $i<count($request->input('field_name')); $i++){
                $parameter = $this->getArr($request, $i, $parameter);
            }
        }

        $array_push = [];
        $array_push['payment_gw_info'] = $request->has('payment_gw_info') ? $request->input('payment_gw_info') : "";
        $parameter[] = $array_push;
        $paymentMethod->payment_parameter = $parameter;

        if($request->hasFile('image')){
            $paymentMethod->image = $fileService->uploadFile($request->image, 'manual_payment');
        }  
        $paymentMethod->save();
    }

    public function manualGatewayStore($request) {
        
        $fileService      = new FileService();
        $new_code         = "500";
        $paymentMethodLog = PaymentMethod::manualMethod()->orderBy('unique_code','DESC')->limit(1)->first();

        if ($paymentMethodLog != null) {

            $new_code = intval(substr($paymentMethodLog->unique_code, 6, 3)) + 1;
        }

        $paymentMethod                 = new PaymentMethod();
        $paymentMethod->name           = $request->input('name');
        $paymentMethod->currency_code  = $request->input('currency_code');
        $paymentMethod->percent_charge = $request->input('percent_charge');
        $paymentMethod->rate           = $request->input('rate');
        $paymentMethod->status         = StatusEnum::TRUE->status();
        $parameter = [];

        if($request->has('field_name')) {

            for($i=0; $i<count($request->input('field_name')); $i++) {

                $parameter = $this->getArr($request, $i, $parameter);
            }
        }   
        $array_push = [];
        $array_push['payment_gw_info'] = $request->has('payment_gw_info') ? $request->input('payment_gw_info') : "";
        $parameter[] = $array_push;
        $paymentMethod->payment_parameter = $parameter;

        if($request->hasFile('image')) {

            try { 

                $paymentMethod->image = $fileService->uploadFile($request->file('image'), 'manual_payment', null, null, false);
            }catch (\Exception) {

                $notify[] = ['error', 'Image could not be uploaded.'];
                return back()->withNotify($notify);
            }
        }

        $paymentMethod->unique_code = "MANUAL".$new_code;
        $paymentMethod->save();
    }



    /**
     * @param ManualPaymentRequest $request
     * @param int $i
     * @param array $parameter
     * @return array
     */
    public function getArr($request, int $i, array $parameter): array
    {
        $array = [];
        $array['field_label'] = $request->input("field_name.{$i}");
        $array['field_name'] = strtolower(str_replace(' ', '_', $request->input("field_name.{$i}")));
        $array['field_type'] = $request->input("field_type.{$i}");
        $parameter[$array['field_name']] = $array;
        return $parameter;
    }

    public function statusUpdate($request) {
        
        try {
            
            $status   = true;
            $reload   = false;
            $message  = translate('Payment gateway status updated successfully');
            $payment_method = PaymentMethod::where("id", $request->input('id'))->first();
            
            if($request->value == StatusEnum::TRUE->status()) {
                
                $payment_method->status = StatusEnum::FALSE->status();
                $payment_method->update();
            } else {

                $payment_method->status = StatusEnum::TRUE->status();
                $payment_method->update();
            }

        } catch (\Exception $error) {

            $status  = false;
            $message = $error->getMessage();
        }

        return json_encode([
            'reload'  => $reload,
            'status'  => $status,
            'message' => $message
        ]);
    }
}
