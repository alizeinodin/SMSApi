<?php

namespace Alizeinodin\SmsApi\DTOs;

final class SendResult
{
    /**
     * @param  array<string, mixed>|null  $data
     * @param  array<string, mixed>|null  $raw
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message = 'OK',
        public readonly mixed $data = null,
        public readonly ?string $driver = null,
        public readonly ?array $raw = null,
        public readonly ?int $statusCode = null,
        public readonly ?string $messageId = null,
    ) {}
}
