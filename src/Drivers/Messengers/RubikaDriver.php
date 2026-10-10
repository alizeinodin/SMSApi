<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://rubika.ir/botapi
 */
class RubikaDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'rubika';
    }

    protected function defaultChannel(): string
    {
        return 'rubika';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $chatId = $this->resolveRecipient($mobiles, $options);

        $raw = $this->botRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'disable_notification' => $options['disable_notification'] ?? null,
            'reply_to_message_id' => $options['reply_to_message_id'] ?? null,
        ]);

        return $this->toMessengerResponse($raw, $raw['message_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? ''));

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://botapi.rubika.ir/v3'), '/').'/'.$token.'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function botRequest(string $method, array $payload): array
    {
        if ((string) $this->config('bot_token', '') === '') {
            throw new SmsApiException('Rubika bot_token is not configured.');
        }

        $payload = array_filter($payload, static fn ($value): bool => $value !== null);

        return $this->request(
            'POST',
            ltrim($method, '/'),
            $payload,
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && isset($decoded['message_id']),
        );
    }
}
