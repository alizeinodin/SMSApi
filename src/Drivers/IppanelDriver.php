<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class IppanelDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'ippanel');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));

        return $this->map($this->request('POST', 'sms/send/webservice/single', [
            'sender' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'recipient' => $mobiles,
            'message' => $message,
            'description' => [
                'summary' => (string) ($options['summary'] ?? 'smsapi'),
                'count_recipient' => (string) count($mobiles),
            ],
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->map($this->request('GET', 'sms/accounting/credit/show'));
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

        return $this->map($this->request('POST', 'sms/pattern/normal/send', [
            'code' => (string) $templateId,
            'sender' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'recipient' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'variable' => $values,
        ]));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $apiKey = (string) ($config['api_key'] ?? '');

        // Official IPPanel SDK uses header: apikey: {key}
        // @see https://github.com/IPPanel/php-rest-sdk
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api2.ippanel.com/api/v1'),
            [
                'apikey' => $apiKey,
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
            throw new SmsApiException('IPPanel api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded, int $status): bool => $status < 400
                && (string) ($decoded['status'] ?? 'OK') !== 'ERROR',
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse($raw, success: true, message: (string) ($raw['message'] ?? 'OK'));
    }
}
