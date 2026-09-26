<?php

namespace Alizeinodin\SmsApi\Drivers;

class BahmanpayamDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'bahmanpayam');
    }
}
