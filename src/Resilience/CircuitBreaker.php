<?php

namespace Alizeinodin\SmsApi\Resilience;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

class CircuitBreaker
{
    public function __construct(
        private CacheRepository $cache,
        private array $config = [],
    ) {}

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function isOpen(string $driver): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $openedAt = $this->cache->get($this->openedKey($driver));

        if ($openedAt === null) {
            return false;
        }

        $openSeconds = $this->openSeconds();
        $elapsed = time() - (int) $openedAt;

        // Half-open window: allow a probe after cooldown.
        if ($elapsed >= $openSeconds) {
            return false;
        }

        return true;
    }

    public function retryAfter(string $driver): int
    {
        $openedAt = $this->cache->get($this->openedKey($driver));

        if ($openedAt === null) {
            return 0;
        }

        return max(0, $this->openSeconds() - (time() - (int) $openedAt));
    }

    public function recordSuccess(string $driver): void
    {
        if (! $this->enabled()) {
            return;
        }

        $this->cache->forget($this->failuresKey($driver));
        $this->cache->forget($this->openedKey($driver));
    }

    public function recordFailure(string $driver): void
    {
        if (! $this->enabled()) {
            return;
        }

        $key = $this->failuresKey($driver);
        $failures = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $failures, $this->openSeconds() * 2);

        if ($failures >= $this->failureThreshold()) {
            $this->cache->put($this->openedKey($driver), time(), $this->openSeconds());
            $this->cache->forget($key);
        }
    }

    private function failureThreshold(): int
    {
        return max(1, (int) ($this->config['failure_threshold'] ?? 5));
    }

    private function openSeconds(): int
    {
        return max(1, (int) ($this->config['open_seconds'] ?? 60));
    }

    private function failuresKey(string $driver): string
    {
        return "smsapi:circuit:{$driver}:failures";
    }

    private function openedKey(string $driver): string
    {
        return "smsapi:circuit:{$driver}:opened_at";
    }
}
