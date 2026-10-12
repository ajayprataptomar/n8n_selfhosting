<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\Gateway;
use App\Services\System\Communication\GatewayService;
use Illuminate\Support\Arr;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use App\Enums\System\ChannelTypeEnum;
use App\Jobs\ProcessDispatchLogBatch;
use App\Services\System\Communication\DispatchService;
use Exception;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\LazyCollection;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class InsertDispatchLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $logs;
    protected $channel;
    protected $pipe;
    protected $user;
    protected $apiLogCount;

    /**
     * Create a new job instance.
     *
     * @param array $logs
     * @param ChannelTypeEnum $channel
     * @param string $pipe
     * @param ?User $user
     * @param ?int $apiLogCount
     * @return void
     */
    public function __construct(array $logs, ChannelTypeEnum $channel, string $pipe, ?User $user = null, ?int $apiLogCount = null)
    {
        $this->logs         = $logs;
        $this->channel      = $channel;
        $this->pipe         = $pipe;
        $this->user         = $user;
        $this->apiLogCount  = $apiLogCount;
        $this->onQueue('dispatch-logs');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            DB::transaction(function () {

                DB::table('dispatch_logs')->insert($this->logs);
                $insertedLogs = $this->retrieveInsertedLogs($this->logs);
                $this->queueGatewayLogs($insertedLogs);
            });
        } catch (Exception $e) {
            
            Log::error('Error inserting dispatch logs: ' . $e->getMessage());
        }
    }
    
    /**
     * Retrieve the inserted logs with their IDs
     *
     * @param array $logs
     * @return array
     */
    protected function retrieveInsertedLogs(array $logs)
    {
        $identifiers = [];
        foreach ($logs as $log) {
            $key = sprintf(
                "%s_%s_%s_%s",
                $log['message_id'] ?? '',
                $log['contact_id'] ?? '',
                $log['type'] ?? '',
                $log['created_at'] ?? ''
            );
            $identifiers[$key] = $log;
        }
        
        $results = DB::table('dispatch_logs')
            ->whereIn('message_id', array_unique(array_map(fn($log) => $log['message_id'], $logs)))
            ->whereIn('contact_id', array_unique(array_map(fn($log) => $log['contact_id'], $logs)))
            ->where('type', $this->channel->value)
            ->orderBy('id', 'desc')
            ->take(count($logs) * 2)
            ->get();
            
        $insertedLogs = [];
        foreach ($results as $result) {
            $key = sprintf(
                "%s_%s_%s_%s",
                $result->message_id,
                $result->contact_id,
                $result->type,
                $result->created_at
            );
            
            if (isset($identifiers[$key])) {
                $insertedLogs[] = (array)$result;
                unset($identifiers[$key]); 
            }
        }
        
        return $insertedLogs;
    }

    /**
     * Queue the gateway logs for processing
     *
     * @param array $insertedLogs
     * @return void
     */
    protected function queueGatewayLogs(array $insertedLogs)
    {
        $gatewayLogs = array_filter($insertedLogs, fn($log) => 
            Arr::get($log, 'gatewayable_type') === Gateway::class);
            
        if (empty($gatewayLogs)) return;

        $gatewayService     = new GatewayService();
        $dispatchService    = new DispatchService();

        $batches       = [];
        $batchSizes    = config("queue.batch_sizes.{$this->pipe}.{$this->channel->value}");
        $queue         = config("queue.pipes.{$this->pipe}.{$this->channel->value}");
        $minBatchSize  = Arr::get($batchSizes, "min");
        $maxBatchSize  = Arr::get($batchSizes, "max");
        $gatewayConfig = config("setting.gateway_credentials.{$this->channel->value}");
        $logCounter    = 0;
        
        collect($gatewayLogs)
                            ->groupBy(function ($log) {
                                
                                return implode('|', [
                                    Arr::get($log, 'gatewayable_id'),
                                    Arr::get($log, 'user_id'),
                                    $this->channel->value,
                                    Arr::get($log, 'campaign_id', 'none'),
                                    Arr::get($log, 'id', 'none'),
                                    Arr::get($log, 'scheduled_at', 'none'),
                                ]);
                            })->map(function ($logs, $groupKey) use (&$batches, $gatewayService, $dispatchService, $maxBatchSize, $minBatchSize, $gatewayConfig, &$logCounter) {

                                [$gatewayId, $userId, $channelValue, $campaignId, $dispatchId, $scheduledAt] = explode('|', $groupKey);
                                $gatewayId          = $gatewayId ?: null;
                                $userId             = $userId ?: null;
                                $dispatchId         = $dispatchId !== 'none' ? $dispatchId : null;
                                $messagesToSend     = $logs->count();
                                $dispatchType       = $campaignId ? 'campaign' : 'regular';
                                $delay              = $gatewayService->calculateDispatchDelay($gatewayId, $this->channel, $messagesToSend, $campaignId ? "campaign" : "regular", $userId);

                                $gateway            = Gateway::where('id', $gatewayId)->select(['bulk_contact_limit', 'type'])->first();
                                $typeConfig         = $gateway ? Arr::get($gatewayConfig, $gateway->type, []) : [];
                                $nativeBulkSupport  = Arr::get($typeConfig, 'meta_data', false);
                                $bulkLimit          = $gateway ? ($gateway->bulk_contact_limit ?? 1) : 1;
                                $logCount           = $logs->count();

                                if($scheduledAt) return;
                                
                                if ($nativeBulkSupport && $bulkLimit > 1 && ($this->apiLogCount > 1 || $logCount > 1)) {
                                    collect($logs)
                                            ->groupBy('dispatch_unit_id')
                                            ->map(function ($unitLogs) use ($dispatchService, $gatewayId, $dispatchId, $dispatchType, $userId, &$batches, $maxBatchSize, $delay, &$logCounter) {

                                                return $unitLogs->chunk($maxBatchSize)
                                                                    ->filter(fn($chunk) => count($chunk) >= 1)
                                                                    ->map(function ($chunk) use ($dispatchService, $gatewayId, $dispatchId, $dispatchType, $userId, &$batches, $delay, &$logCounter) {
                                                                        $logCounter++;
                                                                        $unitIds = $chunk->keys()->all();
                                                                        $delay = $delay * $logCounter;
                                                                        $dispatchService->storeDispatchDelay($gatewayId, $this->channel, $dispatchId, $dispatchType, $delay, $userId);
                                                                        $job = ProcessDispatchLogBatch::dispatch($unitIds, $this->channel, $this->pipe, true)->delay(now()->addSeconds($delay));
                                                                                                                                                                
                                                                        $batches[] = $job;
                                                                    });
                                            })->all();
                                } else {
                                    
                                    $logs->chunk($maxBatchSize)
                                            ->filter(fn($chunk) => count($chunk) >= $minBatchSize)
                                            ->map(function ($chunk) use ($gatewayId, $dispatchService, $dispatchId, $dispatchType, $userId, &$batches, $delay, &$logCounter) {
                                                $logCounter++;
                                                $ids = collect($chunk)->pluck('id')->toArray();
                                                $delay    = $delay * $logCounter;
                                                $dispatchService->storeDispatchDelay($gatewayId, $this->channel, $dispatchId, $dispatchType, $delay, $userId);
                                                $job = ProcessDispatchLogBatch::dispatch($ids, $this->channel, $this->pipe, false)
                                                                                    ->delay(now()->addSeconds($delay));
                                                
                                                $batches[] = $job;
                                            })->all();
                                }
                            });
        // if (!empty($batches)) {
        //     Bus::batch($batches)
        //         ->allowFailures()
        //         ->onQueue($queue)
        //         ->dispatch();
        // }
    }
}