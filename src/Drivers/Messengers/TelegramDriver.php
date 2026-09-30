<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://core.telegram.org/bots/api#sendmessage */
class TelegramDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'telegram');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('bot_token', 'bot_token');
        $chatId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => $options['parse_mode'] ?? null,
        ], fn ($v) => $v !== null), isSuccessful: fn (array $d): bool => ($d['ok'] ?? false) === true);

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? null, ($raw['ok'] ?? false) === true);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? ''));

        return $this->buildClient('https://api.telegram.org/bot'.$token);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
