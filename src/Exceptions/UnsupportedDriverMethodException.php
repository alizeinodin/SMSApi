<?php

namespace Alizeinodin\SmsApi\Exceptions;

/**
 * Thrown when a driver does not implement / support a unified API method.
 * Failover treats this as a soft failure and continues to the next provider.
 */
class UnsupportedDriverMethodException extends SmsApiException
{
    public function __construct(string $driver, string $method)
    {
        parent::__construct("Driver [{$driver}] does not support method [{$method}].");
    }
}
