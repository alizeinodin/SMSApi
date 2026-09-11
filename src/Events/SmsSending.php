<?php

namespace Alizeinodin\SmsApi\Events;

/**
 * Dispatched immediately before an outbound SMS operation (send / bulk / sendMapped / sendTemplate).
 */
class SmsSending
{
    /**
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $operation,
        public string $driver,
        public string|array|null $mobiles = null,
        public int|string|null $templateId = null,
        public ?string $template = null,
        public ?string $messageType = null,
        public ?string $channel = null,
        public array $options = [],
    ) {
    }
}
