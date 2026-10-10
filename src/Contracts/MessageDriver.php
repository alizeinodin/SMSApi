<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

interface MessageDriver
{
    public function getName(): string;

    public function getProvider(): string;

    public function getChannel(): string;

    /**
     * @param  string|array<int, string>  $recipients
     * @param  array<string, mixed>  $options
     */
    public function send(string|array $recipients, string $message, array $options = []): SendResult;

    /**
     * @param  array<int, string>  $recipients
     * @param  array<string, mixed>  $options
     */
    public function bulk(string $message, array $recipients, array $options = []): SendResult;
}
