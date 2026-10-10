<?php

namespace Alizeinodin\SmsApi\Drivers;

class FarapayamakDriver extends PayamakPanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'farapayamak');
    }
}
