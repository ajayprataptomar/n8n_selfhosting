<?php

namespace App\Service\Admin\Core;

use Carbon\Carbon;
use App\Models\User;
use App\Models\SMSlog;
use App\Models\EmailLog;
use App\Models\CreditLog;
use App\Enums\StatusEnum;
use App\Enums\ServiceType;
use App\Models\PricingPlan;
use App\Models\WhatsappLog;
use App\Models\Subscription;
use App\Jobs\RegisterMailJob;
use App\Enums\SubscriptionStatus;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\CommunicationStatusEnum;
use App\Exceptions\ApplicationException;
use App\Http\Requests\UserCreditRequest;
use App\Jobs\PermanentlyDeleteUserJob;
use App\Models\DispatchLog;
use App\Service\Admin\Dispatch\SmsService;
use App\Services\System\DeviceLimitService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\AbstractPaginator;
use Throwable;

class CustomerService
{
    /**
     * findById
     *
     * @param int|string $userId
     * 
     * @return User
     */
    public function findById(int|string $userId): User {

        return User::where('id', $userId)->first();
    }
    /**
     * @param $userUid
     * 
     * @return User
     * 
     */
    public function findByUid(string $userUid): User {

        return User::where('uid', $userUid)->first();
    }

    /**
     * @return AbstractPaginator
     * 
     */
    public function getPaginateUsers(bool $withTrashed = false): AbstractPaginator {

        return User::when($withTrashed, fn(Builder $q): Builder => $q->onlyTrashed())
                        ->filter(['email_verified_status'])
                        ->routefilter()
                        ->search(['name','email'])
                        ->latest()
                        ->date()
                        ->paginate(paginateNumber(site_settings("paginate_number")))->onEachSide(1)
                        ->appends(request()->all());
    }

    /**
     * @param User $user
     * 
     * @return array $notify
     * 
     */
    public function applyOnboardingBonus(User $user): array {

        if(site_settings('onboarding_bonus', StatusEnum::FALSE->status()) == StatusEnum::FALSE->status()) {
            
            $notify[] = ['success', translate("Added new user succesfully")];
            return $notify;
        }
        $plan = PricingPlan::find(site_settings('onboarding_bonus_plan'));
        if(!$plan) {
            $notify[] = ['success', translate("Added new user succesfully")];
            return $notify;
        }
        
        $user->sms_credit      = $plan->sms->is_allowed ? $plan->sms->credits : 0;
        $user->email_credit    = $plan->email->is_allowed ? $plan->email->credits : 0;
        $user->whatsapp_credit = $plan->whatsapp->is_allowed ? $plan->whatsapp->credits : 0;
        
        $user->save();

        Subscription::create([

            'user_id'      => $user->id,
            'plan_id'      => $plan->id,
            'expired_date' => Carbon::now()->addDays($plan->duration),
            'amount'       => $plan->amount,
            'trx_number'   => trxNumber(),
            'status'       => SubscriptionStatus::RUNNING->value,
        ]);
        $notify[] = ['success', translate("Added new user with "). $plan->name. translate(" as an onboarding bonus.")];
        return $notify;
    }
    
