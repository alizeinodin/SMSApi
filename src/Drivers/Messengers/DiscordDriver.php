<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://discord.com/developers/docs/resources/channel#create-message
 */
class DiscordDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'discord';
    }

    protected function defaultChannel(): string
    {
        return 'discord';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $channelId = (string) ($options['channel_id'] ?? $this->resolveRecipient($mobiles, $options));

        if ($channelId === '') {
            throw new SmsApiException('Discord channel_id is required.');
        }

        if ((string) $this->config('bot_token', '') === '') {
            throw new SmsApiException('Discord bot_token is not configured.');
        }

        $raw = $this->request(
            'POST',
            'channels/'.$channelId.'/messages',
            ['content' => $message],
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && isset($decoded['id']),
        );

        return $this->toMessengerResponse($raw, $raw['id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        $version = (string) ($config['api_version'] ?? 'v10');

        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://discord.com/api'), '/').'/'.$version.'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Bot '.(string) ($config['bot_token'] ?? ''),
            ],
        ]);
    }
}
