<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Subscription;
use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncomingApiMiddleware
{
    /**
     * @param Request $request
     * @param Closure $next
     * @return JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check API key from multiple sources (priority order):
        // 1. Header: Api-key
        // 2. Query parameter: api_key
        // 3. POST body: api_key
        $apiKey = $request->header('Api-key')
            ?? $request->query('api_key')
            ?? $request->input('api_key')
            ?? null;

        if(is_null($apiKey)){
            return response()->json([
                'status' => 'error',
                'message' => 'API key is required. Provide via header (Api-key) or URL parameter (api_key)',
                'error' => 'Invalid Api Key'
            ],403);
        }
        $user = User::where('api_key', $apiKey)->first();
        $admin = Admin::where('api_key', $apiKey)->first();

        if($user){
            $subscription = Subscription::where('user_id',$user->id)
                                            ->where('status', SubscriptionStatus::RUNNING->value)
                                            ->exists();
            if(!$subscription){
                return response()->json([
                    'status' => 'error',
                    'error' => 'Your Subscription Is Expired! Buy A New Plan'
                ],403);
            }
        }

        if($user || $admin){
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'error' => 'Invalid Api Key'
        ],403);
    }
}
