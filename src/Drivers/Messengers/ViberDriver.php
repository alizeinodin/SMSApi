<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://developers.viber.com/docs/api/rest-bot-api/
 */
class ViberDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'viber';
    }

    protected function defaultChannel(): string
    {
        return 'viber';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $receiver = $this->resolveRecipient($mobiles, $options);

        if ((string) $this->config('auth_token', '') === '') {
            throw new SmsApiException('Viber auth_token is not configured.');
        }

        $raw = $this->request(
            'POST',
            'send_message',
            [
                'receiver' => $receiver,
                'type' => 'text',
                'text' => $message,
                'sender' => [
                    'name' => (string) ($options['sender_name'] ?? $this->config('sender_name', 'Bot')),
                ],
            ],
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && (int) ($decoded['status'] ?? 1) === 0,
        );

        return $this->toMessengerResponse($raw, $raw['message_token'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://chatapi.viber.com/pa'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-Viber-Auth-Token' => (string) ($config['auth_token'] ?? ''),
            ],
        ]);
    }
}
