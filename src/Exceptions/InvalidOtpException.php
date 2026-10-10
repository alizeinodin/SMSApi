<?php

namespace Alizeinodin\SmsApi\Exceptions;

class InvalidOtpException extends OtpException
{
    public function __construct(
        string $message = 'Invalid OTP code.',
        public readonly int $attemptsRemaining = 0,
    ) {
        parent::__construct($message);
    }
}
