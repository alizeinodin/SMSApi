<?php

namespace Alizeinodin\SmsApi\Events;

use Alizeinodin\SmsApi\DTOs\SendResult;

/**
 * Dispatched after an outbound SMS operation returns (including unsuccessful provider responses).
 */
class SmsSent
{
    /**
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $operation,
        public string $driver,
        public SendResult $response,
        public string|array|null $mobiles = null,
        public int|string|null $templateId = null,
        public ?string $template = null,
        public ?string $messageType = null,
        public ?string $channel = null,
        public array $options = [],
    ) {}
}
