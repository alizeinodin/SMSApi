<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Arr;

/**
 * Shared HTTP + identity base for SMS providers and messengers.
 */
abstract class AbstractHttpDriver implements MessageDriver
{
    protected Client $client;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        protected array $config = [],
        ?Client $client = null,
    ) {
        $this->client = $client ?? $this->buildClient();
    }

    abstract public function getName(): string;

    abstract public function getChannel(): string;

    public function getProvider(): string
    {
        $type = $this->config['driver'] ?? null;

        if (is_string($type) && $type !== '') {
            return $type;
        }

        return $this->getName();
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        throw new SmsApiException(sprintf('[%s] send() is not implemented.', $this->getName()));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        throw new SmsApiException(sprintf('[%s] bulk() is not implemented.', $this->getName()));
    }

    /**
     * @param  array<string, string>  $headers
     */
    protected function buildClient(array $headers = []): Client
    {
        $baseUri = rtrim((string) ($this->config['base_url'] ?? ''), '/').'/';

        return new Client([
            'base_uri' => $baseUri,
            'timeout' => (float) ($this->config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => array_merge([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ], $headers),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     *
     * @throws SmsApiException
     */
    protected function request(
        string $method,
        string $uri,
        array $payload = [],
        array $query = [],
        ?callable $isSuccessful = null,
    ): array {
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if (in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true)) {
            $options['json'] = $payload;
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

        $successful = $isSuccessful
            ? (bool) $isSuccessful($decoded, $response->getStatusCode())
            : $response->getStatusCode() < 400;

        if (! $successful) {
            throw new SmsApiException(
                (string) ($decoded['message'] ?? sprintf('Unknown error from [%s].', $this->getName())),
                (int) ($decoded['status'] ?? $response->getStatusCode()),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toResponse(array $raw, bool $success = true, ?string $message = null): SendResult
    {
        return new SendResult(
            success: $success,
            message: $message ?? (string) ($raw['message'] ?? 'OK'),
            data: $raw['data'] ?? null,
            driver: $this->getName(),
            raw: $raw,
            statusCode: isset($raw['status']) ? (int) $raw['status'] : null,
        );
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }
}
