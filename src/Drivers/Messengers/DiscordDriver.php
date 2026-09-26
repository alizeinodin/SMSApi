<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://discord.com/developers/docs/resources/channel#create-message */
class DiscordDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'discord');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('bot_token', 'bot_token');
        $channelId = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'channels/'.$channelId.'/messages', [
            'content' => $message,
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400 && isset($d['id']));

        return $this->toMessengerResponse($raw, $raw['id'] ?? null);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $version = (string) ($config['api_version'] ?? 'v10');
        $base = rtrim((string) ($config['base_url'] ?? 'https://discord.com/api'), '/').'/'.$version;

        return $this->buildClient($base, [
            'Authorization' => 'Bot '.(string) ($config['bot_token'] ?? ''),
        ]);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
