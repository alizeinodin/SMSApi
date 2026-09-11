<?php

namespace Alizeinodin\SmsApi\DTOs;

final class SendResult
{
    /**
     * @param  array<string, mixed>  $raw
     * @param  array<int, array{driver: string, message: string, code: int}>  $attempts
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly mixed $data = null,
        public readonly string $driver = '',
        public readonly array $raw = [],
        public readonly ?int $statusCode = null,
        public readonly array $attempts = [],
    ) {
    }

    /**
     * @param  array<int, array{driver: string, message: string, code: int}>  $attempts
     */
    public function withAttempts(array $attempts): self
    {
        return new self(
            success: $this->success,
            message: $this->message,
            data: $this->data,
            driver: $this->driver,
            raw: $this->raw,
            statusCode: $this->statusCode,
            attempts: $attempts,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'driver' => $this->driver,
            'raw' => $this->raw,
            'status_code' => $this->statusCode,
            'attempts' => $this->attempts,
        ];
    }
}
