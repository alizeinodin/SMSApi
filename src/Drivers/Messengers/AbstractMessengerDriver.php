<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\Drivers\AbstractDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

abstract class AbstractMessengerDriver extends AbstractDriver
{
    public function getChannel(): string
    {
        return (string) ($this->config['channel'] ?? $this->getName());
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $last = null;
        foreach ($recipients as $recipient) {
            $last = $this->send($recipient, $message, $options);
        }

        return $last ?? $this->toResponse(['message' => 'No recipients'], success: false, message: 'No recipients');
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $text = is_string($templateId) ? $templateId : 'template:'.$templateId;
        foreach ($parameters as $key => $value) {
            $val = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
            $text .= "\n{$key}={$val}";
        }

        return $this->send($mobile, $text, $options);
    }

    protected function ensureConfigured(string $key, string $label): void
    {
        if ((string) $this->config($key, '') === '') {
            throw new SmsApiException(sprintf('[%s] %s is not configured.', $this->getName(), $label));
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toMessengerResponse(array $raw, mixed $messageId = null, bool $success = true): SendResult
    {
        return $this->toResponse(
            $raw,
            success: $success,
            message: (string) ($raw['description'] ?? $raw['message'] ?? 'OK'),
            messageId: $messageId !== null ? (string) $messageId : null,
        );
    }
}
