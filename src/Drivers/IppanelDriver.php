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
        $mobiles = array_map(
            fn (string $m): string => $this->toInternational($m),
            $this->normalizeMobiles($mobiles),
        );

        if ($mobiles === []) {
            throw new SmsApiException('At least one mobile number is required.');
        }

        $originator = $this->resolveOriginator($options);

        // Official SDK posts sender + recipient[] to the single endpoint for any count.
        return $this->toIppanelResponse($this->ippanelRequest('POST', 'sms/send/webservice/single', [
            'sender' => $originator,
            'recipient' => array_values($mobiles),
            'message' => $message,
            'description' => [
                'summary' => (string) ($options['summary'] ?? 'smsapi'),
                'count_recipient' => (string) count($mobiles),
            ],
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $mobile = $this->toInternational($this->normalizeMobiles($mobile)[0] ?? $mobile);

        return $this->toIppanelResponse($this->ippanelRequest('POST', 'sms/pattern/normal/send', [
            'code' => (string) $templateId,
            'sender' => $this->resolveOriginator($options),
            'recipient' => $mobile,
            'variable' => $this->mapPatternVariables($parameters),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'sms/accounting/credit/show'));
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'sms/message/all', [
            'message_id' => $messageId,
        ]));
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'inbox', [
            'page' => 1,
            'per_page' => $count,
        ]));
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'inbox', [
            'page' => $pageNumber,
            'per_page' => $pageSize,
        ]));
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->toIppanelResponse($this->ippanelRequest('GET', 'inbox', [
            'page' => $pageNumber,
            'per_page' => $pageSize,
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
        $apiKey = (string) ($config['api_key'] ?? '');

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api2.ippanel.com/api/v1'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                // Edge panels use AccessKey; the official SDK also sends apikey.
                'Authorization' => 'AccessKey '.$apiKey,
                'apikey' => $apiKey,
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
