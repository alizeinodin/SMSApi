<?php

namespace Alizeinodin\SmsApi\Pending;

use Alizeinodin\SmsApi\Jobs\SendMessageJob;
use Alizeinodin\SmsApi\SmsManager;
use DateInterval;
use DateTimeInterface;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Fluent builder for queueing SMS operations onto named/dedicated queues.
 *
 * @example Sms::queue('otp')->sendTemplate('0912...', 100, ['CODE' => '1234']);
 * @example Sms::queue('otp')->tries(5)->backoff([5, 15, 30])->sendTemplate(...);
 */
class QueuedSend
{
    private ?string $driver = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $failoverDrivers = null;

    private DateTimeInterface|DateInterval|int|null $delay = null;

    private ?int $tries = null;

    /**
     * @var array<int, int>
     */
    private array $backoff = [];

    private ?int $timeout = null;

    public function __construct(
        private SmsManager $manager,
        private string $queue,
        private ?string $connection = null,
        private ?string $channel = null,
    ) {}

    public function onConnection(string $connection): self
    {
        $clone = clone $this;
        $clone->connection = $connection;

        return $clone;
    }

    public function delay(DateTimeInterface|DateInterval|int $delay): self
    {
        $clone = clone $this;
        $clone->delay = $delay;

        return $clone;
    }

    public function tries(int $tries): self
    {
        $clone = clone $this;
        $clone->tries = max(1, $tries);

        return $clone;
    }

    /**
     * @param  array<int, int>  $backoff  Seconds between retries, e.g. [5, 15, 30]
     */
    public function backoff(array $backoff): self
    {
        $clone = clone $this;
        $clone->backoff = array_values(array_map('intval', $backoff));

        return $clone;
    }

    public function timeout(int $seconds): self
    {
        $clone = clone $this;
        $clone->timeout = max(1, $seconds);

        return $clone;
    }

    public function driver(string $driver): self
    {
        $clone = clone $this;
        $clone->driver = $driver;
        $clone->failoverDrivers = null;

        return $clone;
    }

    /**
     * Alias for driver() — named connection / account.
     */
    public function connection(string $connection): self
    {
        return $this->driver($connection);
    }

    /**
     * @param  array<int, string>|null  $drivers
     */
    public function failover(?array $drivers = null): self
    {
        $clone = clone $this;
        $clone->failoverDrivers = $drivers;
        $clone->driver = null;

        return $clone;
    }

    /**
     * @param  string|array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function send(string|array $mobiles, string $message, array $options = []): PendingDispatch
    {
        return $this->dispatch('send', [$mobiles, $message, $this->withChannel($options)]);
    }

    /**
     * @param  array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function bulk(string $message, array $mobiles, array $options = []): PendingDispatch
    {
        return $this->dispatch('bulk', [$message, $mobiles, $this->withChannel($options)]);
    }

    /**
     * @param  array<int, string>  $messages
     * @param  array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): PendingDispatch
    {
        return $this->dispatch('sendMapped', [$messages, $mobiles, $this->withChannel($options)]);
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @param  array<string, mixed>  $options
     */
    public function sendTemplate(
        string $mobile,
        int|string $templateId,
        array $parameters = [],
        array $options = [],
    ): PendingDispatch {
        return $this->dispatch('sendTemplate', [$mobile, $templateId, $parameters, $this->withChannel($options)]);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function withChannel(array $options): array
    {
        if ($this->channel !== null && $this->channel !== '') {
            $options['channel'] = $options['channel'] ?? $this->channel;
        }

        return $options;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function dispatch(string $operation, array $arguments): PendingDispatch
    {
        $job = new SendMessageJob(
            operation: $operation,
            arguments: $arguments,
            driver: $this->driver,
            failoverDrivers: $this->failoverDrivers,
            channel: $this->channel,
            tries: $this->tries,
            backoff: $this->backoff,
            timeout: $this->timeout,
        );

        $pending = (new PendingDispatch($job))->onQueue($this->queue);

        if ($this->connection) {
            $pending->onConnection($this->connection);
        }

        if ($this->delay !== null) {
            $pending->delay($this->delay);
        }

        return $pending;
    }
}
