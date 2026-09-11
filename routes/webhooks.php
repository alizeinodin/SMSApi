<?php

use Alizeinodin\SmsApi\Http\Controllers\DeliveryWebhookController;
use Alizeinodin\SmsApi\Http\Middleware\VerifyWebhookToken;
use Illuminate\Support\Facades\Route;

Route::middleware(array_merge(
    (array) config('smsapi.webhook.middleware', ['api']),
    [VerifyWebhookToken::class],
))
    ->post(
        config('smsapi.webhook.path', 'smsapi/webhooks/delivery').'/{driver?}',
        DeliveryWebhookController::class
    )
    ->name('smsapi.webhooks.delivery');
