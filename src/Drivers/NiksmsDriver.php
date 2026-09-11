<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use GuzzleHttp\Client;

/**
 * Nik SMS / نیک اس‌ام‌اس REST API v2.
 *
 * @see https://niksms.com/
 */
class NiksmsDriver extends AbstractDriver
{
    use HasProviderIdentity;
    use ResolvesSenderLine;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function driverConfig(): array
    {
        return $this->config;
    }

    protected function defaultProviderName(): string
    {
        return 'niksms';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        if (count($mobiles) === 1) {
            return $this->toNiksmsResponse($this->niksmsRequest('POST', 'send/one', [
                'message' => $message,
                'recipient' => $mobiles[0],
                'senderNumber' => $this->resolveSenderLine($options, 'senderNumber/line_number'),
            ]));
        }

        return $this->bulk($message, $mobiles, $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toNiksmsResponse($this->niksmsRequest('POST', 'send/group', [
            'message' => $message,
            'recipients' => json_encode(array_values($mobiles), JSON_UNESCAPED_UNICODE),
            'senderNumber' => $this->resolveSenderLine($options, 'senderNumber/line_number'),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    public function getCredit(): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    public function cancelScheduled(string $packId): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://niksms.com/api/v2'), '/') . '/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function niksmsRequest(string $method, string $uri, array $payload): array
    {
        $username = (string) $this->config('username', '');
        $password = (string) $this->config('password', '');

        if ($username === '' || $password === '') {
            throw new SmsApiException('Niksms username/password are not configured.');
        }

        $form = array_merge([
            'username' => $username,
            'password' => $password,
        ], $payload);

        try {
            $response = $this->client->request($method, ltrim($uri, '/'), ['form_params' => $form]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new SmsApiException(
                sprintf('HTTP request via [%s] failed: %s', $this->getName(), $e->getMessage()),
                (int) $e->getCode(),
                previous: $e,
            );
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new SmsApiException(
                sprintf('Invalid JSON response from [%s].', $this->getName()),
                $response->getStatusCode(),
                ['raw' => $body],
            );
        }

        if ($response->getStatusCode() >= 400) {
            throw new SmsApiException(
                (string) ($decoded['Message'] ?? 'Niksms request failed.'),
                $response->getStatusCode(),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toNiksmsResponse(array $raw): SendResult
    {
        $status = (int) ($raw['Status'] ?? $raw['status'] ?? 1);

        return new SendResult(
            success: $status === 1,
            message: (string) ($raw['Message'] ?? $raw['message'] ?? 'OK'),
            data: $raw['Data'] ?? $raw['data'] ?? $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: $status,
        );
    }
}
