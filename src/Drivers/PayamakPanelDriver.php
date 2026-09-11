<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Drivers\Concerns\FlattensTemplateParameters;
use Alizeinodin\SmsApi\Drivers\Concerns\HasProviderIdentity;
use Alizeinodin\SmsApi\Drivers\Concerns\ResolvesSenderLine;
use GuzzleHttp\Client;

/**
 * Shared Payamak Panel REST API (ملی‌پیامک، فراپیامک، پیامیتو، …).
 *
 * @see https://www.payamak-panel.com/rest.html
 */
abstract class PayamakPanelDriver extends AbstractDriver
{
    use FlattensTemplateParameters;
    use HasProviderIdentity;
    use ResolvesSenderLine;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makePayamakClient($config));
    }

    protected function driverConfig(): array
    {
        return $this->config;
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/SendSMS', [
            'to' => implode(',', $mobiles),
            'from' => $this->resolveSenderLine($options, 'from/line_number'),
            'text' => $message,
            'isflash' => false,
        ]));
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->send($mobiles, $message, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/SendByBaseNumber2', [
            'to' => $mobile,
            'bodyId' => (int) $templateId,
            'args' => $this->flattenTemplateParameters($parameters),
        ]));
    }

    public function getCredit(): SendResult
    {
        return $this->toPayamakResponse($this->payamakRequest('POST', 'SendSMS/GetCredit', []));
    }

    public function cancelScheduled(string $packId): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected static function makePayamakClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://rest.payamak-panel.com/api'), '/') . '/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function payamakRequest(string $method, string $uri, array $payload): array
    {
        $username = (string) $this->config('username', '');
        $password = (string) $this->config('password', '');

        if ($username === '' || $password === '') {
            throw new SmsApiException(sprintf('[%s] username/password are not configured.', $this->getName()));
        }

        $form = array_merge([
            'username' => $username,
            'password' => $password,
        ], $payload);

        if (isset($form['args']) && is_array($form['args'])) {
            $form['args'] = json_encode(array_values($form['args']), JSON_UNESCAPED_UNICODE);
        }

        try {
            $response = $this->client->request($method, ltrim($uri, '/'), ['form_params' => $form]);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new SmsApiException(
                sprintf('HTTP request via [%s] failed: %s', $this->getName(), $e->getMessage()),
                (int) $e->getCode(),
                previous: $e,
            );
        }

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            $decoded = ['Value' => trim($body), 'RetStatus' => $response->getStatusCode() < 400 ? 1 : 0];
        }

        if ($response->getStatusCode() >= 400 || ($decoded['RetStatus'] ?? null) === 0) {
            throw new SmsApiException(
                (string) ($decoded['StrRetStatus'] ?? $decoded['Message'] ?? 'Payamak panel request failed.'),
                (int) ($decoded['RetStatus'] ?? $decoded['Value'] ?? 0),
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    protected function toPayamakResponse(array $raw): SendResult
    {
        return new SendResult(
            success: true,
            message: (string) ($raw['StrRetStatus'] ?? 'OK'),
            data: $raw,
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($raw['RetStatus'] ?? 1),
        );
    }
}
