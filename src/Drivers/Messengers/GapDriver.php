<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

class GapDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'gap');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('token', 'token');
        $chatId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'sendMessage', [
            'chat_id' => $chatId,
            'data' => $message,
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400, bodyMode: 'form');

        return $this->toMessengerResponse($raw);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.gap.im'),
            ['token' => (string) ($config['token'] ?? '')],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
