<?php

namespace Alizeinodin\SmsApi\Exceptions;

class OtpAttemptsExceededException extends OtpException
{
    public function __construct(string $message = 'OTP attempt limit exceeded.')
    {
        parent::__construct($message);
    }
}
