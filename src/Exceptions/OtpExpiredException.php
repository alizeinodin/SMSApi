<?php

namespace Alizeinodin\SmsApi\Exceptions;

class OtpExpiredException extends OtpException
{
    public function __construct(string $message = 'OTP has expired.')
    {
        parent::__construct($message);
    }
}
