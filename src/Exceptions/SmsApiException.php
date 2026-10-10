<?php

namespace Alizeinodin\SmsApi\Exceptions;

use Exception;
use Throwable;

class SmsApiException extends Exception
{
    /**
     * @param  array<string, mixed>|null  $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        public readonly ?array $context = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
