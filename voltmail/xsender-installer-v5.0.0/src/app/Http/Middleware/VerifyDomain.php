<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for localhost/development environments
        $domain = $request->getHost();
        if (str_contains($domain, 'localhost') || str_contains($domain, '127.0.0.1')
            || str_contains($domain, '.test') || str_contains($domain, '.local')) {
            return $next($request);
        }

        if (!is_domain_verified()) {
            return redirect()->route('domain.unverified')->with('error', 'Domain verification failed.');
        }

        return $next($request);
    }
}
