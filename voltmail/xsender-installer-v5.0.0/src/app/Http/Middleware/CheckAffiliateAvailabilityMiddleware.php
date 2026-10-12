<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAffiliateAvailabilityMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if(site_settings("affiliate_system", \App\Enums\StatusEnum::FALSE->status()) == \App\Enums\StatusEnum::FALSE->status()) {
            return redirect(route("home"));
        }
        return $next($request);
    }
}
