<?php

namespace App\Console\Commands;

use App\Jobs\SendWarmupEmailJob;
use App\Services\Warmup\WarmupOutboxDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class WarmupDispatchCommand extends Command
{
    protected $signature = 'warmup:dispatch
                            {--batch=20 : Maximum slots to claim and process per run}
                            {--queue : Push claimed slots to warmup-send queue instead of inline execution}
                            {--dry-run : Inspect eligible due slots and report what would be processed without claiming or sending}';

    protected $description = 'Claim due warmup slots and execute outbound SMTP transmission';

    public function handle(WarmupOutboxDispatcher $dispatcher): int
    {
        $batch = (int) $this->option('batch');
        $useQueue = (bool) $this->option('queue');
        $isDryRun = (bool) $this->option('dry-run');

        if ($isDryRun) {
            $dueSlots = $dispatcher->getDueSlotsPreview($batch);

            if ($dueSlots->isEmpty()) {
                $this->line('[DRY RUN] No warmup slots currently due for dispatch.');
                return 0;
            }

            $this->info("[DRY RUN] Found {$dueSlots->count()} due warmup slot(s) eligible for dispatch.");

            $rows = [];
            foreach ($dueSlots as $dispatch) {
                $preview = $dispatcher->previewSlot($dispatch);
                $rows[] = [
                    'Slot #'     => $preview['slot_number'],
                    'ID'         => $preview['slot_id'],
                    'Recipient'  => $preview['recipient_email'],
                    'Gateway'    => ($preview['gateway_name'] ?: 'N/A') . ' (' . ($preview['gateway_address'] ?: 'N/A') . ')',
                    'Active'     => $preview['gateway_active'] ? 'YES' : 'NO',
                    'Template'   => $preview['template_id'],
                    'Subject'    => Str::limit($preview['subject'], 35),
                    'Scheduled'  => $preview['scheduled_at'] ?: 'Immediate',
                ];
            }

            $this->table(['Slot #', 'ID', 'Recipient', 'Gateway', 'Active', 'Template', 'Subject', 'Scheduled'], $rows);
            $this->line('[DRY RUN] Read-only inspection complete. Zero database updates, queue jobs, or emails generated.');
            return 0;
        }

        $claimed = $dispatcher->claimDueSlots($batch);

        if ($claimed->isEmpty()) {
            $this->line('No warmup slots currently due for dispatch.');
            return 0;
        }

        $this->info("Claimed {$claimed->count()} due warmup slots for processing.");

        $successCount = 0;
        $failedCount  = 0;

        foreach ($claimed as $dispatch) {
            if ($useQueue) {
                SendWarmupEmailJob::dispatch($dispatch->id);
                $this->line("Queued slot #{$dispatch->slot_number} [ID: {$dispatch->id}] onto warmup-send");
                $successCount++;
            } else {
                $outcome = $dispatcher->dispatchSlot($dispatch);
                if ($outcome['success'] ?? false) {
                    $this->info("Dispatched slot #{$dispatch->slot_number} to {$dispatch->recipient_email} [{$outcome['status']}]");
                    $successCount++;
                } else {
                    $this->error("Failed slot #{$dispatch->slot_number} to {$dispatch->recipient_email} [{$outcome['status']}]: " . ($outcome['error'] ?? ''));
                    $failedCount++;
                }
            }
        }

        $this->info("Processed: {$successCount} succeeded, {$failedCount} failed/ambiguous.");
        return 0;
    }
}
