<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class NiksmsDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'niksms');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobile = $this->normalizeMobiles($recipients)[0] ?? '';

        return $this->map($this->request('POST', 'SendSms/SendOne', array_merge($this->auth(), [
            'senderNumber' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'numbers' => [$mobile],
            'message' => $message,
        ])));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));

        return $this->map($this->request('POST', 'SendSms/SendGroupOnOneMessage', array_merge($this->auth(), [
            'senderNumber' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'numbers' => $mobiles,
            'message' => $message,
        ])));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient((string) ($config['base_url'] ?? 'https://niksms.com/api/v2'));
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /** @return array{username: string, password: string} */
    protected function auth(): array
    {
        $username = (string) $this->config('username', '');
        $password = (string) $this->config('password', '');

        if ($username === '' || $password === '') {
            throw new SmsApiException('Niksms username/password are not configured.');
        }

        return compact('username', 'password');
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = []): array
    {
        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded): bool => (int) ($decoded['Status'] ?? 0) === 1,
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse(
            $raw,
            success: (int) ($raw['Status'] ?? 0) === 1,
            message: (string) ($raw['Message'] ?? 'OK'),
        );
    }
}
