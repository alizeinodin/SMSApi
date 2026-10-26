<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

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

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));
        $messages = array_values(array_map('strval', $messages));

        if (count($messages) !== count($mobiles)) {
            throw new SmsApiException('Niksms peer-to-peer send needs one message per recipient.');
        }

        return $this->toNiksmsResponse($this->niksmsRequest('POST', 'send/ptp', [
            'message' => json_encode($messages, JSON_UNESCAPED_UNICODE),
            'recipients' => json_encode($mobiles, JSON_UNESCAPED_UNICODE),
            'senderNumber' => $this->resolveSenderLine($options, 'senderNumber/line_number'),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    public function getCredit(): SendResult
    {
        return $this->toNiksmsResponse($this->niksmsRequest('POST', 'GetCredit', []));
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toNiksmsResponse($this->niksmsRequest('POST', 'Status', [
            'nikIds' => (string) $messageId,
        ]));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://niksms.com/api/v2'), '/').'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
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
        } catch (GuzzleException $e) {
            throw $this->transportException($e);
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
        $hasStatus = array_key_exists('Status', $raw) || array_key_exists('status', $raw);
        $status = $hasStatus ? (int) ($raw['Status'] ?? $raw['status']) : 0;

        return new SendResult(
            success: $hasStatus && $status === 1,
            message: (string) ($raw['Message'] ?? $raw['message'] ?? 'OK'),
            data: $raw['Data'] ?? $raw['data'] ?? $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: $status,
        );
    }
}
