<?php

namespace Alizeinodin\SmsApi\Facades;

use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alizeinodin\SmsApi\Contracts\MessageDriver driver(?string $name = null)
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult send(string|array $recipients, string $message, array $options = [])
 * @method static \Alizeinodin\SmsApi\DTOs\SendResult sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = [])
 *
 * @see \Alizeinodin\SmsApi\SmsManager
 */
class Sms extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return SmsManager::class;
    }
}
