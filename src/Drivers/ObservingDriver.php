<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\DelegatesSmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Events\SmsFailed;
use Alizeinodin\SmsApi\Events\SmsSending;
use Alizeinodin\SmsApi\Events\SmsSent;
use Illuminate\Contracts\Events\Dispatcher;
use Throwable;

/**
 * Decorator that fires SmsSending / SmsSent / SmsFailed around outbound operations.
 */
class ObservingDriver implements SmsDriver
{
    use DelegatesSmsDriver;

    public function __construct(
        private MessageDriver $driver,
        private Dispatcher $events,
    ) {}

    protected function delegate(): MessageDriver
    {
        return $this->driver;
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        return $this->run(
            'send',
            fn () => $this->driver->send($recipients, $message, $options),
            $recipients,
            $options,
        );
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->run(
            'bulk',
            fn () => $this->driver->bulk($message, $recipients, $options),
            $recipients,
            $options,
        );
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->run(
            'sendMapped',
            fn () => $this->smsDriver('sendMapped')->sendMapped($messages, $mobiles, $options),
            $mobiles,
            $options,
        );
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->run(
            'sendTemplate',
            fn () => $this->smsDriver('sendTemplate')->sendTemplate($mobile, $templateId, $parameters, $options),
            $mobile,
            $options,
            $templateId,
        );
    }

    /**
     * @param  callable(): SendResult  $callback
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $options
     */
    private function run(
        string $operation,
        callable $callback,
        string|array|null $mobiles = null,
        array $options = [],
        int|string|null $templateId = null,
    ): SendResult {
        $driver = $this->driver->getName();
        $template = $this->stringOption($options, 'template');
        $messageType = $this->stringOption($options, 'message_type');
        $channel = $this->resolveChannel($options);
        $resolvedTemplateId = $templateId ?? $options['template_id'] ?? null;

        if (! is_int($resolvedTemplateId) && ! is_string($resolvedTemplateId)) {
            $resolvedTemplateId = null;
        }

        if ($template === null && is_string($resolvedTemplateId)) {
            $template = $resolvedTemplateId;
        }

        $this->safeDispatch(new SmsSending(
            operation: $operation,
            driver: $driver,
            mobiles: $mobiles,
            templateId: $resolvedTemplateId,
            template: $template,
            messageType: $messageType,
            channel: $channel,
            options: $options,
        ));

        try {
            $response = $callback();

            $this->safeDispatch(new SmsSent(
                operation: $operation,
                driver: $response->driver !== '' ? $response->driver : $driver,
                response: $response,
                mobiles: $mobiles,
                templateId: $resolvedTemplateId,
                template: $template,
                messageType: $messageType,
                channel: $channel,
                options: $options,
            ));

            return $response;
        } catch (Throwable $e) {
            $this->safeDispatch(new SmsFailed(
                operation: $operation,
                driver: $driver,
                exception: $e,
                mobiles: $mobiles,
                templateId: $resolvedTemplateId,
                template: $template,
                messageType: $messageType,
                channel: $channel,
                options: $options,
            ));

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveChannel(array $options): ?string
    {
        foreach (['channel', 'rate_limit_channel', 'queue'] as $key) {
            $value = $this->stringOption($options, $key);
            if ($value !== null) {
                return $value;
            }
        }

        return $this->driver->getChannel();
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function stringOption(array $options, string $key): ?string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function safeDispatch(object $event): void
    {
        try {
            $this->events->dispatch($event);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
