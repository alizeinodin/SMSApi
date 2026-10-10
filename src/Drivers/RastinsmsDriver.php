<?php

namespace Alizeinodin\SmsApi\Drivers;

class RastinsmsDriver extends SmsWebserviceV3Driver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'rastinsms');
    }
}
