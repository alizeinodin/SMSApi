<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/** Shared base for melipayamak / farapayamak / payamito / … (payamak-panel REST). */
class PayamakPanelDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? $this->config['driver'] ?? 'payamakpanel');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $to = implode(',', $this->normalizeMobiles($recipients));

        return $this->map($this->request('POST', 'SendSMS/SendSMS', array_merge($this->auth(), [
            'to' => $to,
            'from' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'text' => $message,
            'isFlash' => false,
        ])));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $text = implode(';', array_map(
            fn ($v) => is_array($v) ? (string) ($v['value'] ?? '') : (string) $v,
            array_values($parameters),
        ));

        return $this->map($this->request('POST', 'SendSMS/BaseServiceNumber', array_merge($this->auth(), [
            'to' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'text' => $text,
            'bodyId' => (int) $templateId,
        ])));
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient((string) ($config['base_url'] ?? 'https://rest.payamak-panel.com/api'));
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
            throw new SmsApiException(sprintf('[%s] username/password are not configured.', $this->getName()));
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
            isSuccessful: fn (array $decoded): bool => (int) ($decoded['RetStatus'] ?? 0) === 1,
            bodyMode: 'form',
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse(
            $raw,
            success: (int) ($raw['RetStatus'] ?? 0) === 1,
            message: (string) ($raw['StrRetStatus'] ?? 'OK'),
            messageId: isset($raw['Value']) ? (string) $raw['Value'] : null,
        );
    }
}
