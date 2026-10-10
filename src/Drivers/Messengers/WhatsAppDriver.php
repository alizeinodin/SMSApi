<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use GuzzleHttp\Client;

/**
 * Meta WhatsApp Cloud API driver.
 *
 * @see https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class WhatsAppDriver extends AbstractMessengerDriver
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'whatsapp';
    }

    protected function defaultChannel(): string
    {
        return 'whatsapp';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $to = $this->normalizeWhatsAppRecipient($this->resolveRecipient($mobiles, $options));
        $phoneNumberId = (string) ($options['phone_number_id'] ?? $this->config('phone_number_id', ''));

        if ($phoneNumberId === '') {
            throw new SmsApiException('WhatsApp phone_number_id is required in config or options.');
        }

        $raw = $this->whatsappRequest($phoneNumberId, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        return new SendResult(
            success: isset($raw['messages'][0]['id']),
            message: 'OK',
            data: ['message_id' => $raw['messages'][0]['id'] ?? null],
            driver: $this->getName(),
            raw: $raw,
            statusCode: 200,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $version = (string) ($config['api_version'] ?? 'v21.0');

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://graph.facebook.com'), '/').'/'.$version.'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.(string) ($config['access_token'] ?? ''),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function whatsappRequest(string $phoneNumberId, array $payload): array
    {
        if ((string) $this->config('access_token', '') === '') {
            throw new SmsApiException('WhatsApp access_token is not configured.');
        }

        return $this->request(
            'POST',
            $phoneNumberId.'/messages',
            $payload,
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && isset($decoded['messages']),
        );
    }

    private function normalizeWhatsAppRecipient(string $recipient): string
    {
        return PhoneNumber::normalize($recipient, ['format' => 'digits', 'country_code' => '98']);
    }
}
