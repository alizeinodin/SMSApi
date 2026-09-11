<?php

namespace Alizeinodin\SmsApi\Resilience;

use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Illuminate\Cache\RateLimiter;

class SmsRateLimiter
{
    public function __construct(
        private RateLimiter $limiter,
        private array $config = [],
    ) {
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    /**
     * @param  array<string, mixed>  $options
     *
     * @throws RateLimitExceededException
     */
    public function hit(string $driver, array $options = []): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->attempt('global', 'global', (array) ($this->config['global'] ?? []));

        $channel = $this->resolveChannel($options);
        if ($channel !== null) {
            $channels = (array) ($this->config['channels'] ?? []);
            if (isset($channels[$channel]) && is_array($channels[$channel])) {
                $this->attempt('channel', $channel, $channels[$channel]);
            }
        }

        $recipient = $this->resolveRecipient($options);
        if ($recipient !== null) {
            $this->attempt(
                'recipient',
                hash('sha256', $recipient),
                (array) ($this->config['recipient'] ?? []),
            );
        }

        $this->attempt('driver', $driver, (array) ($this->config['driver'] ?? []));
    }

    /**
     * @param  array<string, mixed>  $limit
     *
     * @throws RateLimitExceededException
     */
    private function attempt(string $scope, string $key, array $limit): void
    {
        $max = (int) ($limit['max'] ?? 0);
        $perSeconds = (int) ($limit['per_seconds'] ?? 60);

        if ($max <= 0) {
            return;
        }

        $limiterKey = "smsapi:rate:{$scope}:{$key}";

        if ($this->limiter->tooManyAttempts($limiterKey, $max)) {
            throw new RateLimitExceededException(
                $scope,
                $key,
                $this->limiter->availableIn($limiterKey),
            );
        }

        $this->limiter->hit($limiterKey, max(1, $perSeconds));
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveChannel(array $options): ?string
    {
        foreach (['rate_limit_channel', 'channel', 'queue'] as $key) {
            if (! empty($options[$key]) && is_string($options[$key])) {
                return $options[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveRecipient(array $options): ?string
    {
        foreach (['rate_limit_recipient', 'mobile', 'recipient'] as $key) {
            if (! empty($options[$key]) && is_string($options[$key])) {
                return trim($options[$key]);
            }

            if (! empty($options[$key]) && is_array($options[$key])) {
                $first = reset($options[$key]);

                if (is_string($first) && trim($first) !== '') {
                    return trim($first);
                }
            }
        }

        return null;
    }
}
