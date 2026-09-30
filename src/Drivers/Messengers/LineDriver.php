<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://developers.line.biz/en/reference/messaging-api/ */
class LineDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'line');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('channel_access_token', 'channel_access_token');
        $to = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;

        $messages = $options['messages'] ?? [['type' => 'text', 'text' => $message]];

        $raw = $this->httpRequest('POST', 'message/push', [
            'to' => $to,
            'messages' => $messages,
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400);

        return $this->toMessengerResponse($raw);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://api.line.me/v2/bot'),
            ['Authorization' => 'Bearer '.(string) ($config['channel_access_token'] ?? '')],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
