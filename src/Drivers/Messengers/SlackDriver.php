<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://api.slack.com/methods/chat.postMessage */
class SlackDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'slack');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('bot_token', 'bot_token');
        $channel = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $raw = $this->httpRequest('POST', 'chat.postMessage', array_filter([
            'channel' => $channel,
            'text' => $message,
            'blocks' => $options['blocks'] ?? null,
            'thread_ts' => $options['thread_ts'] ?? null,
            'mrkdwn' => $options['mrkdwn'] ?? null,
        ], fn ($v) => $v !== null), isSuccessful: fn (array $d): bool => ($d['ok'] ?? false) === true);

        return $this->toMessengerResponse($raw, $raw['ts'] ?? null, ($raw['ok'] ?? false) === true);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://slack.com/api'),
            ['Authorization' => 'Bearer '.(string) ($config['bot_token'] ?? '')],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
