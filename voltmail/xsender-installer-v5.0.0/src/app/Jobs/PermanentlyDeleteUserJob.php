<?php

namespace App\Jobs;

use App\Models\User;
use App\Service\Admin\Core\FileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermanentlyDeleteUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $user;
    public $tries = 5;
    public $timeout = 600;
    public $backoff = [10, 30, 60];

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function handle()
    {
        $user = User::withTrashed()->find($this->user->id);
        if (!$user) {
            // Log::info("User not found for deletion: ID {$this->user->id}");
            return;
        }

        $directRelations = [
            'androidSession', 'androidSims', 'campaigns', 'campaignUnsubscribers',
            'contacts', 'contactGroups', 'creditLogs', 'dispatchDelays', 'dispatchLogs',
            'gateways', 'imports', 'messages', 'paymentLogs', 'webhookLogs',
            'subscriptions', 'supportTickets', 'templates', 'transactions'
        ];

        $nestedRelations = [
            'contactGroups' => ['contactImports'],
        ];

        $batchSize = 2000;
        $deleted = $user->total_deleted_entries ?? 0;

        collect($directRelations)->each(function ($relation) use ($user, $batchSize, &$deleted, $nestedRelations) {
            try {
                if (!method_exists($user, $relation)) {
                    // Log::warning("Relation {$relation} not defined for user ID {$user->id}. Skipping.");
                    return;
                }

                $currentBatchSize = $relation === 'contacts' ? $batchSize : 1000;

                if (empty($nestedRelations[$relation]) && $user->$relation()->exists()) {
                    while ($user->$relation()->exists()) {
                        $count = $user->$relation()->limit($currentBatchSize)->delete();
                        $deleted += $count;
                        $user->total_deleted_entries = $deleted;
                        $user->save();
                        // Log::info("Bulk deleted {$count} entries from {$relation} for user ID {$user->id}. Total deleted: {$deleted}");

                        if (memory_get_usage() > 500000000) {
                            // Log::warning("Memory limit approaching for user ID {$user->id} in {$relation}. Releasing job.");
                            $this->release(10);
                            return false;
                        }
                    }
                }
                elseif (!empty($nestedRelations[$relation])) {
                    $user->$relation()->chunk($currentBatchSize, function ($items) use ($relation, &$deleted, $user, $nestedRelations) {
                        DB::transaction(function () use ($items, $relation, &$deleted, $user, $nestedRelations) {
                            foreach ($items as $item) {

                                foreach ($nestedRelations[$relation] as $nestedRelation) {
                                    if (method_exists($item, $nestedRelation)) {
                                        $nestedCount = $item->$nestedRelation()->delete();
                                        $deleted += $nestedCount;
                                        // Log::info("Deleted {$nestedCount} entries from {$relation}.{$nestedRelation} for user ID {$user->id}.");
                                    } else {
                                        // Log::warning("Nested relation {$nestedRelation} not defined for {$relation} on user ID {$user->id}. Skipping.");
                                    }
                                }
                                $item->delete();
                                $deleted++;
                            }
                        });

                        $user->total_deleted_entries = $deleted;
                        $user->save();
                        // Log::info("Deleted {$items->count()} entries from {$relation} for user ID {$user->id}. Total deleted: {$deleted}");

                        if (memory_get_usage() > 500000000) {
                            // Log::warning("Memory limit approaching for user ID {$user->id} in {$relation}. Releasing job.");
                            $this->release(10);
                            return false;
                        }
                    });
                }
            } catch (\Throwable $e) {
                // Log::error("Error deleting {$relation} for user ID {$user->id}: {$e->getMessage()}");
            }
        });

        try {
            if ($user->image) {
                $fileService = new FileService();
                $fileService->unlinkFile($user->image, filePath()['profile']['user']['path']);
            }
        } catch (\Exception $e) {
            // Log::error("Error deleting user image for ID {$user->id}: {$e->getMessage()}");
        }

        $user->is_erasing = false;
        $user->save();
        $user->forceDelete();

        // Log::info("User ID {$user->id} permanently deleted with {$deleted} related entries.");
    }
}