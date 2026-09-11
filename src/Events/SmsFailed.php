<?php

namespace Alizeinodin\SmsApi\Events;

use Throwable;

/**
 * Dispatched when an outbound SMS operation throws (provider / resilience / policy errors).
 *
 * Queued jobs that exhaust all retries also dispatch {@see SmsQueuedSendFailed}.
 */
class SmsFailed
{
    /**
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $options
     */
    public function __construct(
        public string $operation,
        public string $driver,
        public Throwable $exception,
        public string|array|null $mobiles = null,
        public int|string|null $templateId = null,
        public ?string $template = null,
        public ?string $messageType = null,
        public ?string $channel = null,
        public array $options = [],
    ) {}
}
