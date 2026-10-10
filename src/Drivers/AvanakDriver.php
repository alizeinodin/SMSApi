<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Avanak (آوانک) voice / OTP REST API — not traditional SMS.
 *
 * @see https://portal.avanak.ir/documentation
 */
class AvanakDriver extends AbstractDriver
{
    use Concerns\FlattensTemplateParameters;
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
        return 'avanak';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $mobiles = $this->normalizeMobiles($mobiles);

        if (count($mobiles) !== 1) {
            throw new UnsupportedDriverMethodException($this->getName(), 'bulk');
        }

        return $this->toAvanakResponse($this->avanakFormRequest('QuickSendWithTTS', [
            'Text' => $message,
            'Number' => $mobiles[0],
            'Vote' => (bool) ($options['vote'] ?? false),
            'ServerID' => (int) ($options['server_id'] ?? $this->config('server_id', 0)),
        ]));
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $code = $this->extractTemplateCode($parameters, 'code', 'otp', 'token');
        $length = (int) ($options['length'] ?? $this->config('otp_length', 0));

        if ($length <= 0) {
            $length = is_numeric($templateId)
                ? (int) $templateId
                : max(4, strlen($code ?: '0000'));
        }

        $payload = [
            'Length' => $length,
            'Number' => $mobile,
            'OptionalCode' => $code !== '' ? $code : '0',
            'ServerID' => (int) ($options['server_id'] ?? $this->config('server_id', 0)),
        ];

        return $this->toAvanakResponse($this->avanakFormRequest('SendOTP', $payload), otp: true);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        throw new UnsupportedDriverMethodException($this->getName(), __FUNCTION__);
    }

    public function getCredit(): SendResult
    {
        return $this->toAvanakResponse($this->avanakFormRequest('AccountStatus', []), credit: true);
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->toAvanakResponse($this->avanakFormRequest('GetQuickSend', [
            'QuickSendID' => $messageId,
        ]));
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
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://portal.avanak.ir/rest'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function avanakFormRequest(string $uri, array $payload): array
    {
        $token = (string) $this->config('api_token', $this->config('token', ''));

        if ($token === '') {
            throw new SmsApiException('Avanak api_token is not configured.');
        }

        $form = array_merge(['Token' => $token], $payload);

        try {
            $response = $this->client->request('POST', ltrim($uri, '/'), [
                'form_params' => $form,
                'headers' => [
                    'Authorization' => $token,
                ],
            ]);
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
            $numeric = is_numeric(trim($body)) ? (int) trim($body) : null;

            if ($numeric !== null && $numeric > 0) {
                return ['QuickSendID' => $numeric, 'status' => $numeric];
            }

            throw new SmsApiException(
                sprintf('Invalid response from [%s].', $this->getName()),
                $response->getStatusCode(),
                ['raw' => $body],
            );
        }

        $errorCode = (int) ($decoded['ErrorCode'] ?? $decoded['errorCode'] ?? 0);

        if ($response->getStatusCode() >= 400 || $errorCode < 0) {
            throw new SmsApiException(
                (string) ($decoded['Message'] ?? 'Avanak request failed.'),
                $errorCode,
                $decoded,
            );
        }

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function toAvanakResponse(array $raw, bool $otp = false, bool $credit = false): SendResult
    {
        $quickSendId = $raw['QuickSendID'] ?? $raw['quickSendId'] ?? $raw['status'] ?? null;

        if ($credit) {
            return new SendResult(
                success: true,
                message: (string) ($raw['Message'] ?? 'OK'),
                data: $raw,
                driver: $this->getName(),
                raw: $raw,
                statusCode: 200,
            );
        }

        return new SendResult(
            success: $quickSendId === null || (int) $quickSendId > 0,
            message: (string) ($raw['Message'] ?? 'OK'),
            data: $otp
                ? [
                    'quick_send_id' => $quickSendId,
                    'generated_code' => $raw['GeneratedCode'] ?? $raw['generatedCode'] ?? null,
                ]
                : ['quick_send_id' => $quickSendId],
            driver: $this->getName(),
            raw: $raw,
            statusCode: (int) ($quickSendId ?? 200),
        );
    }
}
