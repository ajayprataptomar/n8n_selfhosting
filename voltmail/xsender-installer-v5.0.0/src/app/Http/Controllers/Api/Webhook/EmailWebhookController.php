<?php

namespace App\Http\Controllers\Api\Webhook;

use App\Http\Controllers\Controller;
use App\Services\System\Communication\BounceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailWebhookController extends Controller
{
    protected BounceService $bounceService;

    public function __construct()
    {
        $this->bounceService = new BounceService();
    }

    /**
     * Handle SendGrid event webhook.
     * Events: bounce, dropped, deferred, spam_report, unsubscribe
     */
    public function sendgrid(Request $request): JsonResponse
    {
        $events = $request->all();
        if (!is_array($events)) {
            return response()->json(['status' => 'error'], 400);
        }

        foreach ($events as $event) {
            if (!is_array($event)) continue;

            $eventType = $event['event'] ?? '';
            $email = $event['email'] ?? '';
            if (!$email) continue;

            match ($eventType) {
                'bounce' => $this->bounceService->processBounce(
                    $email, 'hard', 'sendgrid',
                    $event['status'] ?? null,
                    $event['reason'] ?? null
                ),
                'dropped' => $this->bounceService->processBounce(
                    $email, 'hard', 'sendgrid',
                    null,
                    $event['reason'] ?? null
                ),
                'deferred' => $this->bounceService->processBounce(
                    $email, 'soft', 'sendgrid',
                    $event['status'] ?? null,
                    $event['reason'] ?? null
                ),
                'spamreport' => $this->bounceService->processBounce(
                    $email, 'complaint', 'sendgrid'
                ),
                default => null,
            };
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle AWS SES notification (via SNS).
     * Notification types: Bounce, Complaint, Delivery
     */
    public function ses(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Handle SNS subscription confirmation
        if (($payload['Type'] ?? '') === 'SubscriptionConfirmation') {
            $subscribeUrl = $payload['SubscribeURL'] ?? null;
            if ($subscribeUrl) {
                file_get_contents($subscribeUrl);
            }
            return response()->json(['status' => 'subscribed']);
        }

        // Parse SNS notification
        $message = json_decode($payload['Message'] ?? '{}', true);
        if (!is_array($message)) {
            return response()->json(['status' => 'error'], 400);
        }

        $notificationType = $message['notificationType'] ?? '';

        if ($notificationType === 'Bounce') {
            $bounce = $message['bounce'] ?? [];
            $bounceType = ($bounce['bounceType'] ?? '') === 'Permanent' ? 'hard' : 'soft';

            foreach ($bounce['bouncedRecipients'] ?? [] as $recipient) {
                $this->bounceService->processBounce(
                    $recipient['emailAddress'] ?? '',
                    $bounceType,
                    'aws',
                    $recipient['diagnosticCode'] ?? null,
                    $recipient['status'] ?? null
                );
            }
        } elseif ($notificationType === 'Complaint') {
            $complaint = $message['complaint'] ?? [];
            foreach ($complaint['complainedRecipients'] ?? [] as $recipient) {
                $this->bounceService->processBounce(
                    $recipient['emailAddress'] ?? '',
                    'complaint',
                    'aws',
                    $complaint['complaintFeedbackType'] ?? null
                );
            }
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Mailgun event webhook.
     * Events: bounced, dropped, complained
     */
    public function mailgun(Request $request): JsonResponse
    {
        $eventData = $request->input('event-data', []);
        if (empty($eventData)) {
            return response()->json(['status' => 'error'], 400);
        }

        $event = $eventData['event'] ?? '';
        $email = $eventData['recipient'] ?? '';
        if (!$email) {
            return response()->json(['status' => 'no email'], 400);
        }

        $deliveryStatus = $eventData['delivery-status'] ?? [];

        match ($event) {
            'failed' => $this->bounceService->processBounce(
                $email,
                ($eventData['severity'] ?? '') === 'permanent' ? 'hard' : 'soft',
                'mailgun',
                $deliveryStatus['code'] ?? null,
                $deliveryStatus['message'] ?? $deliveryStatus['description'] ?? null
            ),
            'complained' => $this->bounceService->processBounce(
                $email, 'complaint', 'mailgun'
            ),
            default => null,
        };

        return response()->json(['status' => 'ok']);
    }

    /**
     * Handle Mailjet event webhook.
     * Events: bounce, spam, blocked
     */
    public function mailjet(Request $request): JsonResponse
    {
        $events = $request->all();
        if (!is_array($events)) {
            return response()->json(['status' => 'error'], 400);
        }

        foreach ($events as $event) {
            if (!is_array($event)) continue;

            $eventType = $event['event'] ?? '';
            $email = $event['email'] ?? '';
            if (!$email) continue;

            match ($eventType) {
                'bounce' => $this->bounceService->processBounce(
                    $email,
                    ($event['hard_bounce'] ?? false) ? 'hard' : 'soft',
                    'mailjet',
                    $event['error_related_to'] ?? null,
                    $event['error'] ?? null
                ),
                'spam' => $this->bounceService->processBounce(
                    $email, 'complaint', 'mailjet'
                ),
                'blocked' => $this->bounceService->processBounce(
                    $email, 'soft', 'mailjet',
                    $event['error_related_to'] ?? null,
                    $event['error'] ?? null
                ),
                default => null,
            };
        }

        return response()->json(['status' => 'ok']);
    }
}
