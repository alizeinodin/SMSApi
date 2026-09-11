<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\CircuitOpenException;
use Alizeinodin\SmsApi\Exceptions\FailoverException;
use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Resilience\CircuitBreaker;
use Closure;
use Throwable;

/**
 * Try providers in order until one succeeds (outbound only).
 *
 * Wallet / reports / inbox calls use the first configured provider only.
 */
class FailoverDriver implements SmsDriver
{
    /**
     * @param  array<int, string>  $driverNames
     * @param  Closure(string): MessageDriver  $resolver
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private array $driverNames,
        private Closure $resolver,
        private array $config = [],
        private ?CircuitBreaker $circuitBreaker = null,
    ) {
        $this->driverNames = array_values(array_filter(
            $driverNames,
            fn ($name) => is_string($name) && $name !== '' && $name !== 'failover'
        ));
    }

    public function getName(): string
    {
        return 'failover';
    }

    public function getProvider(): string
    {
        return 'failover';
    }

    public function getChannel(): string
    {
        return 'sms';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        return $this->attempt(__FUNCTION__, func_get_args());
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->attempt(__FUNCTION__, func_get_args());
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->attempt(__FUNCTION__, func_get_args());
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->attempt(__FUNCTION__, func_get_args());
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getPack(string $packId): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getCredit(): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function getLines(): SendResult
    {
        return $this->callPrimary(__FUNCTION__, func_get_args());
    }

    public function setLineNumber(int|string $lineNumber): static
    {
        foreach ($this->driverNames as $name) {
            $this->resolve($name)->setLineNumber($lineNumber);
        }

        return $this;
    }

    public function getLineNumber(): ?int
    {
        foreach ($this->driverNames as $name) {
            $line = $this->resolve($name)->getLineNumber();

            if ($line !== null) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function callPrimary(string $method, array $arguments): SendResult
    {
        if ($this->driverNames === []) {
            throw new FailoverException('Failover driver has no providers configured.', []);
        }

        $driver = $this->resolve($this->driverNames[0]);

        return $driver->{$method}(...$arguments);
    }

    /**
     * @param  array<int, mixed>  $arguments
     *
     * @throws FailoverException
     * @throws RateLimitExceededException
     */
    private function attempt(string $method, array $arguments): SendResult
    {
        if ($this->driverNames === []) {
            throw new FailoverException(
                'Failover driver has no providers configured.',
                [],
            );
        }

        $attempts = [];
        $breaker = $this->circuitBreaker;

        foreach ($this->driverNames as $name) {
            if ($breaker?->isOpen($name)) {
                $attempts[] = [
                    'driver' => $name,
                    'message' => sprintf('Circuit open (retry in %ds)', $breaker->retryAfter($name)),
                    'code' => 503,
                ];

                continue;
            }

            try {
                $driver = $this->resolve($name);
                /** @var SendResult $response */
                $response = $driver->{$method}(...$arguments);

                if ($response->success) {
                    return $attempts === []
                        ? $response
                        : $response->withAttempts($attempts);
                }

                $attempts[] = [
                    'driver' => $driver->getName(),
                    'message' => $response->message !== '' ? $response->message : 'Unsuccessful response.',
                    'code' => $response->statusCode ?? 0,
                ];
            } catch (RateLimitExceededException $e) {
                if ($e->isGlobal()) {
                    throw $e;
                }

                $attempts[] = [
                    'driver' => $name,
                    'message' => $e->getMessage(),
                    'code' => 429,
                ];
            } catch (CircuitOpenException $e) {
                $attempts[] = [
                    'driver' => $e->driver,
                    'message' => $e->getMessage(),
                    'code' => 503,
                ];
            } catch (\Error $e) {
                throw $e;
            } catch (Throwable $e) {
                $attempts[] = [
                    'driver' => $name,
                    'message' => $e->getMessage(),
                    'code' => (int) $e->getCode(),
                ];
            }
        }

        throw new FailoverException(
            sprintf(
                'All SMS providers failed for [%s]: %s',
                $method,
                implode(' → ', array_map(
                    static fn (array $a) => $a['driver'] . ' (' . $a['message'] . ')',
                    $attempts
                ))
            ),
            $attempts,
        );
    }

    private function resolve(string $name): MessageDriver
    {
        return ($this->resolver)($name);
    }
}
