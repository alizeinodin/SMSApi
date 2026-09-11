<?php

namespace Alizeinodin\SmsApi\Notifications;

use Alizeinodin\SmsApi\Mail\TemplateMailer;
use Alizeinodin\SmsApi\Pending\TemplateSend;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use Illuminate\Notifications\Messages\MailMessage;

final class NotificationMessage
{
    private ?string $content = null;

    private ?string $to = null;

    private bool $usingTemplate = false;

    private int|string|null $templateId = null;

    /**
     * @var array<string, string>|array<int, array{name: string, value: string}>
     */
    private array $parameters = [];

    /**
     * @var array<string, mixed>
     */
    private array $options = [];

    private ?string $driver = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $failoverDrivers = null;

    private ?string $queue = null;

    private \DateTimeInterface|\DateInterval|int|null $queueDelay = null;

    private ?int $tries = null;

    /**
     * @var array<int, int>
     */
    private array $backoff = [];

    private ?int $timeout = null;

    /**
     * Preferred delivery channels for OTP-style notifications (sms / mail).
     *
     * @var array<int, string>
     */
    private array $channels = ['sms'];

    public static function create(?string $content = null): self
    {
        $message = new self;

        if ($content !== null) {
            $message->content($content);
        }

        return $message;
    }

    /**
     * Apply a named message-type policy (driver / failover / queue / rate-limit channel / hours).
     *
     * @example NotificationMessage::create()->policy('otp')->sendTemplate('otp-login', ['CODE' => '1234']);
     * @example NotificationMessage::create('تخفیف')->policy('marketing');
     */
    public function policy(string $type): self
    {
        /** @var MessagePolicyRegistry $registry */
        $registry = app(MessagePolicyRegistry::class);
        $policy = $registry->get($type);

        if ($policy->failover !== null) {
            $this->failoverDrivers = $policy->failover;
            $this->driver = null;
        } elseif ($policy->driver !== null) {
            $this->driver = $policy->driver;
            $this->failoverDrivers = null;
        }

        if ($policy->queue !== null) {
            $this->queue = $policy->queue;
        }

        $this->options = array_merge($this->options, [
            'channel' => $policy->channel(),
            'message_type' => $policy->type,
            'rate_limit_channel' => $policy->channel(),
        ]);

        return $this;
    }

    public function content(string $content): self
    {
        $this->content = $content;
        $this->usingTemplate = false;

        return $this;
    }

    /**
     * Send a template / OTP message instead of plain text.
     *
     * Pass a numeric provider template id, or a name from config('smsapi.templates').
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function sendTemplate(int|string $templateId, array $parameters = []): self
    {
        $this->usingTemplate = true;
        $this->templateId = $templateId;
        $this->parameters = $parameters;
        $this->content = null;

        return $this;
    }

    /**
     * Alias for sendTemplate() using a registry template name.
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function template(string $name, array $parameters = []): self
    {
        return $this->sendTemplate($name, $parameters);
    }

    /**
     * Preferred channels for this OTP/message (informational for notifications).
     *
     * Use with Notification::via() via Sms::otpChannels() / $message->getChannels(),
     * and toMailMessage() when mail is included.
     *
     * @param  string|array<int, string>  $channels  sms|mail|both or ['sms','mail']
     */
    public function via(string|array $channels): self
    {
        $this->channels = TemplateSend::normalizeChannels($channels);

        return $this;
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

    /**
     * Build a MailMessage for notification toMail() from verify/template data.
     */
    public function toMailMessage(): MailMessage
    {
        if (! $this->usingTemplate || $this->templateId === null) {
            $body = $this->content ?? (string) config('smsapi.otp.mail.text', 'کد تایید شما: :CODE');

            return (new MailMessage)
                ->subject((string) config('smsapi.otp.mail.subject', 'کد تایید شما'))
                ->line($body);
        }

        $templateName = is_string($this->templateId) ? $this->templateId : (string) $this->templateId;
        $definition = null;

        if (is_string($this->templateId) && app()->bound(TemplateRegistry::class)) {
            $registry = app(TemplateRegistry::class);

            if ($registry->has($this->templateId)) {
                $definition = $registry->get($this->templateId);
            }
        }

        /** @var TemplateMailer $mailer */
        $mailer = app(TemplateMailer::class);

        return $mailer->toMailMessage($templateName, $this->parameters, $definition);
    }

    public function to(string $mobile): self
    {
        $this->to = $mobile;

        return $this;
    }

    /**
     * Use a single named driver / connection for this notification.
     */
    public function driver(string $driver): self
    {
        $this->driver = $driver;
        $this->failoverDrivers = null;

        return $this;
    }

    /**
     * Alias for driver() — named connection / account.
     */
    public function connection(string $connection): self
    {
        return $this->driver($connection);
    }

    /**
     * Try providers in order for this notification only.
     *
     * @param  array<int, string>  $drivers
     */
    public function failover(array $drivers): self
    {
        $this->failoverDrivers = array_values($drivers);
        $this->driver = null;

        return $this;
    }

    /**
     * Queue this notification SMS on a named/dedicated queue (e.g. otp).
     * Null keeps an existing policy queue, otherwise falls back to config.
     */
    public function queue(?string $name = null): self
    {
        $this->queue = $name
            ?? $this->queue
            ?? (string) config('smsapi.queue.default', 'default');

        return $this;
    }

    public function delay(\DateTimeInterface|\DateInterval|int $delay): self
    {
        $this->queueDelay = $delay;

        return $this;
    }

    public function tries(int $tries): self
    {
        $this->tries = max(1, $tries);

        return $this;
    }

    /**
     * @param  array<int, int>  $backoff
     */
    public function backoff(array $backoff): self
    {
        $this->backoff = array_values(array_map('intval', $backoff));

        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->timeout = max(1, $seconds);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    public function lineNumber(int|string $lineNumber): self
    {
        $this->options['lineNumber'] = $lineNumber;

        return $this;
    }

    public function sendDateTime(?int $timestamp): self
    {
        $this->options['sendDateTime'] = $timestamp;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getTo(): ?string
    {
        return $this->to;
    }

    public function isTemplate(): bool
    {
        return $this->usingTemplate;
    }

    public function getTemplateId(): int|string|null
    {
        return $this->templateId;
    }

    /**
     * @return array<string, string>|array<int, array{name: string, value: string}>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getDriver(): ?string
    {
        return $this->driver;
    }

    /**
     * @return array<int, string>|null
     */
    public function getFailoverDrivers(): ?array
    {
        return $this->failoverDrivers;
    }

    public function getQueue(): ?string
    {
        return $this->queue;
    }

    public function getQueueDelay(): \DateTimeInterface|\DateInterval|int|null
    {
        return $this->queueDelay;
    }

    public function getTries(): ?int
    {
        return $this->tries;
    }

    /**
     * @return array<int, int>
     */
    public function getBackoff(): array
    {
        return $this->backoff;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    /**
     * @return array<int, string>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    public function wantsMail(): bool
    {
        return in_array('mail', $this->channels, true);
    }

    public function wantsSms(): bool
    {
        return in_array('sms', $this->channels, true);
    }
}
