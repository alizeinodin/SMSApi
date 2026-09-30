<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://eitaayar.ir/docs */
class EitaaDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'eitaa');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $token = (string) $this->config('token', $this->config('bot_token', ''));
        if ($token === '') {
            $this->ensureConfigured('token', 'token');
        }
        $chatId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'api/'.$token.'/sendMessage', [
            'chat_id' => $chatId,
            'text' => $message,
        ], isSuccessful: fn (array $d): bool => ($d['ok'] ?? false) === true);

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? null, ($raw['ok'] ?? false) === true);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient((string) ($config['base_url'] ?? 'https://eitaayar.ir'));
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
