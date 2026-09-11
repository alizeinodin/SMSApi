<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;

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
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toKavenegarResponse($this->kavenegarRequest('POST', 'sms/sendarray.json', [
            'receptor' => json_encode(array_values($mobiles), JSON_UNESCAPED_UNICODE),
            'message' => json_encode(array_fill(0, count($mobiles), $message), JSON_UNESCAPED_UNICODE),
            'sender' => $this->resolveSender($options),
        ]));
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toKavenegarResponse($this->kavenegarRequest('POST', 'sms/sendarray.json', [
            'receptor' => json_encode(array_values($mobiles), JSON_UNESCAPED_UNICODE),
            'message' => json_encode(array_values($messages), JSON_UNESCAPED_UNICODE),
            'sender' => $this->resolveSender($options),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $query = [
            'receptor' => $mobile,
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
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $apiKey = rawurlencode((string) ($config['api_key'] ?? ''));

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.kavenegar.com/v1'), '/').'/'.$apiKey.'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function kavenegarRequest(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('Kavenegar api_key is not configured.');
        }

        $query = strtoupper($method) === 'GET' ? $payload : [];
        $body = strtoupper($method) === 'GET' ? [] : $payload;

        return $this->request(
            $method,
            ltrim($uri, '/'),
            $body,
            $query,
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
                && (int) ($decoded['return']['status'] ?? 0) === 200,
        );
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toKavenegarResponse(array $raw): SendResult
    {
        $entry = $raw['return']['entries'][0] ?? $raw['return']['entry'] ?? $raw['entries'][0] ?? null;

        return new SendResult(
            success: (int) ($raw['return']['status'] ?? 0) === 200,
            message: (string) ($raw['return']['message'] ?? 'OK'),
            data: is_array($entry) ? $entry : ($raw['return'] ?? null),
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
