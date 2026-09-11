<?php

namespace Alizeinodin\SmsApi\Drivers;

class MelipayamakDriver extends PayamakPanelDriver
{
    protected function defaultProviderName(): string
    {
        return 'melipayamak';
    }
}
