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
 * SMS Webservice V3 API (پیام‌رسان، بهین‌پیام، راستین، …).
 *
 * @see https://doc.sms-webservice.com/
 */
abstract class SmsWebserviceV3Driver extends AbstractDriver
{
    use FlattensTemplateParameters;
    use HasProviderIdentity;
    use ResolvesSenderLine;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeV3Client($config));
    }

    protected function driverConfig(): array
    {
        return $this->config;
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        return $this->bulk($message, $this->normalizeMobiles($mobiles), $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toV3Response($this->v3Request('POST', 'SendBulk', [
            'ApiKey' => $this->apiKey(),
            'Text' => $message,
            'Sender' => (int) $this->resolveSenderLine($options),
            'Recipients' => array_map(
                fn (string $mobile): array => [
                    'Destination' => $this->toV3Destination($mobile),
                    'UserTraceId' => 0,
                ],
                array_values($mobiles),
            ),
        ]));
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);
        $sender = (int) $this->resolveSenderLine($options);
        $recipients = [];

        foreach (array_values($mobiles) as $index => $mobile) {
            $recipients[] = [
                'Sender' => $sender,
                'Text' => (string) ($messages[$index] ?? ''),
                'Destination' => $this->toV3Destination($mobile),
                'UserTraceId' => 0,
            ];
        }

        return $this->toV3Response($this->v3Request('POST', 'SendMultiple', [
            'ApiKey' => $this->apiKey(),
            'Recipients' => $recipients,
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $query = [
            'ApiKey' => $this->apiKey(),
            'TemplateKey' => (string) $templateId,
            'Destination' => $this->toV3Destination($mobile),
        ];

        $index = 1;
        foreach ($this->flattenTemplateParameters($parameters) as $value) {
            $query['p'.$index] = $value;
            $index++;
        }

        return $this->toV3Response($this->v3Request('GET', 'SendTokenSingle', $query));
    }

    public function getCredit(): SendResult
    {
        $raw = $this->v3Request('POST', 'AccountInfo', ['ApiKey' => $this->apiKey()]);

        return $this->toV3Response($raw, data: [
            'credit' => $raw['Result']['Credit'] ?? $raw['Credit'] ?? null,
            'senders' => $raw['Result']['AvailableSenders'] ?? $raw['AvailableSenders'] ?? null,
        ]);
    }

    public function getLines(): SendResult
    {
        return $this->getCredit();
    }

    /**
     * StatusById takes a batch of message ids and answers with one delivery record per id.
     */
    public function getMessage(int $messageId): SendResult
    {
        return $this->toV3Response($this->v3Request('POST', 'StatusById', [
            'ApiKey' => $this->apiKey(),
            'Ids' => [$messageId],
        ]));
    }

    public function cancelScheduled(string $packId): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function makeV3Client(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.sms-webservice.com/api/V3'), '/').'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    protected function apiKey(): string
    {
        $key = (string) $this->config('api_key', '');

        if ($key === '') {
            throw new SmsApiException(sprintf('[%s] api_key is not configured.', $this->getName()));
        }

        return $key;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function v3Request(string $method, string $uri, array $payload = []): array
    {
        return $this->request(
            $method,
            ltrim($uri, '/'),
            strtoupper($method) === 'GET' ? [] : $payload,
            strtoupper($method) === 'GET' ? $payload : [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && (
                    array_key_exists('Success', $decoded)
                        ? $decoded['Success'] === true
                        : (
                            array_key_exists('success', $decoded)
                                ? $decoded['success'] === true
                                : (isset($decoded['Result']) || isset($decoded['result']) || isset($decoded['id']))
                        )
                ),
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toV3Response(array $raw, mixed $data = null): SendResult
    {
        $result = $raw['Result'] ?? $raw['result'] ?? $raw;

        return new SendResult(
            success: true,
            message: (string) ($raw['Message'] ?? $raw['message'] ?? 'OK'),
            data: $data ?? (is_array($result) ? $result : null),
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['StatusCode'] ?? 200),
        );
    }

    protected function toV3Destination(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?: $mobile;

        if (str_starts_with($digits, '98') && strlen($digits) > 10) {
            return substr($digits, 2);
        }

        return ltrim($digits, '0');
    }
}