    /**
     * logs
     *
     * @param int|null $userId
     * 
     * @return array
     */
    public function logs(?int $userId = null): array {

         return [

            "sms" => [
                'all'     => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::SMS)->count(),
                'success' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::SMS)
                                                ->where('status', CommunicationStatusEnum::DELIVERED->value)
                                                ->count(),
                'pending' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::SMS)
                                                ->where('status', CommunicationStatusEnum::PENDING->value)
                                                ->count(),
                'failed'  => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::SMS)
                                                ->where('status', CommunicationStatusEnum::FAIL->value)
                                                ->count(),
            ],
            "email" => [
                'all'     => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::EMAIL)
                                                ->count(),
                'success' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::EMAIL)
                                                ->where('status', CommunicationStatusEnum::DELIVERED->value)
                                                ->count(),
                'pending' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::EMAIL)
                                                ->where('status', CommunicationStatusEnum::PENDING->value)
                                                ->count(),
                'failed'  => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::EMAIL)
                                                ->where('status', CommunicationStatusEnum::FAIL->value)
                                                ->count(),
            ],
            "whats_app" => [
                'all'     => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::WHATSAPP)
                                                ->count(),
                'success' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::WHATSAPP)
                                                ->where('status', CommunicationStatusEnum::DELIVERED->value)
                                                ->count(),
                'pending' => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::WHATSAPP)
                                                ->where('status', CommunicationStatusEnum::PENDING->value)
                                                ->count(),
                'failed'  => DispatchLog::when($userId, fn(Builder $q) : Builder => 
                                                    $q->where("user_id", $userId)) 
                                                ->where('type', ChannelTypeEnum::WHATSAPP)
                                                ->where('status', CommunicationStatusEnum::FAIL->value)
                                                ->count(),
            ]
        ];
    }

    /**
     * @param UserCreditRequest $request
     * 
     * @return array
     * 
     */
    public function buildCreditArray(UserCreditRequest $request): array {

        $data = [];
        foreach(array_keys(ServiceType::toArray()) as $key) {
    
            $data[strtolower($key)] = (int)$request->input(strtolower($key).'_credit', 0);
        }
        return $data;
    }

    /**
     * @param User $user
     * 
     * @param int $totalCredit
     * 
     * @param int $serviceType
     * 
     * @param string $message
     * 
     * @return void
     * 
     */
    public function deductCreditLog($user, int|null $totalCredit, int $serviceType, bool $manual = false, null|string $message = null): void {

        
        $column_name = strtolower(ServiceType::getValue($serviceType))."_credit";
        
        $creditInfo              = new CreditLog();
        $creditInfo->user_id     = $user->id;
        $creditInfo->type        = $serviceType;
        $creditInfo->manual      = $manual ? StatusEnum::TRUE->status() : StatusEnum::FALSE->status();
        $creditInfo->credit_type = StatusEnum::FALSE->status();
        $creditInfo->credit      = $totalCredit ?? 0;
        $creditInfo->trx_number  = trxNumber();
        $creditInfo->post_credit = $user->$column_name;
        $creditInfo->details     = $message ? $message : $totalCredit.translate(" credit deducted for sending ").ucfirst(strtolower(ServiceType::getValue($serviceType))).translate(" content");
        $creditInfo->save();
        
        if($user->$column_name != -1) {
            
            $user->$column_name -= $totalCredit;
            $user->$column_name = $user->$column_name <= -1 ? -1 : $user->$column_name;
        }
        $user->save();
    }

    /**
     * @param User $user
     * 
     * @param int $totalCredit
     * 
     * @param int $serviceType
     * 
     * @param string $message
     * 
     * @return void
     * 
     */
    public static function addedCreditLog($user, int|null $totalCredit, int $serviceType, bool $manual = false, null|string $message = null): void {
        
        $column_name = strtolower(ServiceType::getValue($serviceType))."_credit";
        
        if($user->$column_name > -1) {
            
            $creditInfo              = new CreditLog();
            $creditInfo->user_id     = $user->id;
            $creditInfo->type        = $serviceType;
            $creditInfo->manual      = $manual ? StatusEnum::TRUE->status() : StatusEnum::FALSE->status();
            $creditInfo->credit_type = StatusEnum::TRUE->status();
            $creditInfo->credit      = $totalCredit ?? 0;
            $creditInfo->trx_number  = trxNumber();
            $creditInfo->post_credit = $user->$column_name;
            $creditInfo->details     = $message ? $message : $totalCredit.' '.ucfirst(strtolower(ServiceType::getValue($serviceType))).translate(" credit added");
            $creditInfo->save();
            
            $user->$column_name += $totalCredit;
            $user->save();
        } 
    }

    /**
     * @param User $user
     * 
     * @param $request
     * 
     * @return void
     * 
     */
    public static function updatePlan(User $user, $request) {

        $new_plan = PricingPlan::where("id", $request->input("pricing_plan"))->firstorFail();

        // Get the old plan for smart device management
        $oldSubscription = Subscription::where([
            "user_id" => $user->id,
            "status" => Subscription::RUNNING
        ])->first();

        $oldPlan = $oldSubscription ? PricingPlan::find($oldSubscription->plan_id) : null;

        // Mark old subscription as inactive
        Subscription::where([
            "user_id" => $user->id,
            "status" => Subscription::RUNNING
        ])->update([
            "status" => Subscription::INACTIVE
        ]);

        // Create new subscription
        Subscription::create([
            "user_id"      => $user->id,
            "plan_id"      => $request->input("pricing_plan"),
            "amount"       => $new_plan->amount,
            "expired_date" => Carbon::now()->addDays($new_plan->duration),
            "trx_number"   => trxNumber(),
            "status"       => Subscription::RUNNING,
        ]);

        // Smart device management - only deactivate excess devices on downgrade
        $deviceLimitService = new DeviceLimitService();
        $deviceChangeResult = $deviceLimitService->handleSubscriptionChange(
            $user,
            $oldPlan,
            $new_plan,
            'admin_plan_change'
        );

        \Log::info('CustomerService: Admin plan change device management', [
            'user_id' => $user->id,
            'old_plan' => $oldPlan?->name ?? 'None',
            'new_plan' => $new_plan->name,
            'change_type' => $deviceChangeResult['change_type'] ?? 'unknown',
            'actions' => $deviceChangeResult['actions'] ?? []
        ]);

        // Update credits
        $user->sms_credit      = $new_plan->sms->credits;
        $user->email_credit    = $new_plan->email->credits;
        $user->whatsapp_credit = $new_plan->whatsapp->credits;
    } 

    public function canSpendCredits($user, $allowed_access, $type, $quantity = null) {

        
        $pass = false;
        $allowed_per_day = array_key_exists('credits_per_day', $allowed_access->{strtolower(ServiceType::getValue($type))}) 
                            ? $allowed_access->{strtolower(ServiceType::getValue($type))}['credits_per_day'] 
                            : 0;
        
       

        if ($allowed_per_day == 0) {
            $pass = true;
        } else {

            if($quantity && $allowed_per_day < $quantity) {

                return false;
            }
            
            $baseQuery = CreditLog::where('user_id', $user->id)
                ->where('type', $type)
                ->where('manual', StatusEnum::FALSE->status())
                ->whereDate('created_at', Carbon::today());
    
            $credits_deducted = (clone $baseQuery)
                ->where('credit_type', StatusEnum::FALSE->status())
                ->sum('credit');
    
            $credits_added = (clone $baseQuery)
                ->where('credit_type', StatusEnum::TRUE->status())
                ->sum('credit');
    
            $net_credits_spent = $credits_deducted - $credits_added;
            if ($net_credits_spent < $allowed_per_day) {
                $pass = true;
            }
        }
        return $pass;
    }

    /**
     * Soft delete (move user to trash)
     *
     * @param string|null $uid
     * @return void
     */
    public function softDeleteUser(string|null $uid = null): void
    {
        if (!$uid) {
            throw new \Exception('User UID is required for soft delete.');
        }
        $user = User::where('uid', $uid)->first();
        if (!$user) {
            throw new \Exception('User not found.');
        }
        $user->delete(); 
        $user->is_erasing = false;
        $user->save();
    }

    /**
     * Restore a trashed user
     *
     * @param string|null $uid
     * @return void
     */
    public function restoreUser(string|null $uid = null): void
    {
        if (!$uid) {
            throw new \Exception('User UID is required for restore.');
        }
        $user = User::onlyTrashed()->where('uid', $uid)->first();
        if (!$user) {
            throw new \Exception('Trashed user not found.');
        }
        $user->restore();
        $user->is_erasing = false;
        $user->save();
    }

    /**
     * permanentlyDeleteUser
     *
     * @param string|null|null $uid
     * 
     * @return RedirectResponse
     */
    public function permanentlyDeleteUser(string|null $uid = null): RedirectResponse
    {
        if (!$uid) {
            throw new \Exception('User UID is required for permanent delete.');
        }

        $relations = [
            'androidSession', 'androidSims', 'campaigns', 'campaignUnsubscribers',
            'contacts', 'contactGroups', 'creditLogs', 'dispatchDelays', 'dispatchLogs',
            'gateways', 'imports', 'messages', 'paymentLogs', 'webhookLogs', 
            'subscriptions', 'supportTickets', 'templates', 'affiliateLogs'
        ];

        $user = User::withTrashed()
            ->where('uid', $uid)
            ->withCount($relations)
            ->first();

        if (!$user) {
            throw new \Exception('User not found.');
        }

        try {
            User::withTrashed()
                ->where("referral_id", $user->id)
                ->update([
                    'referral_id' => null,
                ]);
        } catch(Throwable $th) {}

            

        $totalEntries = collect($relations)->sum(function ($relation) use ($user) {
            return $user->{"{$relation}_count"} ?? 0;
        });

        $user->is_erasing = true;
        $user->total_entries = $totalEntries;
        $user->total_deleted_entries = 0;
        $user->save();

        PermanentlyDeleteUserJob::dispatch($user);

        $notify[] = ['success', translate("User deletion is processing please wait")];
        return back()->withNotify($notify);
    }
}