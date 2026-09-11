<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * Payamak Novin / نوین پیامک — Payamak Panel API.
 */
class PayamaknovinDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'payamaknovin';
    }
}
