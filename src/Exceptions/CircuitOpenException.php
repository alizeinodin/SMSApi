<?php

namespace Alizeinodin\SmsApi\Exceptions;

class CircuitOpenException extends SmsApiException
{
    public function __construct(
        public readonly string $driver,
        public readonly int $retryAfterSeconds = 0,
    ) {
        parent::__construct(
            sprintf(
                'Circuit breaker is open for driver [%s]. Retry after %d seconds.',
                $driver,
                $retryAfterSeconds
            ),
            503,
            [
                'driver' => $driver,
                'retry_after' => $retryAfterSeconds,
            ],
        );
    }
}
