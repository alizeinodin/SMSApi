<?php

namespace Alizeinodin\SmsApi\Policies;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;

/**
 * Resolves named message send policies from config('smsapi.messages').
 *
 * Policies can also be registered / overridden at runtime (in code):
 *
 *   Sms::policies()->define('otp', [
 *       'failover' => ['smsir', 'kavenegar'],
 *       'queue' => 'otp',
 *       'rate_limit' => 'otp',
 *   ]);
 */
class MessagePolicyRegistry
{
    /**
     * @param  array<string, mixed>  $messages
     */
    public function __construct(private array $messages = [])
    {
    }

    public function has(string $type): bool
    {
        return isset($this->messages[$type]) && is_array($this->messages[$type]);
    }

    /**
     * Register or override a message-type policy in code (merges over config).
     *
     * @param  array<string, mixed>  $config
     */
    public function define(string $type, array $config): self
    {
        $existing = isset($this->messages[$type]) && is_array($this->messages[$type])
            ? $this->messages[$type]
            : [];

        $this->messages[$type] = array_replace($existing, $config);

        return $this;
    }

    /**
     * Replace the full policy map (e.g. from a ServiceProvider).
     *
     * @param  array<string, mixed>  $messages
     */
    public function setMessages(array $messages): self
    {
        $this->messages = $messages;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->messages;
    }

    /**
     * @throws SmsApiException
     */
    public function get(string $type): MessagePolicy
    {
        if (! $this->has($type)) {
            throw new SmsApiException("SMS message type [{$type}] is not configured in smsapi.messages.");
        }

        /** @var array<string, mixed> $config */
        $config = $this->messages[$type];

        $failover = $config['failover'] ?? null;
        if (is_string($failover)) {
            $failover = array_values(array_filter(array_map('trim', explode(',', $failover)), static fn ($v) => $v !== ''));
        } elseif (is_array($failover)) {
            $failover = array_values(array_filter(array_map('strval', $failover), static fn ($v) => $v !== ''));
        } else {
            $failover = null;
        }

        if ($failover === []) {
            $failover = null;
        }

        $driver = $config['driver'] ?? null;
        $driver = is_string($driver) && $driver !== '' ? $driver : null;

        $queue = $config['queue'] ?? null;
        $queue = is_string($queue) && $queue !== '' ? $queue : null;

        $rateLimit = $config['rate_limit'] ?? $config['channel'] ?? null;
        $rateLimit = is_string($rateLimit) && $rateLimit !== '' ? $rateLimit : null;

        $timezone = $config['timezone'] ?? null;
        $timezone = is_string($timezone) && $timezone !== '' ? $timezone : null;

        $allowedHours = isset($config['allowed_hours']) && is_array($config['allowed_hours'])
            ? $config['allowed_hours']
            : null;

        $quietHours = isset($config['quiet_hours']) && is_array($config['quiet_hours'])
            ? $config['quiet_hours']
            : null;

        return new MessagePolicy(
            type: $type,
            driver: $driver,
            failover: $failover,
            queue: $queue,
            rateLimit: $rateLimit,
            allowedHours: $allowedHours,
            quietHours: $quietHours,
            timezone: $timezone,
        );
    }

    /**
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys(array_filter(
            $this->messages,
            static fn ($value) => is_array($value),
        ));
    }
}
