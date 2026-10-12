<?php

namespace App\Http\Middleware;

use App\Enums\StatusEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class DomainVerificationMiddleware
{
    /**
     * Cache key for domain verification status
     */
    private const CACHE_KEY = 'domain_verification_status';

    /**
     * Handle an incoming request.
     *
     * SIMPLIFIED: Only checks stored verification status.
     * NO continuous re-verification - that's done during:
     * 1. Installation
     * 2. Updates
     * 3. Manual license check by admin
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for localhost/development
        $domain = $request->getHost();
        if ($this->isDevelopment($domain)) {
            return $next($request);
        }

        // Check cache first - fastest path (no DB query)
        $cachedStatus = Cache::get(self::CACHE_KEY);
        if ($cachedStatus === 'verified') {
            return $next($request);
        }

        // Cache miss - check database once, then cache result
        // site_settings() may return null if DB is temporarily unavailable
        $is_domain_verified = site_settings('is_domain_verified');

        if ($is_domain_verified == StatusEnum::TRUE->status()) {
            // Cache for 7 days - no continuous re-verification needed
            Cache::put(self::CACHE_KEY, 'verified', now()->addDays(7));
            return $next($request);
        }

        // Only redirect if EXPLICITLY set to FALSE in the database
        // null/missing means either:
        //   - Fresh install (installation will set it)
        //   - DB temporarily unavailable (site_settings returned default)
        //   - Cache was just cleared (will rebuild on next request)
        // In all these cases, allow through — don't block legitimate users
        if ($is_domain_verified !== null && $is_domain_verified === StatusEnum::FALSE->status()) {
            return redirect()->route('domain.unverified')
                ->with('error', 'Please verify your license.');
        }

        return $next($request);
    }

    /**
     * Check if running in development environment
     */
    private function isDevelopment(string $domain): bool
    {
        return str_contains($domain, 'localhost')
            || str_contains($domain, '127.0.0.1')
            || str_contains($domain, '.test')
            || str_contains($domain, '.local');
    }
}
