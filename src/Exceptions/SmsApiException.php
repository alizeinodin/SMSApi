<?php

namespace Alizeinodin\SmsApi\Exceptions;

use Exception;
use Throwable;

class SmsApiException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?array $response = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
