<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class FarazsmsDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'farazsms');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($recipients);

        return $this->map($this->request('POST', 'v1/messages', [
            'originator' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'recipients' => $mobiles,
            'message' => $message,
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $values = [];
        foreach ($parameters as $key => $value) {
            $values[is_array($value) ? (string) ($value['name'] ?? $key) : (string) $key] =
                is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
        }

        return $this->map($this->request('POST', 'v1/messages/pattern', [
            'pattern_code' => (string) $templateId,
            'originator' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'recipient' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'values' => $values,
        ]));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.iranpayamak.com'),
            ['Api-Key' => (string) ($config['api_key'] ?? '')],
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
            throw new SmsApiException('Farazsms api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded, int $status): bool => $status < 400
                && in_array(($decoded['status'] ?? 'success'), ['success', 'ok', 200, '200'], true),
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse($raw, success: true, message: (string) ($raw['message'] ?? 'OK'));
    }
}
