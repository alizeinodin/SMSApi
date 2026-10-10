<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\Contracts\MessengerDriver;
use Alizeinodin\SmsApi\Drivers\AbstractHttpDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\HasMessageChannel;
use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Shared helpers for messenger drivers (JSON + form POST).
 */
abstract class AbstractMessengerDriver extends AbstractHttpDriver implements MessengerDriver
{
    use HasMessageChannel;
    use HasProviderIdentity;

    protected function driverConfig(): array
    {
        return $this->config;
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $last = null;

        foreach ($recipients as $recipient) {
            $last = $this->send($recipient, $message, $options);
        }

        if ($last === null) {
            throw new SmsApiException('At least one recipient is required.');
        }

        return $last;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveRecipient(string|array $recipients, array $options): string
    {
        if (isset($options['chat_id']) && is_string($options['chat_id']) && $options['chat_id'] !== '') {
            return $options['chat_id'];
        }

        if (is_array($recipients)) {
            $recipients = $recipients[0] ?? '';
        }

        return (string) $recipients;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function requestForm(
        string $method,
        string $uri,
        array $payload = [],
        ?callable $isSuccessful = null,
    ): array {
        try {
            $response = $this->client->request($method, ltrim($uri, '/'), [
                'form_params' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new SmsApiException(
                sprintf('HTTP request via [%s] failed: %s', $this->getName(), $e->getMessage()),
                (int) $e->getCode(),
                previous: $e,
            );
        }

        return $this->decodeResponse($response->getStatusCode(), (string) $response->getBody(), $isSuccessful);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeResponse(int $httpStatus, string $body, ?callable $isSuccessful = null): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new SmsApiException(
                sprintf('Invalid JSON response from [%s].', $this->getName()),
                $httpStatus,
                ['raw' => $body],
            );
        }

        $successful = $isSuccessful
            ? (bool) $isSuccessful($decoded, $httpStatus)
            : $httpStatus < 400;

        if (! $successful) {
            throw new SmsApiException(
                (string) ($decoded['message'] ?? $decoded['description'] ?? sprintf('Unknown error from [%s].', $this->getName())),
                (int) ($decoded['status'] ?? $httpStatus),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toMessengerResponse(array $raw, mixed $messageId = null, bool $success = true): SendResult
    {
        return new SendResult(
            success: $success,
            message: 'OK',
            data: ['message_id' => $messageId],
            driver: $this->getName(),
            raw: $raw,
            statusCode: 200,
        );
    }
}
