<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://api.slack.com/methods/chat.postMessage
 */
class SlackDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'slack';
    }

    protected function defaultChannel(): string
    {
        return 'slack';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $channel = (string) ($options['channel'] ?? $this->resolveRecipient($mobiles, $options));

        if ($channel === '') {
            throw new SmsApiException('Slack channel id is required.');
        }

        if ((string) $this->config('bot_token', '') === '') {
            throw new SmsApiException('Slack bot_token is not configured.');
        }

        $raw = $this->request(
            'POST',
            'chat.postMessage',
            [
                'channel' => $channel,
                'text' => $message,
            ],
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && ($decoded['ok'] ?? false) === true,
        );

        return $this->toMessengerResponse($raw, $raw['ts'] ?? $raw['message']['ts'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://slack.com/api'), '/').'/',
            'timeout' => self::requestTimeout($config),
            'connect_timeout' => self::connectTimeout($config),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json; charset=utf-8',
                'Authorization' => 'Bearer '.(string) ($config['bot_token'] ?? ''),
            ],
        ]);
    }
}
