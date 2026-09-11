<?php

namespace Alizeinodin\SmsApi\Otp;

final class OtpCheckResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly string $reason = 'ok',
        public readonly int $attemptsRemaining = 0,
        public readonly ?string $challengeId = null,
    ) {
    }

    public function ok(): bool
    {
        return $this->valid;
    }

    public static function success(string|int|null $challengeId = null): self
    {
        return new self(true, 'ok', 0, $challengeId !== null ? (string) $challengeId : null);
    }

    public static function invalid(string $reason, int $attemptsRemaining = 0, string|int|null $challengeId = null): self
    {
        return new self(false, $reason, $attemptsRemaining, $challengeId !== null ? (string) $challengeId : null);
    }
}
