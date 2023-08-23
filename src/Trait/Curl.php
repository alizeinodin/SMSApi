<?php

namespace Alizne\SmsApi\Trait;

use Alizne\SmsApi\Enum\RequestType;
use Exception;

trait Curl
{
    protected bool $sslVerifier = true;

    /**
     * @param bool $sslVerifier
     * @return void
     */
    private function setSslVerifier(bool $sslVerifier): void
    {
        $this->sslVerifier = $sslVerifier;
    }

    /**
     * @return bool
     */
    private function getSslVerifier(): bool
    {
        return $this->sslVerifier;
    }

    /**
     * @throws Exception
     */
    protected function requestCurl(
        $url,
        RequestType $method = RequestType::GET,
        array $headers = [],
        array $data = [],
    ): bool|string
    {
        $data = json_encode($data);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_CUSTOMREQUEST => $method->value,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerifier,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerifier,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
        ]);

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception(curl_error($ch));
        }

        curl_close($ch);
        return $result;
    }
}
