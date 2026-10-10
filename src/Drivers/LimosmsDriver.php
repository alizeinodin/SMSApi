<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Drivers\Concerns\FlattensTemplateParameters;
use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;

/**
 * Limo SMS / لیمواس‌ام‌اس REST API.
 *
 * @see https://limosms.com/
 */
class LimosmsDriver extends AbstractDriver
{
    use FlattensTemplateParameters;
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
        return 'limosms';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toLimosmsResponse($this->limosmsRequest('POST', 'api/send', [
            'Message' => $message,
            'Mobiles' => array_values($mobiles),
            'Sender' => $this->resolveSenderLine($options),
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $values = $this->flattenTemplateParameters($parameters);

        return $this->toLimosmsResponse($this->limosmsRequest('POST', 'api/sendPattern', [
            'Mobile' => $mobile,
            'PatternId' => (string) $templateId,
            'PatternValues' => $values,
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toLimosmsResponse($this->limosmsRequest('GET', 'api/credit'));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.limosms.com'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'ApiKey' => (string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function limosmsRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Limosms api_key is not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && (bool) ($decoded['Success'] ?? $decoded['success'] ?? $httpStatus < 400),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toLimosmsResponse(array $raw): SendResult
    {
        return new SendResult(
            success: (bool) ($raw['Success'] ?? $raw['success'] ?? true),
            message: (string) ($raw['Message'] ?? $raw['message'] ?? 'OK'),
            data: $raw['Data'] ?? $raw['data'] ?? null,
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['Status'] ?? 200),
        );
    }
}
