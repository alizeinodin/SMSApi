<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * iGap bot API (Telegram-compatible style).
 *
 * Set `IGAP_BOT_BASE_URL` if your deployment uses a different gateway.
 *
 * @see https://igap.net/
 */
class IgapDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'igap';
    }

    protected function defaultChannel(): string
    {
        return 'igap';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $chatId = $this->resolveRecipient($mobiles, $options);

        $raw = $this->botRequest('sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ]);

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? $raw['message_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? $config['token'] ?? ''));

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://bot.igap.net'), '/').'/bot'.$token.'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => ['Accept' => 'application/json'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function botRequest(string $method, array $payload): array
    {
        if ((string) $this->config('bot_token', $this->config('token', '')) === '') {
            throw new SmsApiException('iGap bot_token is not configured.');
        }

        $payload = array_filter($payload, static fn ($value): bool => $value !== null);

        return $this->request(
            'POST',
            ltrim($method, '/'),
            $payload,
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && ($decoded['ok'] ?? true) === true,
        );
    }
}
