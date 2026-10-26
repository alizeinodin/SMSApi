<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://core.telegram.org/bots/api#sendmessage
 */
class TelegramDriver extends AbstractMessengerDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'telegram';
    }

    protected function defaultChannel(): string
    {
        return 'telegram';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $chatId = $this->resolveRecipient($mobiles, $options);

        $raw = $this->telegramRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $options['parse_mode'] ?? null,
        ]);

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? ''));

        return new Client([
            'base_uri' => 'https://api.telegram.org/bot'.$token.'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function telegramRequest(string $method, array $payload): array
    {
        if ((string) $this->config('bot_token', '') === '') {
            throw new SmsApiException('Telegram bot_token is not configured.');
        }

        $payload = array_filter($payload, static fn ($value): bool => $value !== null);

        return $this->request(
            'POST',
            ltrim($method, '/'),
            $payload,
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && ($decoded['ok'] ?? false) === true,
        );
    }
}
