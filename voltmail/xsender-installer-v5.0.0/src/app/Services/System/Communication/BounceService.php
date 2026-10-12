<?php

namespace App\Services\System\Communication;

use App\Models\BounceLog;
use App\Models\ContactEngagement;
use App\Models\DispatchLog;
use App\Models\EmailSuppression;
use Illuminate\Support\Facades\Log;

class BounceService
{
    /**
     * Process a bounce event from a webhook.
     */
    public function processBounce(
        string $email,
        string $bounceType,
        ?string $provider = null,
        ?string $bounceCode = null,
        ?string $bounceMessage = null,
        ?int $dispatchLogId = null,
        ?int $userId = null
    ): BounceLog {
        // Find the dispatch log if we have a message ID or email
        if (!$dispatchLogId && $email) {
            $dispatchLog = DispatchLog::whereHas('contact', function ($q) use ($email) {
                $q->where('email_contact', $email);
            })->where('status', 'delivered')
              ->latest('sent_at')
              ->first();

            $dispatchLogId = $dispatchLog?->id;
            $userId = $userId ?? $dispatchLog?->user_id;
        }

        $bounceLog = BounceLog::create([
            'user_id' => $userId,
            'dispatch_log_id' => $dispatchLogId,
            'email_address' => strtolower($email),
            'bounce_type' => $bounceType,
            'bounce_code' => $bounceCode,
            'bounce_message' => $bounceMessage,
            'provider' => $provider,
            'processed' => false,
        ]);

        $this->autoSuppress($bounceLog);

        return $bounceLog;
    }

    /**
     * Auto-suppress based on bounce rules.
     * Hard bounces and complaints: suppress immediately.
     * Soft bounces: suppress after threshold.
     */
    private function autoSuppress(BounceLog $bounceLog): void
    {
        if (!site_settings('bounce_auto_suppress')) {
            return;
        }

        $email = $bounceLog->email_address;
        $userId = $bounceLog->user_id;

        if ($bounceLog->bounce_type === 'hard') {
            EmailSuppression::suppress($email, 'hard_bounce', 'webhook', $userId);
            $this->updateEngagement($bounceLog, 'bounced');
            $bounceLog->update(['processed' => true, 'processed_at' => now()]);
            return;
        }

        if ($bounceLog->bounce_type === 'complaint') {
            EmailSuppression::suppress($email, 'complaint', 'webhook', $userId);
            $bounceLog->update(['processed' => true, 'processed_at' => now()]);
            return;
        }

        // Soft bounce — check threshold
        $threshold = (int) site_settings('bounce_soft_threshold', 3);
        $softBounceCount = BounceLog::where('email_address', $email)
            ->where('bounce_type', 'soft')
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->count();

        if ($softBounceCount >= $threshold) {
            EmailSuppression::suppress($email, 'hard_bounce', 'webhook', $userId);
            $this->updateEngagement($bounceLog, 'bounced');
            $bounceLog->update(['processed' => true, 'processed_at' => now()]);
        }
    }

    /**
     * Update contact engagement on bounce.
     */
    private function updateEngagement(BounceLog $bounceLog, string $type): void
    {
        if (!$bounceLog->dispatch_log_id) {
            return;
        }

        $dispatchLog = $bounceLog->dispatchLog;
        if (!$dispatchLog || !$dispatchLog->contact_id) {
            return;
        }

        $engagement = ContactEngagement::where('contact_id', $dispatchLog->contact_id)
            ->where('channel', 'email')
            ->first();

        if ($engagement) {
            $engagement->increment('total_bounced');
        }
    }
}
