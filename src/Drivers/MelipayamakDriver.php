<?php

namespace Alizeinodin\SmsApi\Drivers;

class MelipayamakDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'melipayamak');
    }
}
