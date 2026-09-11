<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

/**
 * Common outbound surface for SMS providers and messengers.
 */
interface MessageDriver
{
    public function getName(): string;

    /**
     * Underlying transport type (e.g. smsir, telegram).
     * Named connections keep getName() as the connection name.
     */
    public function getProvider(): string;

    /**
     * Logical channel: sms, telegram, whatsapp, bale, …
     */
    public function getChannel(): string;

    /**
     * @param  string|array<int, string>  $recipients  Mobiles or chat/user ids
     * @param  array<string, mixed>  $options
     */
    public function send(string|array $recipients, string $message, array $options = []): SendResult;

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $options
     */
    public function bulk(string $message, array $recipients, array $options = []): SendResult;
}
