<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * Amoot / آموت — Payamak Panel compatible SMS API.
 */
class AmootDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'amoot';
    }
}
