<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * @see https://payamito.com/webservice/
 */
class PayamitoDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'payamito';
    }
}
