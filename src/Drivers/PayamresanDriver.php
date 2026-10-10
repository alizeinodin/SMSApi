<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * @see https://doc.sms-webservice.com/
 */
class PayamresanDriver extends SmsWebserviceV3Driver
{
    protected function defaultProviderName(): string
    {
        return 'payamresan';
    }
}
