<?php

namespace App\Services\System;

use App\Models\AffiliateLog;
use App\Models\Subscription;
use App\Models\User;
use App\Traits\Manageable;
use App\Services\Core\UserService;
use Illuminate\Support\Facades\DB;

class AffiliateService
{
     use Manageable;

     public UserService $userService;

     /**
      * Summary of affiliateBonus
      * @param \App\Models\User $user
      * @param \App\Models\Subscription $subscription
      * @return void
      */
     public function affiliateBonus(User $user , Subscription $subscription) :void {

          DB::transaction(function() use ($user,$subscription): void {

               $subscription->load('plan');
               $commission  = ((float) $subscription->plan->affiliate_commission / 100 ) * (float) $subscription->amount;
               $commission = convertCurrency($commission, getDefaultCurrencyCode(), "USD");
               if($commission > 0) {

                    $params ['commission_rate']             = $subscription->plan->affiliate_commission ;
                    $params ['subscription_id']             = $subscription->id;
                    $params ['user_id']                     = $user->referral->id;
                    $params ['referred_to']                 = $user->id;
                    $params ['commission_amount']           = $commission;
                    $params ['trx_code']                    = trxNumber();
                    $params ['note']                        = $user->name . " Purchased ".$subscription->plan->name . " Plan";
     
                    $log = AffiliateLog::create($params);
     
                    $user->referral->wallet_balance += $log->commission_amount;
                    $user->referral->save();
               }
          });

    }
}