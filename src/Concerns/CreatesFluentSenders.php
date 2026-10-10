<?php

namespace Alizeinodin\SmsApi\Concerns;

use Alizeinodin\SmsApi\Otp\OtpIssue;
use Alizeinodin\SmsApi\Otp\OtpVault;
use Alizeinodin\SmsApi\Pending\PolicySend;
use Alizeinodin\SmsApi\Pending\QueuedSend;
use Alizeinodin\SmsApi\Pending\TemplateSend;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use DateInterval;
use DateTimeInterface;

/**
 * High-level fluent entry points (templates, policies, queues, OTP vault).
 */
trait CreatesFluentSenders
{
    /**
     * Fluent sender for a named template from the registry.
     *
     * @example Sms::template('otp-login')->to('0912...')->send(['CODE' => '1234']);
     */
    public function template(string $name): TemplateSend
    {
        return new TemplateSend($this, $this->templates(), $name);
    }

    /**
     * Alias for template() — OTP-oriented delivery (not the vault).
     *
     * @example Sms::otp('otp-login')->to($user)->mail()->send(['CODE' => '1234']);
     */
    public function otp(string $name): TemplateSend
    {
        return $this->template($name);
    }

    /**
     * Optional OTP vault: generate + HMAC store + deliver + check.
     *
     * @example Sms::challenge('login')->template('otp-login')->to($user)->send();
     */
    public function challenge(string $purpose): OtpIssue
    {
        return new OtpIssue($this, $this->otpVault(), $purpose);
    }

    public function otpVault(): OtpVault
    {
        return $this->container->make(OtpVault::class);
    }

    /**
     * @return array<int, string>
     */
    public function otpChannels(): array
    {
        return TemplateSend::normalizeChannels(config('smsapi.otp.channel', 'sms'));
    }

    public function templates(): TemplateRegistry
    {
        return $this->container->make(TemplateRegistry::class);
    }

    /**
     * @example Sms::policy('otp')->failover(['smsir', 'kavenegar'])->sendTemplate(...);
     */
    public function policy(string $type): PolicySend
    {
        return new PolicySend($this, $this->policies()->get($type));
    }

    public function policies(): MessagePolicyRegistry
    {
        return $this->container->make(MessagePolicyRegistry::class);
    }

    /**
     * Queue SMS on a named queue from config (e.g. "otp" → "sms-otp").
     */
    public function queue(?string $name = null): QueuedSend
    {
        $alias = ($name === null || $name === '')
            ? (string) $this->config()->get('smsapi.queue.default', 'default')
            : $name;

        return new QueuedSend(
            $this,
            $this->resolveQueueName($name),
            $this->queueConnection(),
            $alias,
        );
    }

    /**
     * Queue SMS on an explicit queue name (not looked up in config aliases).
     */
    public function onQueue(string $queue): QueuedSend
    {
        return new QueuedSend($this, $queue, $this->queueConnection(), $queue);
    }

    /**
     * Delay a queued SMS.
     */
    public function later(DateTimeInterface|DateInterval|int $delay, ?string $queue = null): QueuedSend
    {
        return $this->queue($queue)->delay($delay);
    }

    /**
     * Resolve alias like "otp" to configured queue name.
     */
    public function resolveQueueName(?string $name = null): string
    {
        $queues = (array) $this->config()->get('smsapi.queue.queues', []);
        $defaultAlias = (string) $this->config()->get('smsapi.queue.default', 'default');

        if ($name === null || $name === '') {
            $name = $defaultAlias;
        }

        if (isset($queues[$name]) && is_string($queues[$name]) && $queues[$name] !== '') {
            return $queues[$name];
        }

        return $name;
    }

    protected function queueConnection(): ?string
    {
        $connection = $this->config()->get('smsapi.queue.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }
}
