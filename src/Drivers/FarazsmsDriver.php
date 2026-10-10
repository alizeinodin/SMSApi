<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * Faraz SMS / فراز اس‌ام‌اس (IranPayamak REST API).
 *
 * @see https://docs.farazsms.com/
 */
class FarazsmsDriver extends AbstractDriver
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
        $attributes = $this->patternAttributes($parameters);

        return $this->toFarazResponse($this->farazRequest('POST', 'ws/v1/sms/pattern', [
            'code' => (string) $templateId,
            'recipient' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'attributes' => $attributes !== [] ? $attributes : (object) [],
            'line_number' => $this->resolveSenderLine($options),
            'number_format' => (string) ($options['number_format'] ?? 'english'),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/account/balance'));
    }

    public function getLines(): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/lines/accessible'));
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('GET', "ws/v1/send_request/{$messageId}"));
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/send_request', [
            'page' => $pageNumber,
            'per_page' => $pageSize,
        ]));
    }

    /**
     * Send requests are served from a single list, so archived sends page the same endpoint.
     */
    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/send_request', [
            'page' => $pageNumber,
            'per_page' => $pageSize,
        ]));
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/inbox', [
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
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/inbox', [
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
        return $this->toFarazResponse($this->farazRequest('GET', 'ws/v1/inbox', [
            'page' => $pageNumber,
            'per_page' => $pageSize,
        ]));
    }

    /**
     * The only cancellable resource in the API is a location based (LBS) campaign,
     * so $packId is expected to be an lbsId.
     */
    public function cancelScheduled(string $packId): SendResult
    {
        return $this->toFarazResponse($this->farazRequest('PATCH', "ws/v1/lbs/{$packId}/cancel"));
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
     * Pattern variables are addressed by name; positional parameters fall back to param1…paramN.
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return array<string, string>
     */
    private function patternAttributes(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            $attributes = [];
            foreach ($parameters as $item) {
                $attributes[(string) $item['name']] = (string) ($item['value'] ?? '');
            }

            return $attributes;
        }

        $attributes = [];
        foreach ($parameters as $key => $value) {
            $attributes[is_int($key) ? 'param'.($key + 1) : (string) $key] = (string) $value;
        }

        return $attributes;
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
