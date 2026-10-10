<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

class RubikaDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'rubika');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('bot_token', 'bot_token');
        $chatId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400);

        return $this->toMessengerResponse($raw, $raw['data']['message_id'] ?? $raw['message_id'] ?? null);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $token = rawurlencode((string) ($config['bot_token'] ?? ''));
        $base = rtrim((string) ($config['base_url'] ?? 'https://botapi.rubika.ir/v3'), '/');

        return $this->buildClient($base.'/'.$token);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
