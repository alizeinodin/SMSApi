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
 * Mediana SMS REST API.
 *
 * @see https://mediana.ir/
 */
class MedianaDriver extends AbstractDriver
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
        return 'mediana';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toMedianaResponse($this->medianaRequest('POST', 'sms/v1/send', [
            'text' => $message,
            'recipients' => array_values($mobiles),
            'sender' => $this->resolveSenderLine($options),
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->toMedianaResponse($this->medianaRequest('POST', 'sms/v1/send/pattern', [
            'recipient' => $mobile,
            'patternCode' => (string) $templateId,
            'parameters' => $this->flattenTemplateParameters($parameters),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toMedianaResponse($this->medianaRequest('GET', 'sms/v1/credit'));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.mediana.ir'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.(string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function medianaRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Mediana api_key is not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && ($decoded['success'] ?? $decoded['Success'] ?? true) !== false,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toMedianaResponse(array $raw): SendResult
    {
        $success = ($raw['success'] ?? $raw['Success'] ?? true) !== false;

        return new SendResult(
            success: $success,
            message: (string) ($raw['message'] ?? 'OK'),
            data: $raw['data'] ?? $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: 200,
        );
    }
}
