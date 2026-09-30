<?php

namespace Alizeinodin\SmsApi\Drivers;

class PayamaknovinDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'payamaknovin');
    }
}
