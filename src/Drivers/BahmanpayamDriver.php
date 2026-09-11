<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * Bahman Payam / بهمن پیام — Payamak Panel API.
 */
class BahmanpayamDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'bahmanpayam';
    }
}
