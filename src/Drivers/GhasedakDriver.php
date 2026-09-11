<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;

/**
 * @see https://ghasedak.me/docs
 */
class GhasedakDriver extends AbstractDriver
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
        return 'ghasedak';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        if (count($mobiles) === 1) {
            return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/send/simple', [
                'message' => $message,
                'receptor' => $mobiles[0],
                'linenumber' => $this->resolveLine($options),
            ]));
        }

        return $this->bulk($message, $mobiles, $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/send/bulk', [
            'message' => $message,
            'receptors' => array_values($mobiles),
            'linenumber' => $this->resolveLine($options),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $payload = [
            'receptor' => $mobile,
            'type' => '1',
            'template' => (string) $templateId,
        ];

        $index = 1;
        foreach ($this->flattenParameters($parameters) as $value) {
            $payload['param'.$index] = $value;
            $index++;
        }

        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'verification/send/simple', $payload));
    }

    public function getCredit(): SendResult
    {
        return $this->toGhasedakResponse($this->ghasedakRequest('GET', 'account/info'));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.ghasedak.me/v2'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'apikey' => (string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ghasedakRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Ghasedak api_key is not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && (int) ($decoded['result']['code'] ?? 200) === 200,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toGhasedakResponse(array $raw): SendResult
    {
        $code = (int) ($raw['result']['code'] ?? 0);

        return new SendResult(
            success: $code === 200,
            message: (string) ($raw['result']['message'] ?? 'OK'),
            data: $raw['items'][0] ?? $raw['result'] ?? null,
            driver: $this->getName(),
            raw: $raw,
            statusCode: $code,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveLine(array $options): string
    {
        $line = $options['lineNumber'] ?? $this->lineNumber;

        if ($line === null || $line === '') {
            throw new SmsApiException('Ghasedak linenumber is required in config or options.lineNumber.');
        }

        return (string) $line;
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return list<string>
     */
    private function flattenParameters(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            return array_map(static fn (array $item): string => (string) ($item['value'] ?? ''), $parameters);
        }

        return array_map('strval', array_values($parameters));
    }
}
