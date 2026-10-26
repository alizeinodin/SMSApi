<?php

namespace Alizeinodin\SmsApi\Http\Middleware;

use Alizeinodin\SmsApi\Support\ConfigBoolean;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebhookToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! (bool) config('smsapi.webhook.enabled', false)) {
            return response()->json(['message' => 'Webhook disabled.'], 404);
        }

        $token = (string) config('smsapi.webhook.token', '');

        if ($token === '') {
            if (ConfigBoolean::get(config('smsapi.webhook.require_token', true), true)) {
                return response()->json(['message' => 'Webhook token is not configured.'], 503);
            }

            return $next($request);
        }

        $provided = (string) (
            $request->header('X-SMSAPI-Webhook-Token')
            ?: $request->header('Authorization')
            ?: ''
        );

        if ($provided === '' && ConfigBoolean::get(config('smsapi.webhook.allow_query_token', false))) {
            $provided = (string) $request->query('token', '');
        }

        if (str_starts_with($provided, 'Bearer ')) {
            $provided = trim(substr($provided, 7));
        }

        if (! hash_equals($token, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return $next($request);
    }
}
