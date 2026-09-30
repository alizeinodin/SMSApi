<?php

namespace Alizeinodin\SmsApi\Drivers;

class PayamresanDriver extends SmsWebserviceV3Driver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'payamresan');
    }
}
