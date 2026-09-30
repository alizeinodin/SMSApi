<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

abstract class AbstractDriver implements SmsDriver
{
    protected Client $client;

    protected ?string $lineNumber = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
        ?Client $client = null,
    ) {
        $line = $config['line_number'] ?? null;
        $this->lineNumber = $line !== null && $line !== '' ? (string) $line : null;
        $this->client = $client ?? $this->buildDefaultClient();
    }

    abstract public function getName(): string;

    public function getProvider(): string
    {
        $type = $this->config['driver'] ?? null;

        return (is_string($type) && $type !== '') ? $type : $this->getName();
    }

    public function getChannel(): string
    {
        return (string) ($this->config['channel'] ?? 'sms');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        throw new SmsApiException(sprintf('[%s] send() is not implemented.', $this->getName()));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        throw new SmsApiException(sprintf('[%s] bulk() is not implemented.', $this->getName()));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        throw new SmsApiException(sprintf('[%s] sendTemplate() is not implemented.', $this->getName()));
    }

    /**
     * @return array<int, string>
     */
    protected function normalizeMobiles(string|array $mobiles): array
    {
        return PhoneNumber::normalize($mobiles, [
            'format' => (string) ($this->config['phone_format'] ?? 'local'),
            'country_code' => (string) ($this->config['country_code'] ?? '98'),
        ]);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toResponse(
        array $raw,
        bool $success = true,
        ?string $message = null,
        ?string $messageId = null,
    ): SendResult {
        return new SendResult(
            success: $success,
            message: $message ?? (string) ($raw['message'] ?? 'OK'),
            data: $raw['data'] ?? $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: isset($raw['status']) ? (int) $raw['status'] : null,
            messageId: $messageId,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function buildClient(string $baseUri, array $headers = [], array $extra = []): Client
    {
        return new Client(array_merge([
            'base_uri' => rtrim($baseUri, '/').'/',
            'timeout' => (float) ($this->config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $headers),
        ], $extra));
    }

    protected function buildDefaultClient(): Client
    {
        return $this->buildClient((string) ($this->config['base_url'] ?? 'https://example.invalid'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function httpRequest(
        string $method,
        string $uri,
        array $payload = [],
        array $query = [],
        ?callable $isSuccessful = null,
        string $bodyMode = 'json',
    ): array {
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            if ($bodyMode === 'form') {
                $options['form_params'] = $payload;
            } else {
                $options['json'] = $payload;
            }
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
        $status = $response->getStatusCode();

        if (trim($body) === '') {
            if ($status < 400) {
                return [];
            }

            throw new SmsApiException(
                sprintf('Empty error response from [%s].', $this->getName()),
                $status,
            );
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new SmsApiException(
                sprintf('Invalid JSON response from [%s].', $this->getName()),
                $status,
                ['raw' => $body],
            );
        }

        $successful = $isSuccessful
            ? (bool) $isSuccessful($decoded, $status)
            : $status < 400;

        if (! $successful) {
            throw new SmsApiException(
                $this->extractErrorMessage($decoded),
                (int) ($decoded['status'] ?? $decoded['Status'] ?? $decoded['error_code'] ?? $status),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $decoded
     */
    protected function extractErrorMessage(array $decoded): string
    {
        foreach (['description', 'message', 'Message', 'status_message'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key]) && $decoded[$key] !== '') {
                return $decoded[$key];
            }
        }

        if (isset($decoded['error'])) {
            if (is_string($decoded['error']) && $decoded['error'] !== '') {
                return $decoded['error'];
            }

            if (is_array($decoded['error']) && isset($decoded['error']['message']) && is_string($decoded['error']['message'])) {
                return $decoded['error']['message'];
            }
        }

        return sprintf('Unknown error from [%s].', $this->getName());
    }

    protected function resolveLine(?string $override = null): string
    {
        $line = $override ?? $this->lineNumber;

        if ($line === null || $line === '') {
            throw new SmsApiException(sprintf('[%s] line_number is required.', $this->getName()));
        }

        return (string) $line;
    }
}
