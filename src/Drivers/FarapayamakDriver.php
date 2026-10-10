<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * @see https://docs.farapayamak.ir/
 */
class FarapayamakDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'farapayamak';
    }
}
