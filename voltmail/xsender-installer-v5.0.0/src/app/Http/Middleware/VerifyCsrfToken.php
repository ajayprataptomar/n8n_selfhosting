<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Closure;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */

    protected $addHttpCookie = true;

    protected $except = [
        '/user/success','/user/cancel','/user/fail','/user/ipn','user/ipn/strip','user/ipn/paypal','user/ipn/paystack','user/ipn/razorpay','webhook',
        // Webhook endpoints for WhatsApp Node service
        'api/whatsapp/*',
        'webhook/*',
        // Automation endpoints (called by cron)
        'automation/*',
        'cron/*',
        'queue/*',
        // RFC 8058 One-Click POST — receiving mail servers can't provide a CSRF token.
        'unsubscribe',
    ];

    /**
     * Handle an incoming request.
     * Provides better error handling for CSRF token mismatches
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try {
            return parent::handle($request, $next);
        } catch (\Illuminate\Session\TokenMismatchException $e) {
            // Log the 419 for debugging (only in debug mode)
            if (config('app.debug')) {
                \Log::debug('CSRF Token Mismatch', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            // For AJAX requests, return JSON with new token
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'error' => 'csrf_token_expired',
                    'message' => translate('Your session has expired. Please refresh the page.'),
                    'new_token' => csrf_token()
                ], 419);
            }

            // For installer routes, regenerate token and redirect back
            if ($request->is('install/*')) {
                $request->session()->regenerateToken();
                return redirect()->back()
                    ->withInput($request->except('_token', 'password', 'db_password'))
                    ->with('error', translate('Session expired. Please try again.'));
            }

            // For login pages - regenerate token and redirect with message
            if ($request->is('admin/login') || $request->is('login') || $request->is('admin') || $request->is('user')) {
                $request->session()->regenerate();
                $request->session()->regenerateToken();

                $notify[] = ['error', translate('Session expired. Please try again.')];
                return redirect()->back()
                    ->withInput($request->except('_token', 'password'))
                    ->withNotify($notify);
            }

            // For any other page - regenerate and redirect
            $request->session()->regenerateToken();
            $notify[] = ['error', translate('Your session has expired. Please try again.')];
            return redirect()->back()
                ->withInput($request->except('_token', 'password'))
                ->withNotify($notify);
        }
    }
}
