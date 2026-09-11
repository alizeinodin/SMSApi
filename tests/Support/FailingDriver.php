<?php

namespace Alizeinodin\SmsApi\Tests\Support;

use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;

class FailingDriver extends ArrayDriver
{
    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        throw new SmsApiException('Provider down');
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        throw new SmsApiException('Provider down');
    }
}
