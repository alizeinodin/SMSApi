<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class MedianaDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'mediana');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));

        return $this->map($this->request('POST', 'sms/send', [
            'receptor' => $mobiles,
            'sender' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'message' => $message,
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
            (string) ($config['base_url'] ?? 'https://api.mediana.ir'),
            ['Authorization' => 'Bearer '.(string) ($config['api_key'] ?? '')],
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
            throw new SmsApiException('Mediana api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded, int $status): bool => $status < 400,
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse($raw, success: true, message: (string) ($raw['message'] ?? 'OK'));
    }
}
