<?php

namespace App\Http\Middleware;

use App\Enums\SettingKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class QueueAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token          = $request->query('token');
        $expectedToken  = site_settings(SettingKey::QUEUE_TOKEN->value, config('app.queue_token', env('QUEUE_TOKEN', '')));

        if (!$token || $token !== $expectedToken) {
            return response()->json([
                'error' => 'Unauthorized: Invalid or missing token',
            ], 401);
        }

        return $next($request);
    }
}