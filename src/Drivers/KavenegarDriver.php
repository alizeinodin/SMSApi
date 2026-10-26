<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @see https://kavenegar.com/rest.html
 */
class KavenegarDriver extends AbstractDriver
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
        return 'kavenegar';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        if ($mobiles === []) {
            throw new SmsApiException('At least one mobile number is required.');
        }

        if (count($mobiles) === 1) {
            return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/send.json', [
                'receptor' => $mobiles[0],
                'message' => $message,
                'sender' => $this->resolveSender($options),
            ]));
        }

        return $this->bulk($message, $mobiles, $options);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));
        $sender = $this->resolveSender($options);

        return $this->toKavenegarResponse($this->kavenegarRequest('POST', 'sms/sendarray.json', [
            'receptor' => json_encode($mobiles, JSON_UNESCAPED_UNICODE),
            'message' => json_encode(array_fill(0, count($mobiles), $message), JSON_UNESCAPED_UNICODE),
            'sender' => json_encode(array_fill(0, count($mobiles), $sender), JSON_UNESCAPED_UNICODE),
        ]));
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($mobiles));
        $messages = array_values($messages);
        $sender = $this->resolveSender($options);

        return $this->toKavenegarResponse($this->kavenegarRequest('POST', 'sms/sendarray.json', [
            'receptor' => json_encode($mobiles, JSON_UNESCAPED_UNICODE),
            'message' => json_encode($messages, JSON_UNESCAPED_UNICODE),
            'sender' => json_encode(array_fill(0, count($mobiles), $sender), JSON_UNESCAPED_UNICODE),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $query = [
            'receptor' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'template' => (string) $templateId,
        ];

        $tokenIndex = 0;
        foreach ($parameters as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $query[$this->mapVerifyToken((string) $value['name'], $tokenIndex)] = (string) $value['value'];
                $tokenIndex++;

                continue;
            }

            $query[$this->mapVerifyToken((string) $key, $tokenIndex)] = (string) $value;
            $tokenIndex++;
        }

        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'verify/lookup.json', $query));
    }

    public function getCredit(): SendResult
    {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'account/info.json'));
    }

    public function getLines(): SendResult
    {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'account/linenumbers.json'));
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/cancel.json', [
            'messageid' => $packId,
        ]), list: true);
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/select.json', [
            'messageid' => $messageId,
        ]));
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/latestoutbox.json', [
            'pagesize' => $pageSize,
        ]), list: true);
    }

    /**
     * Kavenegar only keeps one day per query, so a null range falls back to the last 24 hours.
     */
    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/selectoutbox.json', array_filter([
            'startdate' => $fromDate ?? time() - 86400,
            'enddate' => $toDate,
        ], fn ($value) => $value !== null)), list: true);
    }

    /**
     * The receive endpoint always returns the newest batch, so $count is not sent.
     */
    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/receive.json', [
            'linenumber' => $this->resolveSender([]),
            'isread' => 0,
        ]), list: true);
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/inboxpaged.json', [
            'linenumber' => $this->resolveSender([]),
            'isread' => 0,
            'pagenumber' => $pageNumber,
            'pagesize' => $pageSize,
        ]), list: true);
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->toKavenegarResponse($this->kavenegarRequest('GET', 'sms/inboxpaged.json', array_filter([
            'linenumber' => $this->resolveSender([]),
            'isread' => 1,
            'pagenumber' => $pageNumber,
            'pagesize' => $pageSize,
            'startdate' => $fromDate,
            'enddate' => $toDate,
        ], fn ($value) => $value !== null)), list: true);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $apiKey = rawurlencode((string) ($config['api_key'] ?? ''));

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.kavenegar.com/v1'), '/').'/'.$apiKey.'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * GET uses query string; POST (sendarray) uses form-urlencoded fields.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function kavenegarRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Kavenegar api_key is not configured.');
        }

        $method = strtoupper($method);
        $options = [];

        if ($method === 'GET') {
            $options['query'] = $payload;
        } elseif ($payload !== []) {
            $options['form_params'] = $payload;
        }

        try {
            $response = $this->client->request($method, ltrim($uri, '/'), $options);
        } catch (GuzzleException $e) {
            throw $this->transportException($e);
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

        $status = (int) ($decoded['return']['status'] ?? 0);

        if ($response->getStatusCode() >= 400 || $status !== 200) {
            throw new SmsApiException(
                (string) ($decoded['return']['message'] ?? sprintf('Unknown error from [%s].', $this->getName())),
                $status !== 0 ? $status : $response->getStatusCode(),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toKavenegarResponse(array $raw, bool $list = false): SendResult
    {
        $entries = $raw['return']['entries'] ?? $raw['entries'] ?? $raw['return']['entry'] ?? null;

        if ($list) {
            $data = is_array($entries)
                ? (array_is_list($entries) ? $entries : [$entries])
                : [];
        } else {
            $entry = is_array($entries) && array_is_list($entries) ? ($entries[0] ?? null) : $entries;
            $data = is_array($entry) ? $entry : ($raw['return'] ?? null);
        }

        return new SendResult(
            success: (int) ($raw['return']['status'] ?? 0) === 200,
            message: (string) ($raw['return']['message'] ?? 'OK'),
            data: $data,
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['return']['status'] ?? 200),
        );
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveSender(array $options): string
    {
        $sender = $options['sender'] ?? $options['lineNumber'] ?? $this->lineNumber;

        if ($sender === null || $sender === '') {
            throw new SmsApiException(
                'Kavenegar sender/line is required. Set line_number in config or pass options.sender.'
            );
        }

        return (string) $sender;
    }

    private function mapVerifyToken(string $name, int $index): string
    {
        $normalized = strtolower(trim($name));

        return match ($normalized) {
            'token', 'code', 'otp' => 'token',
            'token2', 'token_2' => 'token2',
            'token3', 'token_3' => 'token3',
            default => $index === 0 ? 'token' : 'token'.($index + 1),
        };
    }
}
