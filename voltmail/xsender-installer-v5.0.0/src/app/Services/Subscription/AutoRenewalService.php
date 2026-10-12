<?php

namespace App\Services\Subscription;

use App\Models\User;
use App\Models\PaymentLog;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

/**
 * AutoRenewalService — Handles automatic subscription renewals
 *
 * Supports two renewal methods:
 * 1. Wallet balance — deducts from user's wallet_balance
 * 2. Payment gateway — charges saved card via Stripe/PayPal/etc.
 *
 * Flow:
 * - 3 days before expiry: Send renewal reminder
 * - 1 day before expiry: Attempt auto-renewal
 * - On expiry day: Retry if first attempt failed
 * - 1 day after expiry (grace): Final retry, then disable auto-renewal
 */
class AutoRenewalService
{
    protected array $results = [
        'renewed'    => 0,
        'failed'     => 0,
        'reminders'  => 0,
        'skipped'    => 0,
    ];

    /**
     * Main entry point — called by scheduler
     */
    public function processRenewals(): array
    {
        $this->results = ['renewed' => 0, 'failed' => 0, 'reminders' => 0, 'skipped' => 0];

        // Step 1: Send reminders (3 days before expiry)
        $this->sendRenewalReminders();

        // Step 2: Process auto-renewals (expiring within 24 hours or already expired within grace period)
        $this->processAutoRenewals();

        Log::info('[AutoRenewal] Process complete', $this->results);

        return $this->results;
    }

