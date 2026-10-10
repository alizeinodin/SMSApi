<?php

use Alizeinodin\SmsApi\Http\Controllers\SendMessageController;
use Alizeinodin\SmsApi\Http\Middleware\VerifyApiToken;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(
    (array) config('smsapi.api.middleware', ['api']),
    [VerifyApiToken::class],
))
    ->prefix(trim((string) config('smsapi.api.prefix', 'smsapi'), '/'))
    ->group(function (): void {
        Route::post('messages', SendMessageController::class)
            ->middleware((array) config('smsapi.api.route_middleware', []))
            ->name('smsapi.messages.send');
    });
