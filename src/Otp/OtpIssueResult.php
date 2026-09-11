<?php

namespace Alizeinodin\SmsApi\Otp;

use Alizeinodin\SmsApi\DTOs\SendResult;
use DateTimeInterface;

/**
 * Result of issuing an OTP (plaintext code only when reveal_code is enabled).
 */
final class OtpIssueResult
{
    public function __construct(
        public readonly SendResult $send,
        public readonly string|int $challengeId,
        public readonly string $recipient,
        public readonly string $purpose,
        public readonly DateTimeInterface $expiresAt,
        public readonly ?string $code = null,
    ) {}

    public function successful(): bool
    {
        return $this->send->success;
    }
}
