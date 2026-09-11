<?php

namespace Alizeinodin\SmsApi\Concerns;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\MessengerDriver;
use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\Drivers\FailoverDriver;
use Alizeinodin\SmsApi\Drivers\LogTransport;
use Alizeinodin\SmsApi\Drivers\NormalizingDriver;
use Alizeinodin\SmsApi\Drivers\ObservingDriver;
use Alizeinodin\SmsApi\Drivers\PersistingLogDriver;
use Alizeinodin\SmsApi\Drivers\ResilienceDriver;
use Alizeinodin\SmsApi\Drivers\TemplateResolvingDriver;
use Alizeinodin\SmsApi\Logging\SmsLogger;
use Alizeinodin\SmsApi\Resilience\CircuitBreaker;
use Alizeinodin\SmsApi\Resilience\SmsRateLimiter;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;

/**
 * Decorator stack applied to resolved drivers (normalize → templates → resilience → log → events).
 */
trait DecoratesDrivers
{
    protected function wrap(
        MessageDriver $driver,
        bool $wrapLogging = true,
        bool $wrapResilience = true,
        bool $wrapObserving = true,
    ): MessageDriver {
        if ($driver instanceof ObservingDriver) {
            return $driver;
        }

        if ($driver instanceof PersistingLogDriver) {
            return $wrapObserving ? $this->observe($driver) : $driver;
        }

        // Failover already applies resilience / templates / phone normalize per child.
        if ($driver instanceof FailoverDriver) {
            $wrapResilience = false;
        } elseif ($driver instanceof ArrayDriver || $driver instanceof LogTransport) {
            $wrapResilience = false;
        } else {
            if (! $driver instanceof NormalizingDriver
                && ! $driver instanceof TemplateResolvingDriver
                && ! $driver instanceof ResilienceDriver
            ) {
                $driver = new NormalizingDriver(
                    $driver,
                    (array) $this->config()->get('smsapi.phone', []),
                );
            }

            if (! $driver instanceof MessengerDriver
                && ! $driver instanceof TemplateResolvingDriver
                && ! $driver instanceof ResilienceDriver
            ) {
                $driver = new TemplateResolvingDriver(
                    $driver,
                    $this->container->make(TemplateRegistry::class),
                );
            }

            if ($wrapResilience && $this->resilienceEnabled() && ! $driver instanceof ResilienceDriver) {
                $driver = new ResilienceDriver(
                    $driver,
                    $this->container->make(CircuitBreaker::class),
                    $this->container->make(SmsRateLimiter::class),
                );
            }
        }

        // Local transports still get phone normalize + named templates.
        if ($driver instanceof ArrayDriver || $driver instanceof LogTransport) {
            if (! $driver instanceof NormalizingDriver) {
                $driver = new NormalizingDriver(
                    $driver,
                    (array) $this->config()->get('smsapi.phone', []),
                );
            }

            if (! $driver instanceof TemplateResolvingDriver) {
                $driver = new TemplateResolvingDriver(
                    $driver,
                    $this->container->make(TemplateRegistry::class),
                );
            }
        }

        if ($wrapLogging && (bool) $this->config()->get('smsapi.logging.enabled', false)) {
            if (! $driver instanceof PersistingLogDriver) {
                $driver = new PersistingLogDriver($driver, $this->container->make(SmsLogger::class));
            }
        }

        return $wrapObserving ? $this->observe($driver) : $driver;
    }

    /**
     * Outermost decorator: SmsSending / SmsSent / SmsFailed.
     */
    protected function observe(MessageDriver $driver): MessageDriver
    {
        if ($driver instanceof ObservingDriver) {
            return $driver;
        }

        return new ObservingDriver(
            $driver,
            $this->container->make('events'),
        );
    }

    protected function resilienceEnabled(): bool
    {
        return (bool) $this->config()->get('smsapi.resilience.circuit_breaker.enabled', false)
            || (bool) $this->config()->get('smsapi.resilience.rate_limit.enabled', false);
    }
}
