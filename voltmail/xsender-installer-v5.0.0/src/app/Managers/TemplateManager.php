<?php

namespace App\Managers;

use App\Enums\System\ChannelTypeEnum;
use App\Models\Admin;
use App\Models\Template;
use App\Models\User;
use App\Traits\Manageable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class TemplateManager
{
     use Manageable;
     
     /**
      * logs
      *
      * @param ChannelTypeEnum $channel
      * @param bool $paginated
      * @param bool $adminSpecific
      * @param bool $userSpecific
      * @param bool $isDefault
      * @param string|int|null $cloudId
      * @param User|null $user
      * 
      * @return Collection
      */
     public function logs(
          ChannelTypeEnum $channel, 
          bool $paginated          = false, 
          bool $adminSpecific      = false, 
          bool $userSpecific       = false, 
          bool $isDefault          = false, 
          bool $isPlugin           = false, 
          string|int|null $cloudId = null, 
          ?User $user              = null
     ) : Collection|LengthAwarePaginator {

          return Template::where("global", false)
                              ->where("channel", $channel)
                              ->when($adminSpecific, fn(Builder $q): Builder =>
                                   $q->whereNull("user_id"))
                              ->when($userSpecific, fn(Builder $q): Builder =>
                                   $q->whereNotNull("user_id"))
                              ->when($isDefault, fn(Builder $q): Builder =>
                                   $q->where("default", true), 
                                        fn(Builder $q): Builder =>
                                             $q->where("default", false))
                              ->when($isPlugin, fn(Builder $q): Builder =>
                                   $q->where("plugin", true), 
                                        fn(Builder $q): Builder =>
                                             $q->where("plugin", false))
                              ->when($user, fn(Builder $q): Builder =>
                                   $q->where("user_id", $user->id))
                              ->when($channel == ChannelTypeEnum::WHATSAPP && $cloudId, 
                                   fn(Builder $q): Builder =>
                                        $q->where("cloud_id", $cloudId))
                              ->when(!$paginated, fn(Builder $q): Collection =>
                                   $q->get(), 
                                        fn(Builder $q): LengthAwarePaginator =>
                                             $q->paginate(site_settings('paginate_number', 10))
                                                  ->appends(request()->all()));
     }
}