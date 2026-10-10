<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\DTOs\SendResult;
use GuzzleHttp\Client;

/**
 * Farazsms uses the IPPanel API under the hood (official Faraz/IPPanel stack).
 *
 * @see https://github.com/IPPanel/php-rest-sdk
 */
class FarazsmsDriver extends IppanelDriver
{
    public function getName(): string
    {
        return (string) ($this->config['name'] ?? 'farazsms');
    }

    /** @param  array<string, mixed>  $config */
    protected function makeClient(array $config): Client
    {
        // Prefer explicit faraz base_url; otherwise same as IPPanel api2.
        $config['base_url'] = $config['base_url'] ?? 'https://api2.ippanel.com/api/v1';

        return parent::makeClient($config);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        // Official IPPanel pattern send
        $values = [];
        foreach ($parameters as $key => $value) {
            $values[is_array($value) ? (string) ($value['name'] ?? $key) : (string) $key] =
                is_array($value) ? (string) ($value['value'] ?? '') : (string) $value;
        }

        return $this->map($this->request('POST', 'sms/pattern/normal/send', [
            'code' => (string) $templateId,
            'sender' => $this->resolveLine(isset($options['lineNumber']) ? (string) $options['lineNumber'] : null),
            'recipient' => $this->normalizeMobiles($mobile)[0] ?? $mobile,
            'variable' => $values,
        ]));
    }
}
