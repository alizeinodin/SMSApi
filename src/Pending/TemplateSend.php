<?php

namespace Alizeinodin\SmsApi\Pending;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Mail\TemplateMailer;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\SmsManager;
use Alizeinodin\SmsApi\Support\RecipientResolver;
use Alizeinodin\SmsApi\Templates\TemplateDefinition;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use DateInterval;
use DateTimeInterface;
use Illuminate\Foundation\Bus\PendingDispatch;

/**
 * Fluent sender for a named template.
 *
 * @example Sms::template('otp-login')->to($user)->send(['CODE' => '1234']);
 * @example Sms::template('otp-login')->to($user)->via('mail')->send(['CODE' => '1234']);
 * @example Sms::template('otp-login')->to('0912...')->failover()->queue('otp')->send([...]);
 */
class TemplateSend
{
    private ?string $mobile = null;

    private ?string $email = null;

    /**
     * @var array<int, string>
     */
    private array $channels = ['sms'];

    private ?string $driver = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $failoverDrivers = null;

    private bool $useDefaultFailover = false;

    private ?string $queue = null;

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
        private TemplateRegistry $registry,
        private string $name,
    ) {
        $definition = $this->definition();

        if ($definition->queue !== null) {
            $this->queue = $definition->queue;
        }

        if ($definition->failover !== null) {
            $this->failoverDrivers = $definition->failover;
        }

        $this->options = $definition->options;
        $this->channels = self::normalizeChannels(config('smsapi.otp.channel', 'sms'));
    }

    public function to(string|object $recipient): self
    {
        $clone = clone $this;
        $clone->mobile = $this->tryExtractMobile($recipient);
        $clone->email = $this->tryExtractEmail($recipient);

        if ($clone->mobile === null && $clone->email === null) {
            throw new SmsApiException(
                'Unable to resolve a mobile number or email from the given recipient.'
            );
        }

        return $clone;
    }

    /**
     * Delivery channels: sms, mail, or both.
     *
     * @param  string|array<int, string>  $channels  sms|mail|both or ['sms','mail']
     */
    public function via(string|array $channels): self
    {
        $clone = clone $this;
        $clone->channels = self::normalizeChannels($channels);

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
     * Alias for driver() — named connection / account.
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

    public function queue(?string $name = null): self
    {
        $clone = clone $this;
        $clone->queue = $name
            ?? $this->definition()->queue
            ?? (string) config('smsapi.queue.default', 'default');

        return $clone;
    }

    /**
     * Force synchronous send even if the template defines a default queue.
     */
    public function sync(): self
    {
        $clone = clone $this;
        $clone->queue = null;
        $clone->delay = null;

        return $clone;
    }

    public function later(DateTimeInterface|DateInterval|int $delay, ?string $queue = null): self
    {
        $clone = clone $this;
        $clone->delay = $delay;

        if ($queue !== null) {
            $clone->queue = $queue;
        } elseif ($clone->queue === null) {
            $clone->queue = (string) config('smsapi.queue.default', 'default');
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
     * Send (or queue) the named template over the configured channel(s).
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function send(array $parameters = []): SendResult|PendingDispatch
    {
        $this->assertMessagePolicyWindow();

        $wantsSms = in_array('sms', $this->channels, true);
        $wantsMail = in_array('mail', $this->channels, true);

        if (! $wantsSms && ! $wantsMail) {
            throw new SmsApiException('TemplateSend requires at least one channel (sms or mail).');
        }

        // Ensure the template exists up-front (clearer error than deep in the driver).
        $definition = $this->definition();

        if ($wantsSms && ($this->mobile === null || $this->mobile === '')) {
            throw new SmsApiException('TemplateSend requires to($mobile) before send() when using the sms channel.');
        }

        if ($wantsMail && ($this->email === null || $this->email === '')) {
            throw new SmsApiException('TemplateSend requires a recipient email before send() when using the mail channel.');
        }

        $mailResponse = null;

        if ($wantsMail) {
            $mailResponse = $this->sendMail($definition, $parameters);
        }

        if (! $wantsSms) {
            return $mailResponse ?? new SendResult(success: false, message: 'Nothing sent', driver: 'mail');
        }

        if ($this->queue !== null) {
            $pending = $this->dispatchQueued($this->mobile, $parameters);

            if ($mailResponse !== null) {
                // SMS is queued; mail already handled (queued or sent).
                return $pending;
            }

            return $pending;
        }

        $smsResponse = $this->client()->sendTemplate($this->mobile, $this->name, $parameters, $this->options);

        if ($mailResponse === null) {
            return $smsResponse;
        }

        return new SendResult(
            success: $smsResponse->success && $mailResponse->success,
            message: $smsResponse->success && $mailResponse->success
                ? 'Sent via sms and mail'
                : 'Partial failure sending otp',
            data: [
                'sms' => $smsResponse->toArray(),
                'mail' => $mailResponse->toArray(),
            ],
            driver: 'sms+mail',
            raw: [
                'sms' => $smsResponse->raw,
                'mail' => $mailResponse->raw,
            ],
            statusCode: $smsResponse->statusCode,
            attempts: $smsResponse->attempts,
        );
    }

    /**
     * @param  string|array<int, string>  $channels
     * @return array<int, string>
     */
    public static function normalizeChannels(string|array $channels): array
    {
        if (is_string($channels)) {
            $channels = strtolower(trim($channels));

            return match ($channels) {
                'mail', 'email' => ['mail'],
                'both', 'all' => ['sms', 'mail'],
                'sms' => ['sms'],
                default => (static function () use ($channels): array {
                    $parsed = array_values(array_unique(array_filter(array_map(
                        static fn (string $c): string => match (strtolower(trim($c))) {
                            'mail', 'email' => 'mail',
                            'sms' => 'sms',
                            default => '',
                        },
                        explode(',', $channels),
                    ))));

                    return $parsed === [] ? ['sms'] : $parsed;
                })(),
            };
        }

        $normalized = [];

        foreach ($channels as $channel) {
            if (! is_string($channel)) {
                continue;
            }

            $channel = strtolower(trim($channel));

            if ($channel === 'both' || $channel === 'all') {
                return ['sms', 'mail'];
            }

            if (in_array($channel, ['mail', 'email'], true)) {
                $normalized[] = 'mail';
            } elseif ($channel === 'sms') {
                $normalized[] = 'sms';
            }
        }

        $normalized = array_values(array_unique($normalized));

        return $normalized === [] ? ['sms'] : $normalized;
    }

    private function definition(): TemplateDefinition
    {
        return $this->registry->get($this->name);
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    private function sendMail(TemplateDefinition $definition, array $parameters): SendResult
    {
        /** @var TemplateMailer $mailer */
        $mailer = app(TemplateMailer::class);

        return $mailer->send(
            email: (string) $this->email,
            templateName: $this->name,
            parameters: $parameters,
            definition: $definition,
            queue: $this->queue !== null,
        );
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    private function dispatchQueued(string $mobile, array $parameters): PendingDispatch
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

        return $pending->sendTemplate($mobile, $this->name, $parameters, $this->options);
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

    private function tryExtractMobile(string|object $recipient): ?string
    {
        return RecipientResolver::extractMobile($recipient);
    }

    private function tryExtractEmail(string|object $recipient): ?string
    {
        return RecipientResolver::extractEmail($recipient);
    }

    private function looksLikeEmail(string $value): bool
    {
        return RecipientResolver::looksLikeEmail($value);
    }

    /**
     * When options carry message_type (from Sms::policy()->template()), enforce send windows.
     * Delayed queued sends are re-checked in SendMessageJob.
     */
    private function assertMessagePolicyWindow(): void
    {
        $type = $this->options['message_type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        if ($this->queue !== null && $this->delay !== null) {
            return;
        }

        /** @var MessagePolicyRegistry $registry */
        $registry = app(MessagePolicyRegistry::class);
        $registry->get($type)->assertWithinAllowedWindow();
    }
}
