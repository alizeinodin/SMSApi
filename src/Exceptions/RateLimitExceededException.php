<?php

namespace Alizeinodin\SmsApi\Exceptions;

class RateLimitExceededException extends SmsApiException
{
    public function __construct(
        public readonly string $scope,
        public readonly string $key,
        public readonly int $retryAfterSeconds = 0,
    ) {
        parent::__construct(
            sprintf(
                'SMS rate limit exceeded for %s [%s]. Retry after %d seconds.',
                $scope,
                $key,
                $retryAfterSeconds
            ),
            429,
            [
                'scope' => $scope,
                'key' => $key,
                'retry_after' => $retryAfterSeconds,
            ],
        );
    }

    public function isGlobal(): bool
    {
        return $this->scope === 'global' || $this->scope === 'channel';
    }
}
