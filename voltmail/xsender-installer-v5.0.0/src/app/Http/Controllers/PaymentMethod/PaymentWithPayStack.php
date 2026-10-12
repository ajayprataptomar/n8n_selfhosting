<?php

namespace App\Http\Controllers\PaymentMethod;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Models\PaymentLog;
use App\Http\Controllers\PaymentMethod\PaymentController;
use Illuminate\Support\Facades\Log;

class PaymentWithPayStack extends Controller
{
    /**
     * Tolerance (in major units of the currency) allowed between the amount Paystack
     * reports and the amount XSender expected. Payment gateways occasionally round
     * sub-cent fractions; treat anything within this band as equal.
     */
    private const AMOUNT_TOLERANCE = 0.01;

    public function store(Request $request)
    {
        $paymentMethod = PaymentMethod::where('unique_code','PAYSTACK103')->first();
        if(!$paymentMethod){
            $notify[] = ['error', 'Invalid Payment gateway'];
            return back()->withNotify($notify);
        }
        $paymentTrackNumber = session()->get('payment_track');
        $paymentLog = PaymentLog::where('trx_number', $paymentTrackNumber)->first();

        if (!$paymentLog) {
            Log::warning('Paystack: missing PaymentLog for current session', [
                'session_payment_track' => $paymentTrackNumber,
                'paystack_reference'    => $request->reference,
            ]);
            $notify[] = ['error', 'No payment record found for this session. Please start the purchase again.'];
            return back()->withNotify($notify);
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.paystack.co/transaction/verify/".$request->reference,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".$paymentMethod->payment_parameter->secret_key,
                "Cache-Control: no-cache",
            ),
        ));
        $response = curl_exec($curl);
        $err      = curl_error($curl);
        curl_close($curl);

        if (!$response) {
            Log::warning('Paystack: empty response from verify endpoint', [
                'paystack_reference' => $request->reference,
                'curl_error'         => $err,
                'trx_number'         => $paymentLog->trx_number,
            ]);
            $notify[] = ['error', 'Could not reach Paystack to verify the payment. Please try again.'];
            return back()->withNotify($notify);
        }

        $data = json_decode($response, true);
        if (!$data) {
            Log::warning('Paystack: non-JSON response from verify endpoint', [
                'paystack_reference' => $request->reference,
                'raw_response'       => substr((string) $response, 0, 500),
            ]);
            $notify[] = ['error', 'Unexpected response from Paystack. Please try again.'];
            return back()->withNotify($notify);
        }

        $paystackData = $data['data'] ?? null;
        if (!$paystackData || ($paystackData['status'] ?? null) !== 'success') {
            $notify[] = ['error', $data['message'] ?? 'Paystack reports the payment was not successful.'];
            return back()->withNotify($notify);
        }

        // Paystack reports amount in minor units (kobo / pesewas / cents).
        $amount       = ($paystackData['amount'] ?? 0) / 100;
        $final_amount = round($paymentLog->final_amount, 2);

        // Currency comparison: case-insensitive + trim. Paystack always returns uppercase
        // ISO 4217 codes; admins sometimes save the local code in lowercase.
        $paystackCurrency = strtoupper(trim((string) ($paystackData['currency'] ?? '')));
        $configCurrency   = strtoupper(trim((string) $paymentMethod->currency_code));

        $amountMatches   = abs($amount - $final_amount) <= self::AMOUNT_TOLERANCE;
        $currencyMatches = $paystackCurrency === $configCurrency;
        $statusIsPending = (int) $paymentLog->status === 0;

        if ($amountMatches && $currencyMatches && $statusIsPending) {
            PaymentController::paymentUpdate($paymentLog->trx_number);
            return response()->json(['message' => 'Payment successfully done'], 200);
        }

        // The payment cleared on Paystack but XSender refused to activate the subscription.
        // Log every value so the admin can reconcile without having to read DB rows by hand.
        Log::warning('Paystack: payment verified as paid but subscription was NOT activated', [
            'paystack_reference' => $request->reference,
            'trx_number'         => $paymentLog->trx_number,
            'user_id'            => $paymentLog->user_id,
            'paystack_amount'    => $amount,
            'expected_amount'    => $final_amount,
            'amount_matches'     => $amountMatches,
            'paystack_currency'  => $paystackCurrency,
            'expected_currency'  => $configCurrency,
            'currency_matches'   => $currencyMatches,
            'payment_log_status' => $paymentLog->status,
            'status_is_pending'  => $statusIsPending,
        ]);

        if (!$currencyMatches) {
            $notify[] = ['error', "Currency mismatch — Paystack settled in {$paystackCurrency}, gateway configured for {$configCurrency}. Update the gateway's Currency field to match your Paystack settlement currency."];
        } elseif (!$amountMatches) {
            $notify[] = ['error', "Amount mismatch — Paystack reported {$amount} {$paystackCurrency}, expected {$final_amount} {$configCurrency}."];
        } elseif (!$statusIsPending) {
            $notify[] = ['error', 'This payment record was already processed. If the subscription is still not active, contact support with reference ' . $request->reference . '.'];
        } else {
            $notify[] = ['error', 'Could not activate the subscription. Reference: ' . $request->reference];
        }

        return back()->withNotify($notify);
    }
}
