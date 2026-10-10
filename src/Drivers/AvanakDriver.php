<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/** Avanak voice OTP / TTS. */
class AvanakDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'avanak');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobile = $this->normalizeMobiles($recipients)[0] ?? '';

        return $this->map($this->request('SendMessage', [
            'Number' => $mobile,
            'Message' => $message,
            'ServerID' => (int) ($options['server_id'] ?? $this->config('server_id', 0)),
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $code = '';
        foreach ($parameters as $key => $value) {
            $name = is_array($value) ? strtolower((string) ($value['name'] ?? $key)) : strtolower((string) $key);
            $val = is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
            if (in_array($name, ['code', 'otp', 'token'], true) || $code === '') {
                $code = $val;
            }
        }

        return $this->map($this->request('SendOTP', [
            'Number' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'Code' => $code,
            'Length' => (int) ($options['length'] ?? $this->config('otp_length', strlen($code) ?: 5)),
            'ServerID' => (int) ($options['server_id'] ?? $this->config('server_id', 0)),
        ]));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient((string) ($config['base_url'] ?? 'https://portal.avanak.ir/rest'));
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, array $payload = []): array
    {
        $token = (string) $this->config('api_token', $this->config('token', ''));

        if ($token === '') {
            throw new SmsApiException('Avanak api_token is not configured.');
        }

        return $this->httpRequest(
            'POST',
            $method,
            array_merge(['Token' => $token], $payload),
            isSuccessful: fn (array $decoded, int $status): bool => $status < 400,
            bodyMode: 'form',
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse($raw, success: true, message: (string) ($raw['message'] ?? 'OK'));
    }
}
