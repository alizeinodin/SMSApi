<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

class MagfaDriver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'magfa');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));
        $sender = $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null);

        return $this->map($this->request('POST', 'send', [
            'senders' => array_fill(0, count($mobiles), $sender),
            'recipients' => $mobiles,
            'messages' => array_fill(0, count($mobiles), $message),
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        $username = (string) ($config['username'] ?? '');
        $password = (string) ($config['password'] ?? '');
        $domain = (string) ($config['domain'] ?? 'magfa');

        return $this->buildClient(
            (string) ($config['base_url'] ?? 'https://sms.magfa.com/api/http/sms/v2'),
            extra: ['auth' => [$username.'/'.$domain, $password]],
        );
    }

    protected function buildDefaultClient(): Client
    {
        return $this->makeClient($this->config);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function request(string $method, string $uri, array $payload = []): array
    {
        if ((string) $this->config('username', '') === '' || (string) $this->config('password', '') === '') {
            throw new SmsApiException('Magfa username/password are not configured.');
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded): bool => (int) ($decoded['status'] ?? 1) === 0,
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        return $this->toResponse(
            $raw,
            success: (int) ($raw['status'] ?? 1) === 0,
            message: (string) ($raw['message'] ?? 'OK'),
        );
    }
}
