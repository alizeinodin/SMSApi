<?php

namespace Alizeinodin\SmsApi\Otp;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\OtpException;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Pending\TemplateSend;
use Alizeinodin\SmsApi\SmsManager;
use DateInterval;
use DateTimeInterface;
use Illuminate\Foundation\Bus\PendingDispatch;
use Throwable;

/**
 * Fluent OTP issue: generate + store (HMAC) + deliver via TemplateSend.
 *
 * @example Sms::challenge('login')->template('otp-login')->to($user)->send();
 * @example Sms::challenge('login')->check($user, $request->input('code'));
 */
class OtpIssue
{
    private ?string $template = null;

    private string|object|null $recipient = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $channels = null;

    private ?string $driver = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $failoverDrivers = null;

    private bool $useDefaultFailover = false;

    private ?string $queue = null;

    private bool $forceSync = true;

    private DateTimeInterface|DateInterval|int|null $delay = null;

    private ?int $length = null;

    private ?int $ttl = null;

    private ?int $maxAttempts = null;

    private ?string $fixedCode = null;

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    public function __construct(
        private SmsManager $manager,
        private OtpVault $vault,
        private string $purpose,
    ) {}

    public function template(string $name): self
    {
        $clone = clone $this;
        $clone->template = $name;

        return $clone;
    }

    public function to(string|object $recipient): self
    {
        $clone = clone $this;
        $clone->recipient = $recipient;

        return $clone;
    }

    /**
     * @param  string|array<int, string>  $channels
     */
    public function via(string|array $channels): self
    {
        $clone = clone $this;
        $clone->channels = TemplateSend::normalizeChannels($channels);

        return $clone;
    }

    public function mail(): self
    {
        return $this->via('mail');
    }

    public function sms(): self
    {
        return $this->via('sms');
    }

    public function both(): self
    {
        return $this->via('both');
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
     * @param  array<int, string>|null  $drivers
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
     * Queue delivery. Requires smsapi.otp.vault.allow_queue=true (CODE lands in the job payload).
     */
    public function queue(?string $name = null): self
    {
        $clone = clone $this;
        $clone->forceSync = false;
        $clone->queue = $name ?? (string) config('smsapi.messages.otp.queue', config('smsapi.queue.default', 'otp'));

        return $clone;
    }

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
        $clone->delay = $delay;
        $clone->forceSync = false;

        if ($queue !== null) {
            $clone->queue = $queue;
        } elseif ($clone->queue === null) {
            $clone->queue = (string) config('smsapi.messages.otp.queue', 'otp');
        }

        return $clone;
    }

    public function length(int $length): self
    {
        $clone = clone $this;
        $clone->length = $length;

        return $clone;
    }

    public function ttl(int $seconds): self
    {
        $clone = clone $this;
        $clone->ttl = $seconds;

        return $clone;
    }

    public function maxAttempts(int $max): self
    {
        $clone = clone $this;
        $clone->maxAttempts = $max;

        return $clone;
    }

    /**
     * Force a known code (tests / migration only). Prefer random generation in production.
     */
    public function usingCode(string $code): self
    {
        $clone = clone $this;
        $clone->fixedCode = trim($code);

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
     * Generate, store HMAC hash, and deliver via named template.
     *
     * Defaults to synchronous send so the CODE is not written to the queue.
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function send(array $parameters = []): OtpIssueResult
    {
        if ($this->recipient === null) {
            throw new OtpException('OtpIssue requires to($recipient) before send().');
        }

        if ($this->template === null || $this->template === '') {
            throw new OtpException(
                'OtpIssue requires template($name). Purpose is only the vault namespace (e.g. login), not the SMS template.'
            );
        }

        $wantsQueue = ! $this->forceSync && ($this->queue !== null || $this->delay !== null);

        if ($wantsQueue && ! $this->vault->settings()['allow_queue']) {
            throw new OtpException(
                'Queued OTP vault delivery is disabled. Use sync() (default), or set SMSAPI_OTP_VAULT_ALLOW_QUEUE=true (stores CODE in the job payload).'
            );
        }

        $stored = $this->vault->store(
            $this->recipient,
            $this->purpose,
            $this->fixedCode,
            $this->length,
            $this->ttl,
            $this->maxAttempts,
        );

        $settings = $this->vault->settings();
        $codeParam = $settings['code_parameter'];
        $parameters = $this->injectCode($parameters, $codeParam, $stored['code']);
        $normalizedRecipient = $this->vault->normalizeRecipient($this->recipient);

        $options = array_merge($this->options, [
            'channel' => $this->options['channel'] ?? 'otp',
            'otp_vault' => [
                'recipient' => $normalizedRecipient,
                'purpose' => $this->purpose,
                'challenge_id' => $stored['challenge']->id,
            ],
        ]);

        try {
            $pending = $this->manager->template($this->template)->to($this->recipient);

            if ($this->channels !== null) {
                $pending = $pending->via($this->channels);
            }

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
                $pending = $pending->later($this->delay, $this->queue);
            }

            $pending = $pending->options($options);
            $send = $pending->send($parameters);
        } catch (Throwable $e) {
            $this->vault->invalidate($this->recipient, $this->purpose);

            throw $e instanceof SmsApiException
                ? $e
                : new SmsApiException($e->getMessage(), (int) $e->getCode(), previous: $e);
        }

        if ($send instanceof PendingDispatch) {
            $sendResult = new SendResult(
                success: true,
                message: 'OTP queued',
                data: [
                    'queued' => true,
                    'otp_vault' => $options['otp_vault'],
                ],
                driver: 'queue',
            );
        } else {
            $sendResult = $send;
        }

        // Partial both-channel failure: keep challenge if any channel succeeded
        // (user may already have the code). Full failure → invalidate.
        if (! $sendResult->success) {
            $this->vault->invalidate($this->recipient, $this->purpose);

            throw new SmsApiException(
                'OTP delivery failed; challenge was invalidated.',
                0,
                $sendResult->toArray(),
            );
        }

        return new OtpIssueResult(
            send: $sendResult,
            challengeId: $stored['challenge']->id,
            recipient: $normalizedRecipient,
            purpose: $this->purpose,
            expiresAt: $stored['expires_at'],
            code: $settings['reveal_code'] ? $stored['code'] : null,
        );
    }

    /**
     * Check a code for this purpose.
     */
    public function check(string|object $recipient, string $code, bool $throw = true): OtpCheckResult
    {
        return $this->vault->check($recipient, $code, $this->purpose, $throw);
    }

    public function invalidate(string|object $recipient): int
    {
        return $this->vault->invalidate($recipient, $this->purpose);
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return array<string, string>|array<int, array{name: string, value: string}>
     */
    private function injectCode(array $parameters, string $codeParam, string $code): array
    {
        if ($parameters === []) {
            return [$codeParam => $code];
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            foreach ($parameters as $index => $item) {
                if (strcasecmp((string) ($item['name'] ?? ''), $codeParam) === 0) {
                    $parameters[$index]['value'] = $code;

                    return $parameters;
                }
            }

            $parameters[] = ['name' => $codeParam, 'value' => $code];

            return $parameters;
        }

        $parameters[$codeParam] = $code;

        return $parameters;
    }
}
