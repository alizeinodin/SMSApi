<?php

namespace Alizeinodin\SmsApi\Exceptions;

use Throwable;

/**
 * Thrown when every provider in a failover chain fails.
 */
class FailoverException extends SmsApiException
{
    /**
     * @param  array<int, array{driver: string, message: string, code: int}>  $attempts
     */
    public function __construct(
        string $message,
        public readonly array $attempts = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, ['attempts' => $attempts], $previous);
    }
}
