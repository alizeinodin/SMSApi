<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://developers.facebook.com/docs/messenger-platform/send-messages */
class FacebookMessengerDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'messenger');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('page_access_token', 'page_access_token');
        $to = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;
        $pageId = (string) ($this->config('page_id', 'me'));

        $raw = $this->httpRequest('POST', $pageId.'/messages', [
            'recipient' => ['id' => $to],
            'messaging_type' => 'RESPONSE',
            'message' => ['text' => $message],
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400 && isset($d['message_id']));

        return $this->toMessengerResponse($raw, $raw['message_id'] ?? null);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $version = (string) ($config['api_version'] ?? 'v21.0');
        $base = rtrim((string) ($config['base_url'] ?? 'https://graph.facebook.com'), '/').'/'.$version;
        $token = (string) ($config['page_access_token'] ?? '');

        return $this->buildClient($base, [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
