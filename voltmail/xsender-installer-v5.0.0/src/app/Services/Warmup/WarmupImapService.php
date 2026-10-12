<?php

namespace App\Services\Warmup;

use App\Models\WarmupDispatch;
use App\Models\WarmupSchedule;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WarmupImapService
{
    protected WarmupMailboxService $mailboxService;
    protected ?ImapClient $injectedClient;

    public function __construct(
        ?WarmupMailboxService $mailboxService = null,
        ?ImapClient $client = null
    ) {
        $this->mailboxService = $mailboxService ?: new WarmupMailboxService();
        $this->injectedClient = $client;
    }

    /**
     * Run IMAP verification across all active schedules that have IMAP configured
     */
    public function verifyActiveSchedules(): array
    {
        $schedules = WarmupSchedule::with('gateway')
            ->where('status', 'active')
            ->get();

        $results = [];
        foreach ($schedules as $schedule) {
            if (!$schedule->gateway || !$this->mailboxService->hasImapConfig($schedule->gateway)) {
                continue;
            }

            try {
                $results[$schedule->id] = $this->verifyDispatchesForSchedule($schedule);
            } catch (Exception $e) {
                Log::warning("Warmup IMAP verification failed for schedule #{$schedule->id}: " . $e->getMessage());
                $results[$schedule->id] = ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Verify pending dispatches for a specific schedule
     */
    public function verifyDispatchesForSchedule(WarmupSchedule $schedule, ?ImapClient $clientOverride = null): array
    {
        $gateway = $schedule->gateway;
        $imapConfig = $this->mailboxService->getImapConfig($gateway);

        if (!$imapConfig) {
            return ['status' => 'skipped_no_imap', 'verified' => 0];
        }

        $pendingDispatches = WarmupDispatch::where('warmup_schedule_id', $schedule->id)
            ->whereIn('status', [WarmupDispatch::STATUS_SENT, WarmupDispatch::STATUS_DELIVERED])
            ->whereNull('verified_at')
            ->whereNotNull('message_id')
            ->latest('sent_at')
            ->limit(30)
            ->get();

        if ($pendingDispatches->isEmpty()) {
            return ['status' => 'no_pending_dispatches', 'verified' => 0];
        }

        // Initialize client
        $client = $clientOverride ?: $this->injectedClient ?: new ImapClient(
            host: $imapConfig['host'],
            port: $imapConfig['port'],
            encryption: $imapConfig['encryption']
        );

        $client->connect();
        $client->login($imapConfig['username'], $imapConfig['password']);

        $verifiedInbox = 0;
        $verifiedSpam  = 0;
        $markedUnknown = 0;

        try {
            // 1. Check INBOX
            $inboxMeta = $client->selectFolder('INBOX');
            $this->validateAndPersistWatermark($schedule, 'INBOX', $inboxMeta['uidvalidity'] ?? null);

            foreach ($pendingDispatches as $dispatch) {
                $uids = $client->searchMessageId($dispatch->message_id);
                if (!empty($uids)) {
                    $dispatch->status          = WarmupDispatch::STATUS_DELIVERED;
                    $dispatch->inbox_placement = WarmupDispatch::PLACEMENT_INBOX;
                    $dispatch->verified_at     = Carbon::now();
                    $dispatch->save();
                    $verifiedInbox++;
                }
            }

            // 2. For remaining unverified messages, check Spam folders
            $unverified = $pendingDispatches->filter(fn($d) => $d->verified_at === null);
            if ($unverified->isNotEmpty()) {
                $spamFolders = ['[Gmail]/Spam', 'Spam', 'Junk'];
                foreach ($spamFolders as $folder) {
                    try {
                        $client->selectFolder($folder);
                        foreach ($unverified as $dispatch) {
                            if ($dispatch->verified_at !== null) continue;

                            $uids = $client->searchMessageId($dispatch->message_id);
                            if (!empty($uids)) {
                                $dispatch->status          = WarmupDispatch::STATUS_DELIVERED;
                                $dispatch->inbox_placement = WarmupDispatch::PLACEMENT_SPAM;
                                $dispatch->verified_at     = Carbon::now();
                                $dispatch->save();
                                $verifiedSpam++;
                            }
                        }
                    } catch (\Exception $e) {
                        // Folder may not exist on this IMAP server, continue to next
                        continue;
                    }
                }
            }

            // 3. Messages sent more than 24 hours ago that were never observed in Inbox or Spam
            $staleCutoff = Carbon::now()->subHours(24);
            foreach ($pendingDispatches as $dispatch) {
                if ($dispatch->verified_at === null && $dispatch->sent_at && $dispatch->sent_at < $staleCutoff) {
                    // Prompt Section 5: If placement cannot be reliably determined, report as unknown rather than inventing a status
                    $dispatch->inbox_placement = WarmupDispatch::PLACEMENT_UNKNOWN;
                    $dispatch->verified_at     = Carbon::now();
                    $dispatch->save();
                    $markedUnknown++;
                }
            }

        } finally {
            $client->disconnect();
        }

        return [
            'status'         => 'success',
            'verified_inbox' => $verifiedInbox,
            'verified_spam'  => $verifiedSpam,
            'marked_unknown' => $markedUnknown,
            'total_checked'  => $pendingDispatches->count(),
        ];
    }

    /**
     * Validate and record folder UIDVALIDITY watermark.
     * If UIDVALIDITY has changed, resets watermark safely.
     */
    public function validateAndPersistWatermark(WarmupSchedule $schedule, string $folder, ?int $serverUidValidity): bool
    {
        if (!$serverUidValidity) {
            return false;
        }

        $watermark = $schedule->imap_watermark ?? [];
        $storedValidity = Arr::get($watermark, "{$folder}.uidvalidity");

        if ($storedValidity !== null && (int) $storedValidity !== $serverUidValidity) {
            // UIDVALIDITY changed: mailbox was recreated or moved.
            Log::warning("IMAP UIDVALIDITY changed on schedule #{$schedule->id} folder {$folder}: was {$storedValidity}, now {$serverUidValidity}. Resetting watermark.");
            $watermark[$folder] = [
                'uidvalidity' => $serverUidValidity,
                'last_uid'    => 0,
                'reset_at'    => Carbon::now()->toISOString(),
            ];
            $schedule->imap_watermark = $watermark;
            $schedule->save();
            return false; // Watermark was reset
        }

        $watermark[$folder] = [
            'uidvalidity' => $serverUidValidity,
            'updated_at'  => Carbon::now()->toISOString(),
        ];
        $schedule->imap_watermark = $watermark;
        $schedule->save();

        return true;
    }
}
