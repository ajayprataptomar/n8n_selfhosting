<?php

namespace App\Managers;

use App\Enums\System\ChannelTypeEnum;
use App\Models\Admin;
use App\Models\Template;
use App\Models\User;
use App\Models\WithdrawMethod;
use App\Traits\Manageable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentManager
{
     use Manageable;
     
     /**
      * Summary of fetchWithdrawMethods
      *
      * @param string|null|null $uid
      * @return Collection|LengthAwarePaginator
      */
     public function fetchWithdrawMethods(string|null $uid = null): Collection|LengthAwarePaginator {

        return WithdrawMethod::when($uid, fn(Builder $q): Builder => 
                                        $q->where('uid', $uid))
                                   ->search([
                                        'name',
                                        'currency_code'
                                   ])->filter(['status'])
                                        ->latest()
                                        ->date()
                                        ->paginate(paginateNumber(site_settings('paginate_number')))
                                                  ->onEachSide(1)
                                                  ->appends(request()->all());
    
     }

     /**
      * Summary of findByKey
      * @param string $value
      * @param string $key
      * @return object|WithdrawMethod|\Illuminate\Database\Eloquent\Model|null
      */
     public function findByKey(string $value, string $key = "uid"): WithdrawMethod{ 
          
          return WithdrawMethod::where($key, $value)->first();
     }
}