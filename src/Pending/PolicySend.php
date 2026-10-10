<?php

namespace Alizeinodin\SmsApi\Pending;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Policies\MessagePolicy;
use Alizeinodin\SmsApi\SmsManager;
use DateInterval;
use DateTimeInterface;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Fluent sender that applies a named message-type policy (driver, queue, rate-limit channel, hours).
 *
 * @example Sms::policy('otp')->failover(['smsir-otp', 'smsir-marketing'])->sendTemplate(...);
 * @example Sms::policy('marketing')->connection('smsir-marketing')->send(...);
 */
class PolicySend
{
    private ?string $driver = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $failoverDrivers = null;

    private bool $useDefaultFailover = false;

    private ?string $queue = null;

    private bool $forceSync = false;

    private DateTimeInterface|DateInterval|int|null $delay = null;

    private ?int $tries = null;

    /**
     * @var array<int, int>
     */
    private array $backoff = [];

    private ?int $timeout = null;

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    public function __construct(
        private SmsManager $manager,
        private MessagePolicy $policy,
    ) {
        if ($policy->failover !== null) {
            $this->failoverDrivers = $policy->failover;
        } elseif ($policy->driver !== null) {
            $this->driver = $policy->driver;
        }

        if ($policy->queue !== null) {
            $this->queue = $policy->queue;
        }
    }

    public function type(): string
    {
        return $this->policy->type;
    }

    public function policy(): MessagePolicy
    {
        return $this->policy;
    }

    public function driver(string $driver): self
    {
        $clone = clone $this;
        $clone->driver = $driver;
        $clone->failoverDrivers = null;
        $clone->useDefaultFailover = false;

        return $clone;
    }

    /**
     * Alias for driver() — select a named connection / account.
     */
    public function connection(string $connection): self
    {
        return $this->driver($connection);
    }

    /**
     * @param  array<int, string>|null  $drivers  null = config default failover list
     */
    public function failover(?array $drivers = null): self
    {
        $clone = clone $this;
        $clone->driver = null;

        if ($drivers === null) {
            $clone->useDefaultFailover = true;
            $clone->failoverDrivers = null;
        } else {
            $clone->useDefaultFailover = false;
            $clone->failoverDrivers = array_values($drivers);
        }

        return $clone;
    }

    /**
     * Queue on a named alias (overrides policy queue). Empty/null keeps policy / default.
     */
    public function queue(?string $name = null): self
    {
        $clone = clone $this;
        $clone->forceSync = false;
        $clone->queue = $name ?? $this->policy->queue ?? (string) config('smsapi.queue.default', 'default');

        return $clone;
    }

    /**
     * Send synchronously even if the policy defines a default queue.
     */
    public function sync(): self
    {
        $clone = clone $this;
        $clone->forceSync = true;
        $clone->queue = null;
        $clone->delay = null;

        return $clone;
    }

    public function later(DateTimeInterface|DateInterval|int $delay, ?string $queue = null): self
    {
        $clone = clone $this;
        $clone->forceSync = false;
        $clone->delay = $delay;

        if ($queue !== null) {
            $clone->queue = $queue;
        } elseif ($clone->queue === null) {
            $clone->queue = $this->policy->queue
                ?? (string) config('smsapi.queue.default', 'default');
        }

        return $clone;
    }

    public function tries(int $tries): self
    {
        $clone = clone $this;
        $clone->tries = max(1, $tries);

        return $clone;
    }

