<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * LimoSMS REST API.
 *
 * @see https://api.limosms.com/
 */
class LimosmsDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'limosms');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));

        return $this->map($this->request('POST', 'api/sendsms', [
            'MobileNumber' => $mobiles,
            'Message' => $message,
            'SenderNumber' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.limosms.com'),
            [
                'ApiKey' => (string) ($config['api_key'] ?? ''),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Limosms api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded, int $status): bool => $status < 400
                && ($decoded['Success'] ?? false) === true,
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        $ids = $raw['MessageId'] ?? null;
        $messageId = is_array($ids) ? (string) ($ids[0] ?? '') : (isset($ids) ? (string) $ids : null);

        return $this->toResponse(
            $raw,
            success: ($raw['Success'] ?? false) === true,
            message: (string) ($raw['Message'] ?? 'OK'),
            messageId: $messageId !== '' ? $messageId : null,
        );
    }
}
