<?php

namespace Alizeinodin\SmsApi\Drivers;

class BehinpayamDriver extends SmsWebserviceV3Driver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'behinpayam');
    }
}
