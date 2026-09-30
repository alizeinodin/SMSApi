<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * sms.ir REST API v1 — sandbox uses a Sandbox API key + verify template 123456.
 *
 * @see https://sms.ir/rest-api/
 */
class SmsIrDriver extends AbstractDriver
{
    public const SANDBOX_TEMPLATE_ID = 123456;

    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'smsir');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        return $this->bulk($message, $this->normalizeMobiles($recipients), $options);
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));

        $raw = $this->request('POST', 'send/bulk', [
            'lineNumber' => (int) $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'messageText' => $message,
            'mobiles' => $mobiles,
            'sendDateTime' => $options['sendDateTime'] ?? null,
        ]);

        return $this->mapSmsIr($raw);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $mobile = $this->normalizeMobiles($mobile)[0] ?? $mobile;

        // Sandbox docs often use 912… without leading zero; keep local 09… which API also accepts.
        $raw = $this->request('POST', 'send/verify', [
            'mobile' => $mobile,
            'templateId' => (int) $templateId,
            'parameters' => $this->normalizeVerifyParameters($parameters),
        ]);

        return $this->mapSmsIr($raw);
    }

    /**
     * Convenience for official sandbox: template 123456 + Code parameter.
     */
    public function sendSandboxVerify(string $mobile, string $code = '12345'): SendResult
    {
        return $this->sendTemplate($mobile, self::SANDBOX_TEMPLATE_ID, ['Code' => $code]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function makeClient(array $config): Client
    {
        $apiKey = (string) ($config['api_key'] ?? '');

        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.sms.ir/v1'),
            ['x-api-key' => $apiKey],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = [], array $query = []): array
    {
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException('sms.ir api_key is not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            $query,
            fn (array $decoded, int $status): bool => $status < 400 && (int) ($decoded['status'] ?? 1) === 1,
        );
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<int, array{name: string, value: string}>
     */
    protected function normalizeVerifyParameters(array $parameters): array
    {
        $out = [];

        foreach ($parameters as $key => $value) {
            if (is_array($value) && isset($value['name'], $value['value'])) {
                $out[] = ['name' => (string) $value['name'], 'value' => (string) $value['value']];

                continue;
            }

            $out[] = ['name' => (string) $key, 'value' => (string) $value];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function mapSmsIr(array $raw): SendResult
    {
        $data = $raw['data'] ?? null;
        $messageId = null;

        if (is_array($data)) {
            $messageId = isset($data['messageId']) ? (string) $data['messageId'] : null;
        }

        return $this->toResponse(
            $raw,
            success: (int) ($raw['status'] ?? 0) === 1,
            message: (string) ($raw['message'] ?? 'OK'),
            messageId: $messageId,
        );
    }
}
