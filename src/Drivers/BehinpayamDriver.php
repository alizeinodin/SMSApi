<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * @see https://behinpayam.com/sms-web-service/
 */
class BehinpayamDriver extends SmsWebserviceV3Driver
{
    protected function defaultProviderName(): string
    {
        return 'behinpayam';
    }
}
