<?php

namespace App\Traits;

use App\Http\Utility\Api\ApiJsonResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for API controllers.
 *
 * - resolveApiUser(): Returns the API-authenticated user without hard-depending
 *   on Passport. AndroidGatewayToken middleware sets the user on the request
 *   attributes / user resolver; falling back to Auth::guard('api') is best-effort
 *   and swallows a Passport failure so a release-stripped install (missing
 *   storage/oauth-*.key) does not surface as a 500.
 * - jsonException(): Uniform error envelope for uncaught exceptions. Logs the
 *   trace under a short reference code (`ref`) and echoes that ref back in the
 *   response so the customer can grep laravel.log to find it in one line.
 */
trait ApiExceptionResponder
{
    protected function resolveApiUser(): ?User
    {
        $req = request();

        $user = $req->user();
        if ($user instanceof User) {
            return $user;
        }

        $session = $req->attributes->get('android_session');
        if ($session && isset($session->user) && $session->user instanceof User) {
            return $session->user;
        }

        try {
            $passport = Auth::guard('api')->user();
            return $passport instanceof User ? $passport : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function jsonException(\Throwable $e, string $where, int $status = Response::HTTP_INTERNAL_SERVER_ERROR): JsonResponse
    {
        $ref = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        Log::error("[api:{$where}] [{$ref}] " . $e->getMessage(), [
            'file'  => $e->getFile(),
            'line'  => $e->getLine(),
            'trace' => collect($e->getTrace())->take(6)->all(),
        ]);

        return ApiJsonResponse::error(
            getEnvironmentMessage($e->getMessage()) . ' (ref: ' . $ref . ')',
            ['error_ref' => $ref],
            $status
        );
    }
}
