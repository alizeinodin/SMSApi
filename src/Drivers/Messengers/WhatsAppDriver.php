<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/** @see https://developers.facebook.com/docs/whatsapp/cloud-api */
class WhatsAppDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'whatsapp');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $this->ensureConfigured('access_token', 'access_token');
        $this->ensureConfigured('phone_number_id', 'phone_number_id');
        $to = is_array($recipients) ? ($recipients[0] ?? '') : $recipients;
        $phoneNumberId = (string) ($options['phone_number_id'] ?? $this->config('phone_number_id'));

        $raw = $this->httpRequest('POST', $phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'to' => ltrim(preg_replace('/\D+/', '', $to) ?? $to, '0'),
            'type' => 'text',
            'text' => ['body' => $message],
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400 && isset($d['messages']));

        $id = $raw['messages'][0]['id'] ?? null;

        return $this->toMessengerResponse($raw, $id);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $version = (string) ($config['api_version'] ?? 'v21.0');
        $base = rtrim((string) ($config['base_url'] ?? 'https://graph.facebook.com'), '/').'/'.$version;

        return $this->buildClient($base, [
            'Authorization' => 'Bearer '.(string) ($config['access_token'] ?? ''),
        ]);
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }
}
