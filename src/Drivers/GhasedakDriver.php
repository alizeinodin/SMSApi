<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Ghasedak (قاصدک) HTTP API v2.
 *
 * The v2 API has no outbox listing, so getLiveSends/getArchivedSends and the
 * pack endpoints stay unsupported; sent messages are only readable one id at a
 * time through sms/status.
 *
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

    /**
     * Pair sending joins both lists with commas, so a message may not contain a comma itself.
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));
        $messages = array_values(array_map('strval', $messages));

        if (count($messages) !== count($mobiles)) {
            throw new SmsApiException('Ghasedak pair sending needs one message per receptor.');
        }

        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/send/pair', [
            'message' => implode(',', $messages),
            'receptors' => $mobiles,
            'linenumber' => $this->resolveLine($options),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $payload = [
            'receptor' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
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

    /**
     * Ghasedak exposes lines only as part of the account payload, and only for some plans.
     */
    public function getLines(): SendResult
    {
        $raw = $this->ghasedakRequest('GET', 'account/info');
        $items = $raw['items'] ?? null;
        $lines = null;

        if (is_array($items)) {
            $lines = $items['lines'] ?? $items[0]['lines'] ?? null;
        }

        return $this->toGhasedakResponse(
            $raw,
            data: is_array($lines) ? array_values($lines) : (is_array($items) ? $items : []),
        );
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/cancel', [
            'messageid' => $packId,
        ]));
    }

    /**
     * Type 1 looks the message up by its Ghasedak message id (type 2 would be the client checkid).
     */
    public function getMessage(int $messageId): SendResult
    {
        return $this->toGhasedakResponse($this->ghasedakRequest('GET', 'sms/status', [
            'id' => $messageId,
            'type' => 1,
        ]));
    }

    /**
     * Returns the newest unread batch for the line; Ghasedak decides the size, so $count is not sent.
     */
    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/receive/last', [
            'linenumber' => $this->resolveLine([]),
            'isread' => 0,
        ]), list: true);
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->receivePaging(isRead: 0, pageNumber: $pageNumber, pageSize: $pageSize);
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->receivePaging(
            isRead: 1,
            pageNumber: $pageNumber,
            pageSize: $pageSize,
            fromDate: $fromDate,
            toDate: $toDate,
        );
    }

    /**
     * The paging endpoint calls the page size "offset".
     */
    private function receivePaging(
        int $isRead,
        int $pageNumber,
        int $pageSize,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->toGhasedakResponse($this->ghasedakRequest('POST', 'sms/receive/paging', array_filter([
            'linenumber' => $this->resolveLine([]),
            'isread' => $isRead,
            'page' => $pageNumber,
            'offset' => $pageSize,
            'fromdate' => $fromDate,
            'todate' => $toDate,
        ], static fn ($value) => $value !== null)), list: true);
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
                'apikey' => (string) ($config['api_key'] ?? ''),
            ],
        ]);
    }

    /**
     * Ghasedak v2 expects application/x-www-form-urlencoded bodies (not JSON).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ghasedakRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Ghasedak api_key is not configured.');
        }

        $method = strtoupper($method);
        $options = [];

        if ($method === 'GET') {
            $options['query'] = $payload;
        } elseif ($payload !== []) {
            // Bulk receptors must be a comma-separated string for form posts.
            if (isset($payload['receptors']) && is_array($payload['receptors'])) {
                $payload['receptor'] = implode(',', $payload['receptors']);
                unset($payload['receptors']);
            }

            $options['form_params'] = $payload;
        }

        try {
            $response = $this->client->request($method, ltrim($uri, '/'), $options);
        } catch (GuzzleException $e) {
            throw new SmsApiException(
                sprintf('HTTP request via [%s] failed: %s', $this->getName(), $e->getMessage()),
                (int) $e->getCode(),
                previous: $e,
            );
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new SmsApiException(
                sprintf('Invalid JSON response from [%s].', $this->getName()),
                $response->getStatusCode(),
                ['raw' => $body],
            );
        }

        $code = (int) ($decoded['result']['code'] ?? 0);

        if ($response->getStatusCode() >= 400 || $code !== 200) {
            throw new SmsApiException(
                (string) ($decoded['result']['message'] ?? sprintf('Unknown error from [%s].', $this->getName())),
                $code !== 0 ? $code : $response->getStatusCode(),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toGhasedakResponse(array $raw, bool $list = false, mixed $data = null): SendResult
    {
        $code = (int) ($raw['result']['code'] ?? 0);
        $items = $raw['items'] ?? null;

        if ($data === null) {
            $data = $list
                ? (is_array($items) ? (array_is_list($items) ? $items : [$items]) : [])
                : ($items[0] ?? $items ?? $raw['result'] ?? null);
        }

        return new SendResult(
            success: $code === 200,
            message: (string) ($raw['result']['message'] ?? 'OK'),
            data: $data,
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
