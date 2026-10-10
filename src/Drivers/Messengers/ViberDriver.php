<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://developers.viber.com/docs/api/rest-bot-api/ */
class ViberDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'viber');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('auth_token', 'auth_token');
        $to = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'send_message', [
            'receiver' => $to,
            'type' => 'text',
            'text' => $message,
            'sender' => [
                'name' => (string) ($options['sender_name'] ?? $this->config('sender_name', 'Bot')),
            ],
        ], isSuccessful: fn (array $d): bool => (int) ($d['status'] ?? 1) === 0);

        return $this->toMessengerResponse($raw, $raw['message_token'] ?? null, (int) ($raw['status'] ?? 1) === 0);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://chatapi.viber.com/pa'),
            ['X-Viber-Auth-Token' => (string) ($config['auth_token'] ?? '')],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
