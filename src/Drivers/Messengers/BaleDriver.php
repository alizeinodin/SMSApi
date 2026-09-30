<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** Bale bot API (Telegram-compatible). @see https://docs.bale.ai */
class BaleDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'bale');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('bot_token', 'bot_token');
        $chatId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ], isSuccessful: fn (array $d): bool => ($d['ok'] ?? false) === true);

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? null, ($raw['ok'] ?? false) === true);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? ''));
        $base = rtrim((string) ($config['base_url'] ?? 'https://tapi.bale.ai'), '/');

        return $this->buildClient($base.'/bot'.$token);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
