<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;

/**
 * Assert the inner driver supports the full SMS surface.
 */
trait RequiresSmsDriver
{
    abstract protected function delegate(): MessageDriver;

    protected function smsDriver(?string $method = null): SmsDriver
    {
        $inner = $this->delegate();

        if ($inner instanceof SmsDriver) {
            return $inner;
        }

        throw new UnsupportedDriverMethodException(
            $inner->getName(),
            $method ?? debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
        );
    }
}