    /**
     * Send renewal reminders 3 days before expiry
     */
    protected function sendRenewalReminders(): void
    {
        $subscriptions = Subscription::with(['user', 'plan'])
            ->where('auto_renewal', true)
            ->whereIn('status', [Subscription::RUNNING, Subscription::RENEWED])
            ->whereNull('renewal_reminder_sent_at')
            ->where('expired_date', '<=', now()->addDays(3))
            ->where('expired_date', '>', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                $user = $subscription->user;
                $plan = $subscription->plan;

                if (!$user || !$plan) continue;

                // Send notification (email or in-app)
                $this->sendRenewalNotification($user, $subscription, 'reminder');

                $subscription->update(['renewal_reminder_sent_at' => now()]);
                $this->results['reminders']++;

            } catch (\Throwable $e) {
                Log::warning('[AutoRenewal] Reminder failed', [
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Process auto-renewals for expiring/expired subscriptions
     */
    protected function processAutoRenewals(): void
    {
        // Get subscriptions that need renewal:
        // - Auto-renewal enabled
        // - Currently running or renewed
        // - Expires within 24 hours OR expired within 1 day (grace period)
        // - Max 3 retry attempts
        $subscriptions = Subscription::with(['user', 'plan'])
            ->where('auto_renewal', true)
            ->whereIn('status', [Subscription::RUNNING, Subscription::RENEWED, Subscription::EXPIRED])
            ->where('expired_date', '<=', now()->addDay())
            ->where('expired_date', '>=', now()->subDay()) // 1-day grace period
            ->where('renewal_attempts', '<', 3)
            ->get();

        foreach ($subscriptions as $subscription) {
            $this->attemptRenewal($subscription);
        }
    }

    /**
     * Attempt to renew a single subscription
     */
    protected function attemptRenewal(Subscription $subscription): bool
    {
        $user = $subscription->user;
        $plan = $subscription->plan;

        if (!$user || !$plan) {
            $this->results['skipped']++;
            return false;
        }

        $method = $subscription->renewal_method ?? $user->auto_renewal_preference ?? 'wallet';
        $amount = (float) $plan->amount;

        Log::info('[AutoRenewal] Attempting renewal', [
            'subscription_id' => $subscription->id,
            'user_id'         => $user->id,
            'method'          => $method,
            'amount'          => $amount,
            'attempt'         => $subscription->renewal_attempts + 1,
        ]);

        $subscription->increment('renewal_attempts');
        $subscription->update(['last_renewal_attempt' => now()]);

        try {
            $success = match ($method) {
                'wallet'  => $this->renewViaWallet($subscription, $user, $plan, $amount),
                'gateway' => $this->renewViaGateway($subscription, $user, $plan, $amount),
                default   => false,
            };

            if ($success) {
                $this->results['renewed']++;
                $this->sendRenewalNotification($user, $subscription, 'success');
                return true;
            }

            $this->results['failed']++;

            // Disable auto-renewal after max attempts
            if ($subscription->renewal_attempts >= 3) {
                $subscription->update(['auto_renewal' => false]);
                $this->sendRenewalNotification($user, $subscription, 'failed_final');
                Log::warning('[AutoRenewal] Max attempts reached, disabling', [
                    'subscription_id' => $subscription->id,
                ]);
            } else {
                $this->sendRenewalNotification($user, $subscription, 'failed_retry');
            }

            return false;

        } catch (\Throwable $e) {
            $this->results['failed']++;
            Log::error('[AutoRenewal] Renewal exception', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Renew via wallet balance
     */
    protected function renewViaWallet(Subscription $subscription, User $user, $plan, float $amount): bool
    {
        if ($user->wallet_balance < $amount) {
            Log::info('[AutoRenewal] Insufficient wallet balance', [
                'user_id' => $user->id,
                'balance' => $user->wallet_balance,
                'required' => $amount,
            ]);
            return false;
        }

        return DB::transaction(function () use ($subscription, $user, $plan, $amount) {
            // Deduct from wallet
            $user->decrement('wallet_balance', $amount);

            // Create payment log
            $paymentLog = PaymentLog::create([
                'subscriptions_id' => $subscription->id,
                'user_id'          => $user->id,
                'method_id'        => null,
                'amount'           => $amount,
                'charge'           => 0,
                'rate'             => 1,
                'final_amount'     => $amount,
                'trx_number'       => 'WAL' . strtoupper(uniqid()),
                'feedback'         => 'Auto-renewal via wallet',
                'status'           => PaymentLog::SUCCESS,
            ]);

            // Activate subscription
            $this->activateRenewal($subscription, $user, $plan, $paymentLog->trx_number);

            return true;
        });
    }

    /**
     * Renew via payment gateway (Stripe, etc.)
     */
    protected function renewViaGateway(Subscription $subscription, User $user, $plan, float $amount): bool
    {
        $gatewayId = $subscription->renewal_gateway_id;
        $customerToken = $subscription->gateway_customer_token;
        $paymentMethodToken = $subscription->gateway_payment_method_token;

        if (!$gatewayId || !$customerToken) {
            Log::warning('[AutoRenewal] No gateway credentials stored', [
                'subscription_id' => $subscription->id,
            ]);
            return false;
        }

        $gateway = PaymentMethod::find($gatewayId);
        if (!$gateway) return false;

        // Route to appropriate gateway handler
        $chargeResult = $this->chargeGateway($gateway, $customerToken, $paymentMethodToken, $amount, $user);

        if (!$chargeResult['success']) {
            Log::warning('[AutoRenewal] Gateway charge failed', [
                'gateway' => $gateway->unique_code,
                'error' => $chargeResult['error'] ?? 'Unknown',
            ]);
            return false;
        }

        return DB::transaction(function () use ($subscription, $user, $plan, $amount, $gateway, $chargeResult) {
            $paymentLog = PaymentLog::create([
                'subscriptions_id' => $subscription->id,
                'user_id'          => $user->id,
                'method_id'        => $gateway->id,
                'amount'           => $amount,
                'charge'           => $gateway->percent_charge * $amount / 100,
                'rate'             => $gateway->rate ?? 1,
                'final_amount'     => $amount,
                'trx_number'       => $chargeResult['transaction_id'] ?? 'GW' . strtoupper(uniqid()),
                'feedback'         => 'Auto-renewal via ' . $gateway->name,
                'status'           => PaymentLog::SUCCESS,
            ]);

            $this->activateRenewal($subscription, $user, $plan, $paymentLog->trx_number);

            return true;
        });
    }

    /**
     * Charge a payment gateway using stored token
     */
    protected function chargeGateway(PaymentMethod $gateway, string $customerToken, ?string $paymentMethodToken, float $amount, User $user): array
    {
        $code = $gateway->unique_code;
        $params = json_decode($gateway->payment_parameter, true) ?? [];

        // Stripe
        if (str_starts_with($code, 'STRIPE')) {
            return $this->chargeStripe($params, $customerToken, $paymentMethodToken, $amount, $gateway->currency_code);
        }

        // PayPal (recurring agreement)
        if (str_starts_with($code, 'PAYPAL')) {
            return $this->chargePaypal($params, $customerToken, $amount, $gateway->currency_code);
        }

        // Razorpay
        if (str_starts_with($code, 'RAZOR')) {
            return $this->chargeRazorpay($params, $customerToken, $paymentMethodToken, $amount, $gateway->currency_code);
        }

        return ['success' => false, 'error' => "Auto-renewal not supported for gateway: {$code}"];
    }

    /**
     * Charge Stripe using saved payment method
     */
    protected function chargeStripe(array $params, string $customerId, ?string $paymentMethodId, float $amount, string $currency): array
    {
        try {
            $secretKey = $params['secret_key'] ?? '';
            if (!$secretKey) return ['success' => false, 'error' => 'Stripe secret key not configured'];

            $response = Http::withBasicAuth($secretKey, '')
                ->asForm()
                ->post('https://api.stripe.com/v1/payment_intents', [
                    'amount'         => (int) ($amount * 100), // cents
                    'currency'       => strtolower($currency),
                    'customer'       => $customerId,
                    'payment_method' => $paymentMethodId,
                    'off_session'    => 'true',
                    'confirm'        => 'true',
                    'description'    => 'Subscription auto-renewal',
                ]);

            $data = $response->json();

            if ($response->successful() && ($data['status'] ?? '') === 'succeeded') {
                return ['success' => true, 'transaction_id' => $data['id']];
            }

            return ['success' => false, 'error' => $data['error']['message'] ?? 'Stripe charge failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Charge PayPal using billing agreement
     */
    protected function chargePaypal(array $params, string $agreementId, float $amount, string $currency): array
    {
        // PayPal billing agreements auto-charge — we just need to verify status
        // For now, return not supported until PayPal recurring is fully wired
        return ['success' => false, 'error' => 'PayPal auto-renewal requires billing agreement setup'];
    }

    /**
     * Charge Razorpay using saved token
     */
    protected function chargeRazorpay(array $params, string $customerId, ?string $tokenId, float $amount, string $currency): array
    {
        try {
            $keyId = $params['razorpay_key'] ?? '';
            $keySecret = $params['razorpay_secret'] ?? '';

            if (!$keyId || !$keySecret) return ['success' => false, 'error' => 'Razorpay credentials not configured'];

            $response = Http::withBasicAuth($keyId, $keySecret)
                ->post('https://api.razorpay.com/v1/payments/create/recurring', [
                    'email'       => '',
                    'contact'     => '',
                    'amount'      => (int) ($amount * 100), // paise
                    'currency'    => strtoupper($currency),
                    'customer_id' => $customerId,
                    'token'       => $tokenId,
                    'description' => 'Subscription auto-renewal',
                ]);

            $data = $response->json();

            if ($response->successful() && in_array($data['status'] ?? '', ['captured', 'authorized'])) {
                return ['success' => true, 'transaction_id' => $data['id'] ?? ''];
            }

            return ['success' => false, 'error' => $data['error']['description'] ?? 'Razorpay charge failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Activate the renewed subscription
     */
    protected function activateRenewal(Subscription $subscription, User $user, $plan, string $trxNumber): void
    {
        $newExpiry = Carbon::parse($subscription->expired_date)->addDays((int) $plan->duration);

        // If already expired, calculate from now
        if (Carbon::parse($subscription->expired_date)->isPast()) {
            $newExpiry = now()->addDays((int) $plan->duration);
        }

        $subscription->update([
            'status'            => Subscription::RENEWED,
            'expired_date'      => $newExpiry,
            'trx_number'        => $trxNumber,
            'amount'            => $plan->amount,
            'renewal_attempts'  => 0,
            'last_renewal_attempt' => now(),
            'renewal_reminder_sent_at' => null,
        ]);

        // Allocate credits based on carry_forward setting
        $carryForward = (int) ($plan->carry_forward ?? 0);

        if ($carryForward) {
            $user->increment('sms_credit', (int) optional($plan->sms)->credits);
            $user->increment('email_credit', (int) optional($plan->email)->credits);
            $user->increment('whatsapp_credit', (int) optional($plan->whatsapp)->credits);
        } else {
            $user->update([
                'sms_credit'      => (int) optional($plan->sms)->credits,
                'email_credit'    => (int) optional($plan->email)->credits,
                'whatsapp_credit' => (int) optional($plan->whatsapp)->credits,
            ]);
        }

        // Create transaction record
        Transaction::create([
            'user_id'            => $user->id,
            'amount'             => $plan->amount,
            'trx_type'           => '+',
            'transaction_type'   => 'subscription_renewal',
            'details'            => "Auto-renewal: {$plan->name} plan",
            'transaction_number' => $trxNumber,
        ]);

        Log::info('[AutoRenewal] Subscription renewed successfully', [
            'subscription_id' => $subscription->id,
            'user_id'         => $user->id,
            'new_expiry'      => $newExpiry->toDateTimeString(),
        ]);
    }

    /**
     * Send renewal notification to user using existing template system
     */
    protected function sendRenewalNotification(User $user, Subscription $subscription, string $type): void
    {
        try {
            $slugMap = [
                'reminder'     => \App\Enums\DefaultTemplateSlug::RENEWAL_REMINDER,
                'success'      => \App\Enums\DefaultTemplateSlug::RENEWAL_SUCCESS,
                'failed_retry' => \App\Enums\DefaultTemplateSlug::RENEWAL_FAILED,
                'failed_final' => \App\Enums\DefaultTemplateSlug::RENEWAL_FAILED,
            ];

            $slug = $slugMap[$type] ?? null;
            if (!$slug) return;

            $plan = $subscription->plan;

            // Build mail code replacements
            $mailCode = [
                'name'        => $user->name ?? $user->email ?? 'User',
                'plan_name'   => $plan?->name ?? 'N/A',
                'amount'      => shortAmount($plan?->amount ?? 0),
                'expiry_date' => Carbon::parse($subscription->expired_date)->format('M d, Y'),
                'wallet_balance' => shortAmount($user->wallet_balance ?? 0),
            ];

            // Use existing SendMail utility
            $sendMail = new \App\Http\Utility\SendMail();
            $gateway = \App\Models\Gateway::where('channel', \App\Enums\System\ChannelTypeEnum::EMAIL)
                ->where('status', \App\Enums\Common\Status::ACTIVE->value)
                ->whereNull('user_id')
                ->first();

            $template = \App\Models\Template::where('slug', $slug->value)
                ->where('channel', \App\Enums\System\ChannelTypeEnum::EMAIL)
                ->where('default', true)
                ->where('status', \App\Enums\Common\Status::ACTIVE->value)
                ->whereNull('user_id')
                ->first();

            if ($gateway && $template) {
                $sendMail->MailNotification($gateway, $template, $user, $mailCode);
            }

            Log::info("[AutoRenewal] Notification sent: {$type}", [
                'user_id' => $user->id,
            ]);

        } catch (\Throwable $e) {
            Log::warning('[AutoRenewal] Notification failed', ['error' => $e->getMessage()]);
        }
    }

    public function getResults(): array
    {
        return $this->results;
    }
}
