<?php

namespace App\Http\Controllers\Admin\Core;

use App\Enums\Common\Status;
use App\Enums\DefaultTemplateSlug;
use App\Enums\ServiceType;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\SessionStatusEnum;
use App\Enums\WithdrawLogEnum;
use App\Http\Controllers\Controller;
use App\Traits\Manageable;
use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\Subscription;
use App\Models\PaymentLog;
use App\Models\CreditLog;
use App\Models\EmailCreditLog;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Models\PricingPlan;
use App\Models\GeneralSetting;
use App\Models\WhatsappCreditLog;
use App\Http\Utility\SendMail;
use App\Models\AffiliateLog;
use App\Models\AndroidApi;
use App\Models\AndroidSession;
use Carbon\Carbon;
use App\Models\Gateway;
use App\Models\Template;
use App\Models\WhatsappDevice;
use App\Models\WithdrawLog;
use App\Service\Admin\Core\CustomerService;
use App\Service\Admin\Gateway\WhatsappGatewayService;
use App\Services\System\AffiliateService;
use App\Services\System\Communication\NodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class ReportController extends Controller
{
    use Manageable;

    protected $sendMail;
    public CustomerService $customerService;
    public function __construct(CustomerService $customerService) {

        $this->sendMail        = new SendMail();
        $this->customerService = $customerService;
    }

    public function credit() {
        
        Session::put("menu_active", true);
        $title      = translate("Credit logs");
        $creditLogs = CreditLog::search(['user:name', 'user:email'])
                        ->with('user')
                        ->routefilter()
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());

        return view('admin.report.credit', compact('title', 'creditLogs'));
    }
    

    public function transaction() {

        Session::put("menu_active", true);
        $title          = translate("Transaction log");
        $paymentMethods = PaymentMethod::where('status', 1)->get();
        $transactions   = Transaction::with('user')
                            ->search(['transaction_number', 'amount', 'user:name', 'user:email'])
                            ->latest()
                            ->date()
                            ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                            ->appends(request()->all());
        return view('admin.report.record.transaction', compact('title', 'transactions', 'paymentMethods'));
    }

    public function subscription() {
        
        Session::put("menu_active", true);
        $title         = translate("Subscription history");
        $pricingPlan   = PricingPlan::where('status','=',1)->get();
        $subscriptions = Subscription::where('status', '!=', 0)
                            ->latest()
                            ->search(['trx_number', 'plan:name', 'amount', 'user:name', 'user:email'])
                            ->with('user', 'plan')
                            ->date()
                            ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                            ->appends(request()->all());
        return view('admin.report.record.subscription', compact('title', 'subscriptions', 'pricingPlan'));
    }

    public function paymentLog()
    {
        Session::put("menu_active", true);
        $title = translate("Payment Logs");
        $paymentLogs = PaymentLog::where('status', '!=', 0)
                                    ->with('user', 'paymentGateway')
                                    ->latest()
                                    ->search(['trx_number', 'amount', 'paymentGateway:name', 'user:name', 'user:email'])
                                    ->with('user', 'plan')
                                    ->date()
                                    ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                    ->appends(request()->all());
        $paymentMethods = PaymentMethod::where('status', 1)->get();
        return view('admin.report.record.payment', compact('title', 'paymentLogs', 'paymentMethods'));
    }

    public function paymentDetail($id)
    {
        Session::put("menu_active", true);
        $title = translate("Payment Details");
        $paymentLog = PaymentLog::where('status', '!=', 0)->where('id', $id)->firstOrFail();
        return view('admin.report.payment_detail', compact('title', 'paymentLog'));
    }


    public function approve(Request $request)
    {
        $request->validate([
            'id'       => 'required|integer',
            'feedback' => 'required'
        ]);
        $paymentData = PaymentLog::where('id',$request->id)->where('status',1)->first();
        if($paymentData) {

            $paymentData->feedback = $request->has('feedback') ? $request->has('feedback') : "";
            $paymentData->status = 2;
            $paymentData->save();

            $subscription = Subscription::where('id', $paymentData->subscriptions_id)->first();
            $last_expired_plan = Subscription::where("status", Subscription::EXPIRED)->latest()->first();
            $last_renewed_plan = Subscription::where("status", Subscription::RENEWED)->latest()->first();
          
           
            if( $last_expired_plan && $last_expired_plan?->plan_id == $subscription->plan_id) {
                Subscription::where(["plan_id" => $subscription->plan_id, "status" => Subscription::EXPIRED])->delete();
                $subscription->status = Subscription::RENEWED;
              	
            } elseif($subscription) {
              	
                $subscription->status = Subscription::RUNNING;
                if($last_renewed_plan) {
                    Subscription::where("status", Subscription::RENEWED)->update(["status" => Subscription::INACTIVE]);
                }
                if($last_expired_plan) {
                    Subscription::where("status", Subscription::EXPIRED)->update(["status" => Subscription::INACTIVE]);
                }
            } else {
          		$subscription->status        = Subscription::RUNNING;
            }
            Subscription::where('user_id', $paymentData->user_id)->where('status', 1)->delete();
            AndroidSession::where(["user_id" => $paymentData->user_id, "status" => SessionStatusEnum::CONNECTED])->update(["status" => SessionStatusEnum::DISCONNECTED]);
            $whatsapp_devices = Gateway::where("channel", ChannelTypeEnum::WHATSAPP)
                                            ->where(["user_id" => $paymentData->user_id, "status" => Status::ACTIVE->value])
                                            ->get();
            if(count($whatsapp_devices) > 0) {

                $whatsappGatewayService = new NodeService;
            	foreach($whatsapp_devices as $whatsapp_device) {

                    $whatsapp_device->status = Status::INACTIVE->value;
                    if($whatsappGatewayService->checkServerStatus()) {
                        
                        $whatsappGatewayService->sessionDelete($whatsapp_device->name);
                    }
                    $whatsapp_device->update();
                }
            }
            
            $subscription->plan_id       = $subscription->plan->id;
            $subscription->amount        = $subscription->plan->amount;
            $subscription->expired_date  = $subscription->expired_date->addDays($subscription->plan->duration);
            $subscription->save();
            $previousSubs = Subscription::where('user_id', $paymentData->user_id)->where('status', 3)->pluck('id');
            if ($previousSubs->isNotEmpty()) {
                Subscription::destroy($previousSubs->toArray());
            } 
            PaymentLog::where('user_id',$paymentData->user_id)->where('status', 1)->update(['status' => 3, 'feedback' => "Transaction Process Did Not Complete!"]);
            $user = User::find($paymentData->user_id);
        
            if($subscription->status == Subscription::RENEWED && $subscription->plan->carry_forward == 1) {
                if($subscription->plan->sms->credits == -1) {
                    
                    $user->sms_credit = $subscription->plan->sms->credits;
                } else{

                    $user->sms_credit += $subscription->plan->sms->credits;
                }
                if($subscription->plan->email->credits == -1) {
                    
                    $user->email_credit = $subscription->plan->email->credits;
                } else{
                    
                    $user->email_credit += $subscription->plan->email->credits;
                }
                if($subscription->plan->whatsapp->credits == -1) {

                    $user->whatsapp_credit = $subscription->plan->whatsapp->credits;
                } else {
                    
                    $user->whatsapp_credit += $subscription->plan->whatsapp->credits;
                }
                
            } else {
                
                $user->sms_credit = $subscription->plan->sms->credits;
                $user->email_credit = $subscription->plan->email->credits;
                $user->whatsapp_credit = $subscription->plan->whatsapp->credits;
            }
            Gateway::where("channel", "!=",ChannelTypeEnum::WHATSAPP->value)
                        ->where('user_id', $user->id)
                        ->update([
                            'status' => Status::INACTIVE->value, 
                            'is_default' => 0
                        ]);
            $user->save();
            Transaction::create([
                'user_id'            => $user->id,
                'payment_method_id'  => $paymentData->method_id,
                'amount'             => $paymentData->amount,
                'transaction_type'   => Transaction::PLUS,
                'transaction_number' => $paymentData->trx_number,
                'details'            => 'Enrollment Confirmed:'.$subscription->plan->name.' Plan Subscribed!',
            ]);

            $gateway = $this->getSpecificLogByColumn(
                model: new Gateway(), 
                column: "is_default",
                value: StatusEnum::TRUE->status(),
                attributes: [
                     "user_id" => null,
                     "channel" => ChannelTypeEnum::EMAIL->value,
                ]
            );
    
            $template = $this->getSpecificLogByColumn(
                    model: new Template(), 
                    column: "slug",
                    value: DefaultTemplateSlug::PAYMENT_CONFIRMED->value,
                    attributes: [
                        "user_id" => null,
                        "channel" => ChannelTypeEnum::EMAIL->value,
                        "default" => true,
                        "status"  => Status::ACTIVE->value
                    ]
            );

            $mailCode = [
                'trx'             => $paymentData->trx_number,
                'amount'          => shortAmount($paymentData->final_amount),
                'charge'          => shortAmount($paymentData->charge),
                'currency'        => getDefaultCurrencySymbol(json_decode(site_settings("currencies"), true)),
                'rate'            => shortAmount($paymentData->rate),
                'method_name'     => $paymentData->paymentGateway->name,
                'method_currency' => $paymentData->paymentGateway->currency_code,
                'name'            => $user->name,
                'time'            => Carbon::now(),
            ];
            if($gateway && $template) $this->sendMail->MailNotification($gateway, $template, $user, $mailCode);
            if(site_settings("affiliate_system", StatusEnum::FALSE->status()) == StatusEnum::TRUE->status()) {

                $continuousCommission   = site_settings("affiliate_continuation", Status::INACTIVE->value) == Status::ACTIVE->value;
                $onboardingBonusPlanId  = site_settings(SettingKey::ONBOARDING_BONUS->value);
                $onboardingPlan         = PricingPlan::active()->where("id", $onboardingBonusPlanId)->first();
                $affiliateBonus        =  $continuousCommission  
                                            || Subscription::where('user_id', $user->id)
                                                                ->when($onboardingPlan ,function($query) use($onboardingPlan) {
                                                                    return $query->where('plan_id','!=',@$onboardingPlan->id);
                                                                })->count() < 2;
                $affiliateService = new AffiliateService();
                if($user->referral && $affiliateBonus && $subscription->plan->affiliate_commission > 0)
                    $affiliateService->affiliateBonus($user, $subscription);

            }
            
            $notify[] = ['success', translate("Payment has been approved.")];
            return back()->withNotify($notify);
        }
        return back();
    }

    public function reject(Request $request)
    {
        $request->validate(['id' => 'required|integer']);
        $paymentLog = PaymentLog::where('id',$request->id)->where('status',1)->firstOrFail();
        $paymentLog->feedback = $request->input('feedback');
        $paymentLog->status = 3;
        $paymentLog->save();
        $paymentLog?->plan?->delete();
        $notify[] = ['success', 'Payment has been rejected.'];
        return back()->withNotify($notify);
    }


    ## -------------------- ##
    ## Withdrawal Functions ##
    ## -------------------- ##

    /**
     * Summary of withdrawLogs
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function withdrawLogs(): View
    {
        Session::put("menu_active", true);
        $title  = translate("Withdraw Logs");
        $logs   = WithdrawLog::with('user', 'method')
                                    ->latest()
                                    ->search(['trx_code', 'final_amount', 'method:name', 'user:name', 'user:email'])
                                    ->date()
                                    ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                    ->appends(request()->all());
        return view('admin.report.record.withdraw', compact('title', 'logs'));
    }

    /**
     * Summary of withdrawDetail
     * @param string|null $trx_code
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View
     */
    public function withdrawDetail(string|null $trx_code = null): VIew
    {
        Session::put("menu_active", true);
        $title  = translate("Withdraw Details");
        $log    = WithdrawLog::where('trx_code', $trx_code)
                                ->with('user', 'method')
                                ->firstOrFail();
        return view('admin.report.withdraw_detail', compact('title', 'log'));
    }

    /**
     * Summary of updateWithdrawStatus
     * @param string|null $trx_code
     * @return RedirectResponse
     */
    public function updateWithdrawStatus(string|null $trx_code = null): RedirectResponse
    {
        $log    = WithdrawLog::where('trx_code', $trx_code)
                                ->with('user', 'method')
                                ->firstOrFail();

        if(request()->input("status") == WithdrawLogEnum::REJECTED->value)
            return $this->rejectWithdraw($log);
        
        $user = $log->user;
        if(!$user) return $this->rejectWithdraw($log);

        if($user->wallet_balance < $log->final_amount) 
           return $this->rejectWithdraw($log);
        
        return $this->approveWithdraw($log, $user);
    }


    /**
     * Summary of rejectWithdraw
     * @param \App\Models\WithdrawLog $log
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rejectWithdraw(WithdrawLog $log): RedirectResponse{
        $log->status = WithdrawLogEnum::REJECTED->value;
        $log->notes = request()->input('notes', translate("No notes provided"));
        $log->save();
        $notify[] = ['success', translate("Withdraw request has been rejected.")];
        return back()->withNotify($notify);
    }

    /**
     * Summary of approveWithdraw
     * @param \App\Models\WithdrawLog $log
     * @param \App\Models\User $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approveWithdraw(WithdrawLog $log, User $user): RedirectResponse
    {
        $user->wallet_balance -= $log->final_amount;
        $user->save();
        $log->status = WithdrawLogEnum::APPROVED->value;
        $log->notes = request()->input('notes', translate("No notes provided"));
        $log->save();
        $notify[] = ['success', translate("Withdraw request has been approved.")];
        return back()->withNotify($notify);
    }

    ## ------------------- ##
    ## Affiliate Functions ##
    ## ------------------- ##

    public function affiliateLogs(): View
    {
        Session::put("menu_active", true);
        $title = translate("Affiliate Logs");
        $logs = AffiliateLog::with('user', 'affiliate', 'subscription', 'subscription.plan')
                                ->latest()
                                ->search(['trx_code', 'user:name', 'user:email', 'affiliate:name', 'affiliate:email'])
                                ->date()
                                ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                                ->appends(request()->all());
        return view('admin.report.record.affiliate', compact('title', 'logs'));
    }
}