<?php

namespace Alizeinodin\SmsApi\Drivers\Messengers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/**
 * @see https://developer.eitaa.com/docs/Develop/SendMassage
 */
class EitaaDriver extends AbstractMessengerDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? self::makeClient($config));
    }

    protected function defaultProviderName(): string
    {
        return 'eitaa';
    }

    protected function defaultChannel(): string
    {
        return 'eitaa';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        $chatId = $this->resolveRecipient($mobiles, $options);
        $token = (string) $this->config('token', $this->config('bot_token', ''));

        if ($token === '') {
            throw new SmsApiException('Eitaa token is not configured.');
        }

        $raw = $this->request(
            'POST',
            'api/app/sendMessage',
            [
                'token' => $token,
                'chat_id' => is_numeric($chatId) ? (int) $chatId : $chatId,
                'text' => $message,
            ],
            [],
            static fn (array $decoded, int $httpStatus): bool => $httpStatus < 400 && ! isset($decoded['error']),
        );

        return $this->toMessengerResponse($raw, $raw['result']['message_id'] ?? $raw['message_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function makeClient(array $config): Client
    {
        return new Client([
            'base_uri' => rtrim((string) ($config['base_url'] ?? 'https://eitaayar.ir'), '/').'/',
            'timeout' => (float) ($config['timeout'] ?? 30),
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
        ]);
    }
}
