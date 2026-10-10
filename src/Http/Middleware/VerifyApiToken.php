<?php

namespace Alizeinodin\SmsApi\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('smsapi.api.enabled', false)) {
            return response()->json(['message' => 'API disabled.'], 404);
        }

        if (! (bool) config('smsapi.api.require_token', true)) {
            return $next($request);
        }

        $token = (string) config('smsapi.api.token', '');

        if ($token === '') {
            return response()->json(['message' => 'API token is not configured.'], 503);
        }

        $provided = (string) (
            $request->header('X-SMSAPI-Token')
            ?: $request->header('Authorization')
            ?: $request->query('token', '')
        );

        if (str_starts_with($provided, 'Bearer ')) {
            $provided = trim(substr($provided, 7));
        }

        if (! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
