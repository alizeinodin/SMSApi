<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;

/**
 * Magfa (مگفا) HTTP SMS API v2.
 *
 * @see https://messaging.magfa.com/ui/?public/wiki/api/http_v2
 */
class MagfaDriver extends AbstractDriver
{
    use Concerns\HasProviderIdentity;

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
        return 'magfa';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toMagfaResponse($this->magfaRequest('POST', 'send', [
            'senders' => [$this->resolveSender($options)],
            'recipients' => array_values($mobiles),
            'messages' => array_fill(0, count($mobiles), $message),
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toMagfaResponse($this->magfaRequest('POST', 'send', [
            'senders' => array_fill(0, count($mobiles), $this->resolveSender($options)),
            'recipients' => array_values($mobiles),
            'messages' => array_values($messages),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    public function getCredit(): SendResult
    {
        return $this->toMagfaResponse($this->magfaRequest('GET', 'balance'), credit: true);
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
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $domain = (string) ($config['domain'] ?? 'magfa');

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://sms.magfa.com/api/http/sms/v2'), '/') . '/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'auth' => [$username.'/'.$domain, $password],
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Accept-Charset' => 'utf-8',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function magfaRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('username', '') === '' || (string) $this->config('password', '') === '') {
            throw new SmsApiException('Magfa username/password are not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && (int) ($decoded['status'] ?? -1) === 0,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toMagfaResponse(array $raw, bool $credit = false): SendResult
    {
        $data = $credit
            ? ['balance' => $raw['balance'] ?? $raw['credit'] ?? null]
            : ($raw['messages'][0] ?? $raw);

        return new SendResult(
            success: (int) ($raw['status'] ?? 0) === 0,
            message: 'OK',
            data: is_array($data) ? $data : null,
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['status'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveSender(array $options): string
    {
        $sender = $options['sender'] ?? $options['lineNumber'] ?? $this->lineNumber;

        if ($sender === null || $sender === '') {
            throw new SmsApiException('Magfa sender/line_number is required.');
        }

        return (string) $sender;
    }
}
