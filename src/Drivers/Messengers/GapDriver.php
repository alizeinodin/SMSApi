<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://developer.gap.im/documents/fa/api/
 */
class GapDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'gap';
    }

    protected function defaultChannel(): string
    {
        return 'gap';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $chatId = $this->resolveRecipient($mobiles, $options);

        if ((string) $this->config('token', '') === '') {
            throw new SmsApiException('Gap token is not configured.');
        }

        $raw = $this->requestForm('POST', 'sendMessage', [
            'chat_id' => $chatId,
            'type' => (string) ($options['type'] ?? 'TEXT'),
            'text' => $message,
        ], static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400
            && (($decoded['success'] ?? false) === true || ($decoded['status'] ?? null) === 'success'));

        return $this->toMessengerResponse($raw, $raw['message_id'] ?? $raw['data']['message_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://api.gap.im'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'token' => (string) ($config['token'] ?? ''),
            ],
        ]);
    }
}
