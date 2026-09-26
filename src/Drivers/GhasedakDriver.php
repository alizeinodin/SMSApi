<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/** @see https://ghasedak.me/docs */
class GhasedakDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'ghasedak');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($recipients);

        return $this->map($this->request('POST', 'sms/send/simple', [
            'receptor' => implode(',', $mobiles),
            'message' => $message,
            'linenumber' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $payload = [
            'receptor' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'type' => 1,
            'template' => (string) $templateId,
        ];

        $i = 1;
        foreach ($parameters as $key => $value) {
            $val = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
            $payload['param'.$i] = $val;
            $i++;
        }

        return $this->map($this->request('POST', 'verification/send/simple', $payload));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.ghasedak.me/v2'),
            ['apikey' => (string) ($config['api_key'] ?? '')],
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
            throw new SmsApiException('Ghasedak api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded): bool => (int) ($decoded['result']['code'] ?? 0) === 200,
            bodyMode: 'form',
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse(
            $raw,
            success: (int) ($raw['result']['code'] ?? 0) === 200,
            message: (string) ($raw['result']['message'] ?? 'OK'),
        );
    }
}
