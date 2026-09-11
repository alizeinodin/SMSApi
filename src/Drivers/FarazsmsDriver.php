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
 * Faraz SMS / فراز اس‌ام‌اس (IranPayamak REST API).
 *
 * @see https://docs.farazsms.com/
 */
class FarazsmsDriver extends AbstractDriver
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
        return 'farazsms';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toFarazResponse($this->farazRequest('POST', 'ws/v1/sms/simple', [
            'text' => $message,
            'recipients' => array_values($mobiles),
            'line_number' => $this->resolveSenderLine($options),
            'number_format' => (string) ($options['number_format'] ?? 'english'),
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $attributes = [];
        foreach ($this->flattenTemplateParameters($parameters) as $index => $value) {
            $attributes['param'.($index + 1)] = $value;
        }

        if (isset($parameters['CODE']) || isset($parameters['code'])) {
            $attributes['code'] = (string) ($parameters['CODE'] ?? $parameters['code']);
        }

        return $this->toFarazResponse($this->farazRequest('POST', 'ws/v1/sms/pattern', [
            'code' => (string) $templateId,
            'recipient' => $mobile,
            'attributes' => $attributes !== [] ? $attributes : (object) [],
            'line_number' => $this->resolveSenderLine($options),
            'number_format' => (string) ($options['number_format'] ?? 'english'),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/wallet/balance'));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.iranpayamak.com'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Api-Key' => (string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function farazRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Farazsms api_key is not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && strtolower((string) ($decoded['status'] ?? '')) === 'success',
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toFarazResponse(array $raw): SendResult
    {
        return new SendResult(
            success: strtolower((string) ($raw['status'] ?? '')) === 'success',
            message: (string) ($raw['message'] ?? 'OK'),
            data: $raw['data'] ?? null,
            driver: $this->getName(),
            raw: $raw,
            statusCode: 200,
        );
    }
}
