<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\Core\DemoService;
use Illuminate\Http\Request;

class RestrictDemoMode
{
    protected $demoService;

    public function __construct(DemoService $demoService)
    {
        $this->demoService = $demoService;
    }

    /**
     * Handles incoming requests, applying demo mode restrictions.
     *
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            if (config('demo.enabled') && $this->demoService->isMethodRestricted($request)) {
                $method = strtoupper($request->method());
                $message = $this->demoService->getMethodMessage($method);
                return $this->demoService->getRestrictedResponse($request, $message);
            }
        } catch (\Exception $e) {
            return $this->demoService->getRestrictedResponse($request, 'An error occurred.', 'error');
        }

        return $next($request);
    }
}