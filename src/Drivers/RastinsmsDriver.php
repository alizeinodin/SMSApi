<?php

namespace Alizeinodin\SmsApi\Drivers;

/**
 * Rastin SMS / راستین اس‌ام‌اس — SMS Webservice V3 compatible API.
 */
class RastinsmsDriver extends SmsWebserviceV3Driver
{
    protected function defaultProviderName(): string
    {
        return 'rastinsms';
    }
}
