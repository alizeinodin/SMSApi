<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

interface SmsDriver extends MessageDriver
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $options
     */
    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult;
}