    /**
     * @param  array<int, int>  $backoff
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

    /**
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $clone = clone $this;
        $clone->options = array_merge($clone->options, $options);

        return $clone;
    }

    /**
     * named template with this message policy's defaults applied.
     */
    public function template(string $name): TemplateSend
    {
        $pending = $this->manager->template($name);

        if ($this->useDefaultFailover) {
            $pending = $pending->failover(null);
        } elseif ($this->failoverDrivers !== null) {
            $pending = $pending->failover($this->failoverDrivers);
        } elseif ($this->driver !== null) {
            $pending = $pending->driver($this->driver);
        }

        if ($this->forceSync) {
            $pending = $pending->sync();
        } elseif ($this->queue !== null) {
            $pending = $pending->queue($this->queue);
        }

        if ($this->delay !== null) {
            $pending = $pending->later($this->delay);
        }

        if ($this->tries !== null) {
            $pending = $pending->tries($this->tries);
        }

        if ($this->backoff !== []) {
            $pending = $pending->backoff($this->backoff);
        }

        if ($this->timeout !== null) {
            $pending = $pending->timeout($this->timeout);
        }

        return $pending->options($this->policyOptions());
    }

    /**
     * @param  string|array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function send(string|array $mobiles, string $message, array $options = []): SendResult|PendingDispatch
    {
        return $this->dispatchOrSend('send', [$mobiles, $message, $this->mergeOptions($options)]);
    }

    /**
     * @param  array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function bulk(string $message, array $mobiles, array $options = []): SendResult|PendingDispatch
    {
        return $this->dispatchOrSend('bulk', [$message, $mobiles, $this->mergeOptions($options)]);
    }

    /**
     * @param  array<int, string>  $messages
     * @param  array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult|PendingDispatch
    {
        return $this->dispatchOrSend('sendMapped', [$messages, $mobiles, $this->mergeOptions($options)]);
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
    ): SendResult|PendingDispatch {
        return $this->dispatchOrSend('sendTemplate', [$mobile, $templateId, $parameters, $this->mergeOptions($options)]);
    }

    /**
     * @param  array<int, mixed>  $arguments  Last element is options array for outbound ops
     */
    private function dispatchOrSend(string $operation, array $arguments): SendResult|PendingDispatch
    {
        $shouldQueue = ! $this->forceSync && $this->queue !== null;

        // Delayed jobs re-check the window when they run; immediate sends check now.
        if (! $shouldQueue || $this->delay === null) {
            $this->policy->assertWithinAllowedWindow();
        }

        if ($shouldQueue) {
            return $this->dispatchQueued($operation, $arguments);
        }

        $client = $this->client();

        /** @var SendResult $response */
        $response = $client->{$operation}(...$arguments);

        return $response;
    }

    /**
     * @param  array<int, mixed>  $arguments
     */
    private function dispatchQueued(string $operation, array $arguments): PendingDispatch
    {
        $pending = $this->manager->queue($this->queue);

        if ($this->useDefaultFailover) {
            $pending = $pending->failover(null);
        } elseif ($this->failoverDrivers !== null) {
            $pending = $pending->failover($this->failoverDrivers);
        } elseif ($this->driver !== null) {
            $pending = $pending->driver($this->driver);
        }

        if ($this->delay !== null) {
            $pending = $pending->delay($this->delay);
        }

        if ($this->tries !== null) {
            $pending = $pending->tries($this->tries);
        }

        if ($this->backoff !== []) {
            $pending = $pending->backoff($this->backoff);
        }

        if ($this->timeout !== null) {
            $pending = $pending->timeout($this->timeout);
        }

        return $pending->{$operation}(...$arguments);
    }

    private function client(): SmsDriver
    {
        if ($this->useDefaultFailover) {
            return $this->manager->failover();
        }

        if ($this->failoverDrivers !== null) {
            return $this->manager->failover($this->failoverDrivers);
        }

        return $this->manager->driver($this->driver);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function mergeOptions(array $options): array
    {
        return array_merge($this->policyOptions(), $this->options, $options);
    }

    /**
     * @return array<string, mixed>
     */
    private function policyOptions(): array
    {
        return [
            'channel' => $this->policy->channel(),
            'message_type' => $this->policy->type,
            'rate_limit_channel' => $this->policy->channel(),
        ];
    }
}
