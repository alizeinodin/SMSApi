<?php

use Alizeinodin\SmsApi\Http\Controllers\SendMessageController;
use Alizeinodin\SmsApi\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;

$routeMiddleware = (array) config('smsapi.api.route_middleware', []);
$throttle = config('smsapi.api.throttle', '60,1');

if (is_string($throttle) && $throttle !== '') {
    $routeMiddleware[] = 'throttle:'.$throttle;
}

Route::middleware(array_merge(
    (array) config('smsapi.api.middleware', ['api']),
    [VerifyApiToken::class],
))
    ->prefix(trim((string) config('smsapi.api.prefix', 'smsapi'), '/'))
    ->group(function () use ($routeMiddleware): void {
        Route::post('messages', SendMessageController::class)
            ->middleware($routeMiddleware)
            ->name('smsapi.messages.send');
    });
