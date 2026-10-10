<?php

namespace Alizeinodin\SmsApi\Drivers;

class PayamitoDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'payamito');
    }
}
