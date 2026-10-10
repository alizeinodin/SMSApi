<?php

namespace Alizeinodin\SmsApi\Drivers;

class AmootDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'amoot');
    }
}
