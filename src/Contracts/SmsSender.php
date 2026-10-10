<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

/**
 * Outbound SMS — the surface most custom SMS providers need to implement.
 */
interface SmsSender extends MessageDriver
{
    /**
     * @param  array<int, string>  $messages
     * @param  array<int, string>  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult;

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @param  array<string, mixed>  $options
     */
    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult;
}
