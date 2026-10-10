<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/** @see https://kavenegar.com/rest.html */
class KavenegarDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'kavenegar');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($recipients);

        return $this->map($this->request('POST', 'sms/send.json', [
            'receptor' => implode(',', $mobiles),
            'message' => $message,
            'sender' => $this->resolveSender($options),
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));
        $sender = $this->resolveSender($options);

        return $this->map($this->request('POST', 'sms/sendarray.json', [
            'receptor' => json_encode($mobiles, JSON_UNESCAPED_UNICODE),
            'message' => json_encode(array_fill(0, count($mobiles), $message), JSON_UNESCAPED_UNICODE),
            'sender' => json_encode(array_fill(0, count($mobiles), $sender), JSON_UNESCAPED_UNICODE),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $query = [
            'receptor' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'template' => (string) $templateId,
        ];

        $tokenIndex = 0;
        foreach ($parameters as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $query[$this->mapToken((string) $value['name'], $tokenIndex)] = (string) $value['value'];
            } else {
                $query[$this->mapToken((string) $key, $tokenIndex)] = (string) $value;
            }
            $tokenIndex++;
        }

        return $this->map($this->request('GET', 'verify/lookup.json', [], $query));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $apiKey = rawurlencode((string) ($config['api_key'] ?? ''));

        return $this->buildClient(
            rtrim((string) ($config['base_url'] ?? 'https://api.kavenegar.com/v1'), '/').'/'.$apiKey,
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = [], array $query = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Kavenegar api_key is not configured.');
        }

        try {
            return $this->httpRequest(
                $method,
                $uri,
                $payload,
                $query,
                fn (array $decoded): bool => (int) ($decoded['return']['status'] ?? 0) === 200,
                bodyMode: 'form',
            );
        } catch (SmsApiException $e) {
            $context = $e->context ?? [];
            $message = (string) ($context['return']['message'] ?? $e->getMessage());
            $code = (int) ($context['return']['status'] ?? $e->getCode());

            throw new SmsApiException($message, $code, $context, $e);
        }
    }

    /** @param  array<string, mixed>  $options */
    protected function resolveSender(array $options): string
    {
        $sender = $options['sender'] ?? $options['lineNumber'] ?? $this->lineNumber;

        if ($sender === null || $sender === '') {
            throw new SmsApiException('Kavenegar sender/line_number is required.');
        }

        return (string) $sender;
    }

    protected function mapToken(string $name, int $index): string
    {
        $key = strtolower($name);

        return match ($key) {
            'token', 'code', 'otp' => 'token',
            'token2', 'token_2' => 'token2',
            'token3', 'token_3' => 'token3',
            default => $index === 0 ? 'token' : 'token'.($index + 1),
        };
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        $entries = $raw['entries'] ?? null;
        $messageId = null;

        if (is_array($entries) && isset($entries[0]['messageid'])) {
            $messageId = (string) $entries[0]['messageid'];
        }

        return $this->toResponse(
            $raw,
            success: (int) ($raw['return']['status'] ?? 0) === 200,
            message: (string) ($raw['return']['message'] ?? 'OK'),
            messageId: $messageId,
        );
    }
}
