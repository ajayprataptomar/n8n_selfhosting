<?php

namespace App\Http\Middleware;

use App\Http\Utility\Api\ApiJsonResponse;
use App\Managers\GatewayManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AndroidGatewayToken
{
    protected $gatewayManager;

    public function __construct()
    {
        $this->gatewayManager   = new GatewayManager();
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token) {
            return ApiJsonResponse::error(
                'Bearer token is required',
                null,
                Response::HTTP_UNAUTHORIZED
            );
        }
        if($token) {
            $androidSession = $this->gatewayManager->getAndroidSession(column: "token", value: $token, ignoreUser: true);
            if(!$androidSession)
                return ApiJsonResponse::error(
                    'Session is not authorized',
                    null,
                    Response::HTTP_UNAUTHORIZED
                );
            // Bind the session's owner onto the request so controllers can
            // reach it without touching Auth::guard('api'). Passport is not
            // needed for Android bearer-token auth — installs that stripped
            // storage/oauth-*.key (typical release ZIPs) would otherwise 500
            // as soon as anything calls Auth::guard('api')->user().
            if ($androidSession->user) {
                $request->setUserResolver(fn () => $androidSession->user);
                try {
                    Auth::guard('api')->setUser($androidSession->user);
                } catch (\Throwable $e) {
                    // Passport not bootable (missing keys / package) — safe to ignore,
                    // the request-resolver above already exposes the user.
                }
            }
            $request->attributes->set('android_session', $androidSession);
        }
        
        

        return $next($request);
    }
}