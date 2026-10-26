<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class SmsIrDriver extends AbstractDriver
{
    use Concerns\HasProviderIdentity;

    /** Official sms.ir Sandbox verify template (parameter name: Code). */
    public const SANDBOX_TEMPLATE_ID = 123456;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeSmsIrClient($config));
    }

    protected function driverConfig(): array
    {
        return $this->config;
    }

    protected function defaultProviderName(): string
    {
        return 'smsir';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        return $this->bulk($message, $this->normalizeMobiles($mobiles), $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));

        $raw = $this->smsIrRequest('POST', 'send/bulk', [
            'lineNumber' => $this->resolveLineNumber($options['lineNumber'] ?? null),
            'messageText' => $message,
            'mobiles' => $mobiles,
            'sendDateTime' => $options['sendDateTime'] ?? null,
        ]);

        return $this->toResponse($raw);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));

        $raw = $this->smsIrRequest('POST', 'send/likeTolike', [
            'lineNumber' => $this->resolveLineNumber($options['lineNumber'] ?? null),
            'messageTexts' => array_values($messages),
            'mobiles' => $mobiles,
            'sendDateTime' => $options['sendDateTime'] ?? null,
        ]);

        return $this->toResponse($raw);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $mobile = $this->normalizeMobiles($mobile)[0] ?? $mobile;

        $raw = $this->smsIrRequest('POST', 'send/verify', [
            'mobile' => $mobile,
            'templateId' => (int) $templateId,
            'parameters' => $this->normalizeVerifyParameters($parameters),
        ]);

        return $this->toResponse($raw);
    }

    /**
     * Send via sms.ir official Sandbox verify template (API key type: Sandbox).
     */
    public function sendSandboxVerify(string $mobile, string $code = '12345'): SendResult
    {
        return $this->sendTemplate($mobile, self::SANDBOX_TEMPLATE_ID, ['Code' => $code]);
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->toResponse($this->smsIrRequest('DELETE', "send/scheduled/{$packId}"));
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', "send/{$messageId}"));
    }

    public function getPack(string $packId): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', "send/pack/{$packId}"));
    }

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', 'send/pack', query: [
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
        ]));
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toResponse($this->smsIrRequest('GET', 'send/live', query: array_filter([
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
            'mobile' => $mobile,
            'sortByNewest' => $sortByNewest,
        ], fn ($value) => $value !== null)));
    }

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toResponse($this->smsIrRequest('GET', 'send/archive', query: array_filter([
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
            'mobile' => $mobile,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'sortByNewest' => $sortByNewest,
        ], fn ($value) => $value !== null)));
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', 'receive/latest', query: [
            'count' => $count,
        ]));
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toResponse($this->smsIrRequest('GET', 'receive/live', query: array_filter([
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
            'mobile' => $mobile,
            'sortByNewest' => $sortByNewest,
        ], fn ($value) => $value !== null)));
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->toResponse($this->smsIrRequest('GET', 'receive/archive', query: array_filter([
            'PageNumber' => $pageNumber,
            'PageSize' => $pageSize,
            'mobile' => $mobile,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
        ], fn ($value) => $value !== null)));
    }

    public function getCredit(): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', 'credit'));
    }

    public function getLines(): SendResult
    {
        return $this->toResponse($this->smsIrRequest('GET', 'line'));
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeSmsIrClient(array $config): Client
    {
        $apiKey = (string) ($config['api_key'] ?? '');

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.sms.ir/v1'), '/').'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-key' => $apiKey,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SmsApiException
     */
    private function smsIrRequest(string $method, string $uri, array $payload = [], array $query = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('SMSAPI_API_KEY is not configured for the smsir driver.');
        }

        return $this->request(
            $method,
            $uri,
            $payload,
            $query,
            static function (array $decoded, int $httpStatus): bool {
                $status = isset($decoded['status']) ? (int) $decoded['status'] : null;

                return $httpStatus < 400 && ($status === null || $status === 1);
            },
        );
    }

    /**
     * @throws SmsApiException
     */
    private function resolveLineNumber(mixed $lineNumber): int
    {
        $resolved = $lineNumber !== null && $lineNumber !== ''
            ? (int) $lineNumber
            : $this->lineNumber;

        if ($resolved === null) {
            throw new SmsApiException(
                'Line number is required. Set SMSAPI_LINE_NUMBER or pass options.lineNumber.'
            );
        }

        return $resolved;
    }

    /**
     * @param  array<string, string>|array<int, array{name?: string, value?: string}>  $parameters
     * @return array<int, array{name: string, value: string}>
     */
    private function normalizeVerifyParameters(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters) && isset($parameters[0]) && is_array($parameters[0])) {
            return array_map(static function (array $item): array {
                return [
                    'name' => (string) ($item['name'] ?? ''),
                    'value' => (string) ($item['value'] ?? ''),
                ];
            }, $parameters);
        }

        $normalized = [];

        foreach ($parameters as $name => $value) {
            $normalized[] = [
                'name' => (string) $name,
                'value' => (string) $value,
            ];
        }

        return $normalized;
    }
}
