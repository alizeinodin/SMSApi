<?php

namespace Alizeinodin\SmsApi\Otp;

interface OtpStore
{
    /**
     * Invalidate previous active challenges and create a new one.
     */
    public function create(
        string $recipient,
        string $purpose,
        string $codeHash,
        \DateTimeInterface $expiresAt,
        int $maxAttempts,
    ): object;

    /**
     * Latest non-consumed challenge for recipient+purpose (may be expired).
     */
    public function findLatest(string $recipient, string $purpose): ?object;

    public function incrementAttempts(object $challenge): object;

    /**
     * Atomically consume. Returns false if already consumed / expired / missing.
     */
    public function consume(object $challenge): bool;

    public function invalidate(string $recipient, string $purpose): int;
}
