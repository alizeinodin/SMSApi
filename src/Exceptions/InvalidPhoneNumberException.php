<?php

namespace Alizeinodin\SmsApi\Exceptions;

class InvalidPhoneNumberException extends SmsApiException
{
    public function __construct(string $mobile, string $reason = 'Invalid Iranian mobile number.')
    {
        parent::__construct(sprintf('%s [%s]', $reason, $mobile));
    }
}
