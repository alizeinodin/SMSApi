<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;

/** Shared base for payamresan / behinpayam / rastinsms (sms-webservice V3). */
class SmsWebserviceV3Driver extends AbstractDriver
{
    public function __construct(array $config = [], ?Client $client = null)
    {
        parent::__construct($config, $client ?? $this->makeClient($config));
    }

    public function getName(): string
    {
        return (string) ($this->config['name'] ?? $this->config['driver'] ?? 'smswebservice');
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        $mobiles = array_values($this->normalizeMobiles($recipients));
        $sender = (int) $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null);

        return $this->map($this->request('POST', 'SendBulk', [
            'ApiKey' => (string) $this->config('api_key', ''),
            'MessageText' => $message,
            'Sender' => $sender,
            'RecipientNumbers' => $mobiles,
        ]));
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->send($recipients, $message, $options);
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        return $this->buildClient((string) ($config['base_url'] ?? 'https://api.sms-webservice.com/api/V3'));
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
        if ((string) $this->config('api_key', '') === '') {
            throw new SmsApiException(sprintf('[%s] api_key is not configured.', $this->getName()));
        }

        return $this->httpRequest(
            $method,
            $uri,
            $payload,
            isSuccessful: fn (array $decoded): bool => ($decoded['Success'] ?? false) === true
                || (int) ($decoded['Code'] ?? 1) === 0,
        );
    }

    /** @param  array<string, mixed>  $raw */
    protected function map(array $raw): SendResult
    {
        $success = ($raw['Success'] ?? false) === true || (int) ($raw['Code'] ?? 1) === 0;

        return $this->toResponse($raw, success: $success, message: (string) ($raw['Message'] ?? 'OK'));
    }
}
