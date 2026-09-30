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
        $to = $this->toWhatsAppDigits(is_array($recipients) ? ($recipients[0] ?? '') : $recipients);
        $phoneNumberId = (string) ($options['phone_number_id'] ?? $this->config('phone_number_id'));

        $raw = $this->httpRequest('POST', $phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400 && isset($d['messages']));

        return $this->toMessengerResponse($raw, $raw['messages'][0]['id'] ?? null);
    }

    /**
     * Send an approved WhatsApp message template (required for most outbound Business traffic).
     *
     * @param  array<string, mixed>  $parameters  Body variables (list or map) OR pass full `components` via $options
     * @param  array<string, mixed>  $options  language (default en_US), components, phone_number_id
     */
    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $this->ensureConfigured('access_token', 'access_token');
        $this->ensureConfigured('phone_number_id', 'phone_number_id');
        $to = $this->toWhatsAppDigits($mobile);
        $phoneNumberId = (string) ($options['phone_number_id'] ?? $this->config('phone_number_id'));
        $language = (string) ($options['language'] ?? $this->config('template_language', 'en_US'));

        $template = [
            'name' => (string) $templateId,
            'language' => ['code' => $language],
        ];

        if (isset($options['components']) && is_array($options['components'])) {
            $template['components'] = $options['components'];
        } elseif ($parameters !== []) {
            $template['components'] = [$this->buildBodyComponent($parameters)];
        }

        $raw = $this->httpRequest('POST', $phoneNumberId.'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $template,
        ], isSuccessful: fn (array $d, int $s): bool => $s < 400 && isset($d['messages']));

        return $this->toMessengerResponse($raw, $raw['messages'][0]['id'] ?? null);
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

    protected function toWhatsAppDigits(string $to): string
    {
        $digits = preg_replace('/\D+/', '', $to) ?? $to;
        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '98'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * @param  array<string|int, mixed>  $parameters
     * @return array{type: string, parameters: list<array<string, mixed>>}
     */
    protected function buildBodyComponent(array $parameters): array
    {
        $out = [];
        $isList = array_is_list($parameters);

        foreach ($parameters as $key => $value) {
            if (is_array($value) && isset($value['type'])) {
                $out[] = $value;

                continue;
            }

            $text = is_array($value) ? (string) ($value['value'] ?? $value['text'] ?? '') : (string) $value;
            $param = ['type' => 'text', 'text' => $text];

            if (! $isList && is_string($key) && $key !== '') {
                $param['parameter_name'] = $key;
            }

            $out[] = $param;
        }

        return ['type' => 'body', 'parameters' => $out];
    }
}
