<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\DelegatesSmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\CircuitOpenException;
use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Resilience\CircuitBreaker;
use Alizeinodin\SmsApi\Resilience\SmsRateLimiter;
use Throwable;

/**
 * Decorator: rate-limit outbound traffic and trip a per-driver circuit breaker.
 */
class ResilienceDriver implements SmsDriver
{
    use DelegatesSmsDriver;

    private const OUTBOUND = ['send', 'bulk', 'sendMapped', 'sendTemplate'];

    public function __construct(
        private MessageDriver $driver,
        private CircuitBreaker $circuitBreaker,
        private SmsRateLimiter $rateLimiter,
    ) {
    }

    protected function delegate(): MessageDriver
    {
        return $this->driver;
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $options = $this->withRecipientOption($options, $recipients);

        return $this->guard(__FUNCTION__, [$recipients, $message, $options], $options);
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $options = $this->withRecipientOption($options, $recipients);

        return $this->guard(__FUNCTION__, [$message, $recipients, $options], $options);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $options = $this->withRecipientOption($options, $mobiles);

        return $this->guard(__FUNCTION__, [$messages, $mobiles, $options], $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $options = $this->withRecipientOption($options, $mobile);

        return $this->guard(__FUNCTION__, [$mobile, $templateId, $parameters, $options], $options);
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @param  array<string, mixed>  $options
     */
    private function guard(string $method, array $arguments, array $options): SendResult
    {
        $name = $this->driver->getName();

        if ($this->circuitBreaker->isOpen($name)) {
            throw new CircuitOpenException($name, $this->circuitBreaker->retryAfter($name));
        }

        $this->rateLimiter->hit($name, $options);

        try {
            if (in_array($method, ['sendMapped', 'sendTemplate'], true)) {
                /** @var SendResult $response */
                $response = $this->smsDriver($method)->{$method}(...$arguments);
            } else {
                /** @var SendResult $response */
                $response = $this->driver->{$method}(...$arguments);
            }

            if ($response->success) {
                $this->circuitBreaker->recordSuccess($name);
            } else {
                $this->circuitBreaker->recordFailure($name);
            }

            return $response;
        } catch (UnsupportedDriverMethodException|RateLimitExceededException|CircuitOpenException $e) {
            throw $e;
        } catch (Throwable $e) {
            $this->circuitBreaker->recordFailure($name);

            throw $e instanceof SmsApiException
                ? $e
                : new SmsApiException($e->getMessage(), (int) $e->getCode(), previous: $e);
        }
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  string|array<int, string>  $recipients
     * @return array<string, mixed>
     */
    private function withRecipientOption(array $options, string|array $recipients): array
    {
        if (isset($options['mobile']) || isset($options['recipient']) || isset($options['rate_limit_recipient'])) {
            return $options;
        }

        $options['mobile'] = is_array($recipients) ? ($recipients[0] ?? null) : $recipients;

        return $options;
    }
}
