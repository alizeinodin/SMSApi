<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * @see https://developers.line.biz/en/docs/messaging-api/
 */
class LineDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'line';
    }

    protected function defaultChannel(): string
    {
        return 'line';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $to = $this->resolveRecipient($mobiles, $options);

        if ((string) $this->config('channel_access_token', '') === '') {
            throw new SmsApiException('LINE channel_access_token is not configured.');
        }

        try {
            $response = $this->client->request('POST', 'message/push', [
                'json' => [
                    'to' => $to,
                    'messages' => [
                        ['type' => 'text', 'text' => $message],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new SmsApiException('LINE request failed: '.$e->getMessage(), (int) $e->getCode(), previous: $e);
        }

        $body = (string) $response->getBody();
        $decoded = $body !== '' ? json_decode($body, true) : [];

        if ($response->getStatusCode() >= 400 || (is_array($decoded) && isset($decoded['message']))) {
            throw new SmsApiException(
                is_array($decoded) ? (string) ($decoded['message'] ?? 'LINE error') : 'LINE error',
                $response->getStatusCode(),
                is_array($decoded) ? $decoded : ['raw' => $body],
            );
        }

        return $this->toMessengerResponse(is_array($decoded) ? $decoded : [], null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.line.me/v2/bot'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.(string) ($config['channel_access_token'] ?? ''),
            ],
        ]);
    }
}
