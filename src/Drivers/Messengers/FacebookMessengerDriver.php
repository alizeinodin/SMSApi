<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * Meta Messenger Platform (Facebook Page / Instagram messaging).
 *
 * @see https://developers.facebook.com/docs/messenger-platform
 */
class FacebookMessengerDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'messenger';
    }

    protected function defaultChannel(): string
    {
        return 'messenger';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $recipientId = $this->resolveRecipient($mobiles, $options);
        $pageId = (string) ($options['page_id'] ?? $this->config('page_id', 'me'));

        if ((string) $this->config('page_access_token', '') === '') {
            throw new SmsApiException('Messenger page_access_token is not configured.');
        }

        $raw = $this->request(
            'POST',
            $pageId.'/messages',
            [
                'messaging_type' => (string) ($options['messaging_type'] ?? 'RESPONSE'),
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
            ],
            ['access_token' => (string) $this->config('page_access_token')],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && isset($decoded['message_id']),
        );

        return $this->toMessengerResponse($raw, $raw['message_id'] ?? null);
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
            'headers' => ['Accept' => 'application/json', 'Content-Type' => 'application/json'],
        ]);
    }
}
