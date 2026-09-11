<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use GuzzleHttp\Client;

/**
 * IPPanel (formerly Farapayamak panel API v1).
 *
 * @see https://ippanelcom.github.io/EdgeDocs/
 */
class IppanelDriver extends AbstractDriver
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
        return 'ippanel';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        if ($mobiles === []) {
            throw new SmsApiException('At least one mobile number is required.');
        }

        if (count($mobiles) === 1) {
            return $this->toIppanelResponse($this->ippanelRequest('POST', 'sms/send/webservice/single', [
                'originator' => $this->resolveOriginator($options),
                'recipient' => $this->toInternational($mobiles[0]),
                'message' => $message,
            ]));
        }

        return $this->bulk($message, $mobiles, $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_map(fn (string $m): string => $this->toInternational($m), $this->normalizeMobiles($mobiles));

        return $this->toIppanelResponse($this->ippanelRequest('POST', 'sms/send/webservice', [
            'originator' => $this->resolveOriginator($options),
            'recipients' => array_values($mobiles),
            'message' => $message,
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->toIppanelResponse($this->ippanelRequest('POST', 'sms/pattern/normal/send', [
            'code' => (string) $templateId,
            'sender' => $this->resolveOriginator($options),
            'recipient' => $this->toInternational($mobile),
            'variable' => $this->mapPatternVariables($parameters),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'user'));
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
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api2.ippanel.com/api/v1'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'AccessKey '.(string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ippanelRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('IPPanel api_key is not configured.');
        }

        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && ($decoded['status'] ?? 'ok') !== 'error',
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toIppanelResponse(array $raw): SendResult
    {
        return new SendResult(
            success: ($raw['status'] ?? 'ok') !== 'error',
            message: (string) ($raw['message'] ?? 'OK'),
            data: $raw['data'] ?? $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: 200,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveOriginator(array $options): string
    {
        $originator = $options['originator'] ?? $options['lineNumber'] ?? $this->lineNumber;

        if ($originator === null || $originator === '') {
            throw new SmsApiException('IPPanel originator/line_number is required.');
        }

        return (string) $originator;
    }

    private function toInternational(string $mobile): string
    {
        return PhoneNumber::normalize($mobile, [
            'enabled' => true,
            'format' => PhoneNumber::FORMAT_DIGITS,
            'country_code' => '98',
            'strict' => false,
        ]);
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return array<string, string>
     */
    private function mapPatternVariables(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            $mapped = [];
            foreach ($parameters as $item) {
                $mapped[(string) ($item['name'] ?? '')] = (string) ($item['value'] ?? '');
            }

            return $mapped;
        }

        return array_map('strval', $parameters);
    }
}
