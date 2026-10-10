<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\DelegatesSmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Logging\SmsLogger;
use Throwable;

/**
 * Decorator that persists outbound operations to sms_logs.
 */
class PersistingLogDriver implements SmsDriver
{
    use DelegatesSmsDriver;

    public function __construct(
        private MessageDriver $driver,
        private SmsLogger $logger,
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
            $message,
        );
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->run(
            'bulk',
            fn () => $this->driver->bulk($message, $recipients, $options),
            $recipients,
            $message,
        );
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->run(
            'sendMapped',
            fn () => $this->smsDriver('sendMapped')->sendMapped($messages, $mobiles, $options),
            $mobiles,
            implode(' | ', array_slice($messages, 0, 3)),
        );
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->run(
            'sendTemplate',
            fn () => $this->smsDriver('sendTemplate')->sendTemplate($mobile, $templateId, $parameters, $options),
            $mobile,
            null,
            [
                'template_id' => $templateId,
                'template' => $options['template'] ?? null,
                'parameters' => $parameters,
            ],
        );
    }

    /**
     * @param  callable(): SendResult  $callback
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $context
     */
    private function run(
        string $operation,
        callable $callback,
        string|array|null $mobiles = null,
        ?string $content = null,
        array $context = [],
    ): SendResult {
        $context['driver'] = $this->driver->getName();

        try {
            $response = $callback();
            try {
                $this->logger->log($operation, $response, $mobiles, $content, $context);
            } catch (Throwable) {
                // Never break delivery because logging failed.
            }

            return $response;
        } catch (Throwable $e) {
            try {
                $this->logger->log($operation, $e, $mobiles, $content, $context);
            } catch (Throwable) {
                //
            }

            throw $e;
        }
    }
}
