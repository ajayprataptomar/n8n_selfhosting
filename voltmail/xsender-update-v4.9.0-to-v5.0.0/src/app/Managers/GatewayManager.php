<?php

namespace App\Managers;

use App\Enums\Common\Status;
use App\Enums\SettingKey;
use App\Enums\StatusEnum;
use App\Enums\System\Gateway\WhatsAppGatewayTypeEnum;
use App\Models\Admin;
use App\Models\User;
use App\Models\Gateway;
use App\Models\AndroidSession;
use App\Models\AndroidSim;
use App\Traits\Manageable;
use Illuminate\Support\Collection;
use App\Enums\System\ChannelTypeEnum;
use App\Enums\System\Gateway\SmsGatewayTypeEnum;
use App\Enums\System\SessionStatusEnum;
use App\Exceptions\ApplicationException;
use App\Models\DispatchLog;
use Illuminate\Broadcasting\Channel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class GatewayManager
{
     use Manageable;
     
     /**
      * getGateways
      *
      * @param ChannelTypeEnum $channel
      * @param bool $loadPaginated
      * @param bool $groupBy
      * @param WhatsAppGatewayTypeEnum|SmsGatewayTypeEnum|null $type
      * @param User|null $user
      * 
      * @return Collection
      */
     public function getGateways(ChannelTypeEnum $channel, bool $loadPaginated = true, bool $groupBy = true, WhatsAppGatewayTypeEnum|SmsGatewayTypeEnum|null $type = null, User|Admin|null $user = null): Collection|LengthAwarePaginator|null
     {
          $allowedAccess   = $user ? (object)planAccess($user) : null;
          return Gateway::select([

               'id',
               'uid',
               'name',
               'type',
               'status',
               'channel',
               'address',
               'meta_data',
               'is_default',
               'per_message_min_delay',
               'per_message_max_delay',
               'delay_after_count',
               'reset_after_count',
               'bulk_contact_limit',
               'delay_after_duration',

          ])->search(['name'])
               ->date()
               ->where('channel', $channel)
               ->when($user instanceof User,
                    fn(Builder $query): Builder =>
                         $query->when((($channel == ChannelTypeEnum::EMAIL || $channel == ChannelTypeEnum::SMS)
                         && @$allowedAccess?->type == StatusEnum::FALSE->status()) || $channel == ChannelTypeEnum::WHATSAPP,
                                   fn(Builder $q): Builder =>
                                        $q->where('user_id', $user->id)),
                                   fn(Builder $q): Builder =>
                                        $q->whereNull("user_id"))
               ->when($type, fn(Builder $q): Builder =>
                    $q->where("type", $type))
               ->orderBy("is_default", "DESC")
               // ->when($groupBy , fn(Builder $q): Builder => 
               //      $q->groupBy("type"))
               ->withCount(["templates"])
               ->when(!$loadPaginated, fn(Builder $q): Collection =>
                    $q->get(), fn(Builder $q): LengthAwarePaginator =>
                    $q->paginate(site_settings('paginate_number', 10))
                    ->appends(request()->all()));;
     }
    

     /**
      * getAndroidSessions
      *
      * @param bool $loadPaginated
      * @param User|null $user
      * 
      * @return Collection|LengthAwarePaginator|null
      */
     public function getAndroidSessions($loadPaginated = false, User|Admin|null $user = null): Collection|LengthAwarePaginator|null
     {
          $allowedAccess   = $user ? (object)planAccess($user) : null;
          return AndroidSession::select([
               'id',
               'name',
               'token',
               'status',
               'qr_code',
               'created_at'
          ])->search(['name'])
               ->filter(['status'])
               ->date()
               ->when($user instanceof User && @$allowedAccess?->type == StatusEnum::FALSE->status(), fn(Builder $query): Builder =>
               $query->where('user_id', $user->id),
                    fn(Builder $q): Builder => $q->whereNull("user_id"))
               ->with(['androidSims' => function ($query) use ($user) {
                    $query->active()
                         ->select(['id', 'sim_number', 'android_session_id'])
                         ->when($user instanceof User, fn(Builder $q): Builder =>
                         $q->where("user_id", $user->id), fn(Builder $q): Builder => $q->whereNull("user_id"));
               }])
               ->withCount(['androidSims' => function ($query) use ($user) {
                    $query->when($user instanceof User, fn(Builder $q): Builder =>
                         $q->where("user_id", $user->id), fn(Builder $q): Builder => $q->whereNull("user_id"));
               }])
               ->latest()
               ->when(!$loadPaginated, fn(Builder $q): Collection =>
                    $q->get(), fn(Builder $q): LengthAwarePaginator =>
                    $q->paginate(site_settings('paginate_number', 10))
                    ->appends(request()->all()));
     }

     /**
      * Summary of getAndroidSession
      * @param string $column
      * @param string|int|null $value
      * @param bool $ignoreUser
      * @param bool $isConnected
      * @param mixed $user
      * @return Builder<AndroidSession>|object|\Illuminate\Database\Eloquent\Model|null
      */
     public function getAndroidSession(string $column = "id", string|int|null $value = null, bool $ignoreUser = false,  bool $isConnected = true, User|Admin|null $user = null): AndroidSession|null
     {
          return AndroidSession::when($isConnected, 
                                   fn(Builder $q): Builder =>
                                        $q->connected())
                                   ->when(!$ignoreUser, 
                                        fn(Builder $q): Builder =>
                                             $q->when( $user, fn(Builder $q): Builder => 
                                                  $q->where("user_id", $user->id),
                                                  fn(Builder $q): Builder => 
                                                       $q->whereNull("user_id")))
                                  
                                   ->where($column, $value)
                                   ->with(['user'])
                                   ->first();
     }

     /**
      * getSpecificGateway
      *
      * @param ChannelTypeEnum $channel
      * @param string|null|null $type
      * @param string|null $column
      * @param string|int|null $value
      * @param User|null $user
      * 
      * @return Gateway|null
      */
     public function getSpecificGateway(ChannelTypeEnum $channel, string|null $type = null, string|null $column = "id", string|int|null $value = null, User|Admin|null $user = null): ?Gateway
     {
          return Gateway::when($user instanceof User, fn(Builder $q): Builder =>
                              $q->where("user_id", $user->id),
                                   fn(Builder $q): Builder =>
                                        $q->whereNull("user_id"))
                              ->when($type, fn(Builder $q): Builder =>
                                   $q->where("type", $type))
                              ->where("channel", $channel)
                              ->where($column, $value)
                              ->first();
     }

     /**
      * getAndroidSims
      *
      * @param string $token
      * @param bool $loadPaginated
      * @param int|null $id
      * @param User|null $user
      * 
      * @return Collection
      */
     public function getAndroidSims(string $token, bool $loadPaginated = false, ?int $id = null, User|Admin|null $user = null): Collection|LengthAwarePaginator
     {
          return AndroidSim::select([
               'id',
               'android_session_id',
               'sim_number',
               'status',
               'per_message_delay',
               'delay_after_count',
               'delay_after_duration',
               'reset_after_count',
               'created_at',
               'updated_at',
          ])->filter(["status"])
               ->date()
               ->search(["sim_number"])
               ->when($id, fn(Builder $q): Builder =>
                    $q->where("id", $id))
               ->wherehas('androidSession', fn(Builder $q): Builder =>
                    $q->where("token", $token))
               ->when($user instanceof User, function ($query) use ($user) {
                    $query->where(function ($q) use ($user) {
                         $q->where('user_id', $user->id)
                         ->orWhereNull('user_id');
                    });
               })->with(["androidSession:id,name,token,status"])
               ->when(!$loadPaginated, fn(Builder $q): Collection =>
                    $q->get(), fn(Builder $q): LengthAwarePaginator =>
                    $q->paginate(site_settings('paginate_number', 10))
                    ->appends(request()->all()));
     }

     /**
      * getAndroidSim
      *
      * @param int $id
      * @param AndroidSession $androidSession
      * @param User|null $user
      * @param bool $userSpecificGateways
      * 
      * @return AndroidSim|null
      */
     public function getAndroidSim(int $id, bool $userSpecificGateways = false, User|Admin|null $user = null, ?AndroidSession $androidSession = null): ?AndroidSim
     {
          return AndroidSim::when($androidSession, 
                                        fn(Builder $q): Builder =>
                                             $q->where('android_session_id', $androidSession->id))
                                   ->where("id", $id)
                                   ->when($user instanceof User && $userSpecificGateways,
                                        fn(Builder $q): Builder =>
                                             $q->where("user_id", $user->id))
                                   ->with(["androidSession"])
                                   ->first();
     }

     /**
      * getAndroidSimForDispatch
      *
      * @param string $gatewayId
      * @param User|Admin|null $user
      *
      * @return [type]
      */
     public function getAndroidSimForDispatch(string|null $gatewayId = null, User|Admin|null $user = null, bool $userSpecificGateways = false)
     {
          $query = AndroidSim::where('status', 'active')
                                   ->where('send_sms', true)
                                   ->whereHas('androidSession', function ($query) {
                                        $query->connected();
                                   })
                                   ->when($user instanceof User && $userSpecificGateways,
                                        fn(Builder $q): Builder =>
                                             $q->where("user_id", $user->id));
                                   
          if ($gatewayId === '-1') {

               $sim = $query->first();
               if (!$sim) {
                    throw new ApplicationException('No active Android SIM with a connected session found for automatic assignment.');
               }
               return $sim;
          } elseif ($gatewayId === '0') {

               $sims = $query->get();
               if ($sims->isEmpty()) {
                    throw new ApplicationException('No active Android SIMs with a connected session found for random assignment.');
               }
               return $sims;
          } else {

               $sim = $this->getAndroidSim(id: (int) $gatewayId, userSpecificGateways: $userSpecificGateways, user: $user);
               
               if (!$sim || $sim->status != Status::ACTIVE || !$sim->androidSession || $sim->androidSession->status != SessionStatusEnum::CONNECTED) {
                    throw new ApplicationException('The specified Android SIM is not active or does not have a connected session.');
               }
               return $sim;
          }
     }

     /**
      * getGatewayForDispatch
      *
      * @param ChannelTypeEnum $channel
      * @param string|null|null $gatewayId
      * @param string|null|null $method
      * @param User|null $user
      * 
      * @return AndroidSim
      */
     public function getGatewayForDispatch(ChannelTypeEnum $channel, bool $adminSpecificGateways = false, bool $userSpecificGateways = false, string|null $gatewayId = null, string|null $method = null, User|Admin|null $user = null):AndroidSim|Gateway|ApplicationException|EloquentCollection
     {
          if ($channel == ChannelTypeEnum::SMS && $method == 'android') 
               return $this->getAndroidSimForDispatch($gatewayId, $user, $userSpecificGateways);
               
          $query = Gateway::where('channel', $channel->value)
                              ->where('status', 'active')
                              ->when($channel == ChannelTypeEnum::WHATSAPP,
                                   fn(Builder $q): Builder =>
                                        $q->when(request()->input("cloud_api") == "true", 
                                        fn(Builder $q): Builder =>
                                             $q->where("type", WhatsAppGatewayTypeEnum::CLOUD->value),
                                                  fn(builder $q): Builder =>
                                                       $q->where("type", WhatsAppGatewayTypeEnum::NODE->value)))
                              ->when($user instanceof User && !$adminSpecificGateways,
                                   fn(Builder $q): Builder =>
                                        $q->where("user_id", $user->id),
                                             fn(Builder $q): Builder =>
                                                  $q->whereNull("user_id"))
                              ->orderBy("is_default", "DESC");
                              
          if ($gatewayId == '-1') {
               $gateway = $query->first();
               
               $method = textFormat(["_"], $method, " ");
               if (!$gateway) throw new ApplicationException("No active gateway found for {$channel->value} channel with type {$method} for automatic assignment.", Response::HTTP_NOT_FOUND);
               
               return $gateway;
          } elseif ($gatewayId == '0') {

               $gateways = $query->get();
               if ($gateways->isEmpty()) throw new ApplicationException("No active gateways found for {$channel->value} channel with method {$method} for random assignment.", Response::HTTP_NOT_FOUND);
               return $gateways;
          } elseif($gatewayId) {

               $gateway = $query->where('id', $gatewayId)->first();
               
               if (!$gateway) throw new ApplicationException("The specified gateway for {$channel->value} channel is not active or does not exist.", Response::HTTP_NOT_FOUND);
               return $gateway;
          } else {

               $gateway = $query->where('is_default')->first();
               if(!$gateway) throw new ApplicationException("No active gateway found for {$channel->value} channel", Response::HTTP_NOT_FOUND);
               return $gateway;
          }
     }

     /**
      * storeAndroidSim
      *
      * @param array $data
      * 
      * @return AndroidSim
      */
     public function storeAndroidSim(array $data): AndroidSim
     {
          return AndroidSim::updateOrCreate([
               "user_id"      => Arr::get($data, "user_id"),
               "sim_number"   => Arr::get($data, "sim_number")
          ], $data);
     }

     /**
      * storeDispatchGateway
      *
      * @param ChannelTypeEnum $type
      * @param Request $request
      * @param User|null $user
      * 
      * @return Gateway
      */
     public function storeDispatchGateway(ChannelTypeEnum $type, Request $request, User|Admin|null $user = null): Gateway {

          $data = $request->input("custom_gateway_parameter");
          if($user) {

               $planAccess = (object) planAccess($user);
               $existingGatewayCount = Gateway::where("channel", $type)
                                                  ->where("user_id", $user->id)
                                                  ->count();
               
              
               if(Arr::get($planAccess->{$type->value}, "gateway_limit", "-1") != "-1" && Arr::get($planAccess->{$type->value}, "gateway_limit", "-1") < $existingGatewayCount)
               throw new ApplicationException("You have already reached maximum gateway limit according to your plan", Response::HTTP_NOT_FOUND);

               $data = Arr::set($data, "user_id", $user->id);
          }
          return $this->createOrUpdateGateway($data);
     }

     /**
      * createOrUpdateGateway
      *
      * @param array $data
      * @param int|string|null|null $id
      * 
      * @return Gateway
      */
     public function createOrUpdateGateway(array $data, int|string|null $id = null): Gateway
     {
          return Gateway::updateOrCreate(['id' => $id],$data);
     }

     /**
      * updateAndroidSim
      *
      * @param AndroidSim $sim
      * @param array $data
      * 
      * @return bool
      */
     public function updateAndroidSim(AndroidSim $sim, array $data): bool
     {
          return $sim->update($data);
     }

     /**
      * deleteAndroidSim
      *
      * @param AndroidSim $sim
      * 
      * @return bool
      */
     public function deleteAndroidSim(AndroidSim $sim): bool
     {
          return $sim->delete();
     }
}