<?php

namespace App\Traits;

use App\Enums\ServiceType;
use Carbon\Carbon;
use App\Models\Gateway;
use App\Models\DispatchLog;
use Illuminate\Support\Arr;
use App\Enums\System\ChannelTypeEnum;
use App\Service\Admin\Core\CustomerService;
use App\Enums\System\CommunicationStatusEnum;
use App\Models\User;
use App\Services\System\Communication\DispatchService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;

trait Dispatchable
{
     public DispatchService $dispatchService;
 
	public function __construct() {
	    $this->dispatchService = new DispatchService();
	}

	/**
	 * getCredentials
	 *
	 * @param ChannelTypeEnum $channel
	 * @param string $provider
	 * @param Gateway $gateway
	 * 
	 * @return array
	 */
	public function getCredentials(ChannelTypeEnum $channel, string $provider, Gateway $gateway): array|null {

          $data = $gateway->meta_data;
          
          $requiredCreds = Arr::get(config("setting.gateway_credentials.{$channel->value}." . strtolower($provider), []), "meta_data");
          
          
          foreach (array_keys($requiredCreds) as $key) {
              if (!Arr::has($data, $key)) {
                  return null;
              }
          }
          return Arr::only($data, array_keys($requiredCreds)); 
     }
  
     /**
      * markAsDelivered
      *
      * @param DispatchLog|Collection $dispatchLog
      * 
      * @return void
      */
     public function markAsDelivered(DispatchLog|Collection $dispatchLog): void
     {
          if ($dispatchLog instanceof Collection) {

               DispatchLog::whereIn('id', $dispatchLog->pluck('id')->all())
                    ->update([
                         "processed_at" => Carbon::now(),
                         "status" => CommunicationStatusEnum::DELIVERED,
                    ]);
          } else {
               
               $dispatchLog->update([
                    "processed_at" => Carbon::now(),
                    "status" => CommunicationStatusEnum::DELIVERED,
               ]);
          }
     }

     /**
      * addedCreditsForLog
      *
      * @param DispatchLog $log
      * @param string $message
      * 
      * @return void
      */
     public function addedCreditsForLog(DispatchLog $log, string $message): void
     {
          $user = User::find($log->user_id);
          
          if ($user) {
               $channel = $log->type->value;
               $columnName = $channel."_credit";
               $creditCount = 1;
               CustomerService::addedCreditLog(
                    $user,
                    $creditCount,
                    constant(ServiceType::class . '::' . strtoupper($channel))->value,
                    false,
                    translate("Added {$creditCount} credit due to failed {$channel} dispatch: {$message}")
               );
          }
     }

     
     /**
      * fail
      *
      * @param DispatchLog|Collection $dispatchLog
      * @param string $message
      * 
      * @return void
      */
     public function fail(DispatchLog|Collection $dispatchLog, string $message): void
     {
          if ($dispatchLog instanceof Collection) {
               $logIds = $dispatchLog->pluck('id')->all();
               DispatchLog::whereIn('id', $logIds)
                    ->update([
                         'status' => CommunicationStatusEnum::FAIL,
                         'processed_at' => Carbon::now(),
                         'response_message' => $message,
                    ]);

               LazyCollection::make($dispatchLog)
                                   ->each(function ($log) use ($message) {
                                        $this->addedCreditsForLog($log, $message);
                                   });
          } else {

               $this->addedCreditsForLog($dispatchLog, $message);
               $dispatchLog->update([
                    'status' => CommunicationStatusEnum::FAIL,
                    'processed_at' => Carbon::now(),
                    'response_message' => $message,
               ]);
          }
     }
}
