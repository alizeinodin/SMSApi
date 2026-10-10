<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * Ghasedak v2 REST API (official SDK: ghasedakapi/ghasedak-php).
 *
 * @see https://github.com/ghasedakapi/ghasedak-php
 */
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
        $line = $options['lineNumber'] ?? $this->lineNumber;

        $payload = [
            'receptor' => implode(',', $mobiles),
            'message' => $message,
        ];

        // Official SDK allows null linenumber when a dedicated line exists.
        if ($line !== null && $line !== '') {
            $payload['linenumber'] = (string) $line;
        }

        if (isset($options['senddate'])) {
            $payload['senddate'] = $options['senddate'];
        }
        if (isset($options['checkid'])) {
            $payload['checkid'] = $options['checkid'];
        }

        return $this->map($this->request('POST', 'sms/send/simple', $payload));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));
        $line = $options['lineNumber'] ?? $this->lineNumber;

        return $this->map($this->request('POST', 'sms/send/bulk', [
            'receptor' => implode(',', $mobiles),
            'linenumber' => $line !== null && $line !== '' ? (string) $line : null,
            'message' => $message,
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $payload = [
            'receptor' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'type' => (int) ($options['type'] ?? 1),
            'template' => (string) $templateId,
        ];

        $i = 1;
        foreach ($parameters as $value) {
            $payload['param'.$i] = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
            $i++;
            if ($i > 10) {
                break;
            }
        }

        return $this->map($this->request('POST', 'verification/send/simple', $payload));
    }

    /**
     * Account info — useful connectivity check without sending SMS.
     *
     * @see GhasedakApi::AccountInfo()
     */
    public function accountInfo(): SendResult
    {
        return $this->map($this->request('GET', 'account/info'));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.ghasedak.me/v2'),
            [
                'apikey' => (string) ($config['api_key'] ?? ''),
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
                'charset' => 'utf-8',
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
            throw new SmsApiException('Ghasedak api_key is not configured.');
        }

        // Official SDK appends ?agent=core-* on every call.
        $query = ['agent' => (string) ($this->config('agent', 'php-smsapi'))];

        try {
            return $this->httpRequest(
                $method,
                $uri,
                array_filter($payload, fn ($v) => $v !== null),
                $query,
                isSuccessful: fn (array $decoded): bool => (int) ($decoded['result']['code'] ?? 0) === 200,
                bodyMode: 'form',
            );
        } catch (SmsApiException $e) {
            $context = $e->context ?? [];
            $message = (string) ($context['result']['message'] ?? $e->getMessage());
            $code = (int) ($context['result']['code'] ?? $e->getCode());

            throw new SmsApiException($message, $code, $context, $e);
        }
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
