<?php

namespace Alizeinodin\SmsApi\Otp;

use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class DatabaseOtpStore implements OtpStore
{
    public function create(
        string $recipient,
        string $purpose,
        string $codeHash,
        DateTimeInterface $expiresAt,
        int $maxAttempts,
    ): object {
        return DB::transaction(function () use ($recipient, $purpose, $codeHash, $expiresAt, $maxAttempts) {
            OtpChallenge::query()
                ->forRecipient($recipient, $purpose)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->get()
                ->each(function (OtpChallenge $challenge): void {
                    $challenge->consumed_at = now();
                    $challenge->save();
                });

            return OtpChallenge::query()->create([
                'recipient' => $recipient,
                'purpose' => $purpose,
                'code_hash' => $codeHash,
                'expires_at' => $expiresAt,
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'consumed_at' => null,
            ]);
        });
    }

    public function findLatest(string $recipient, string $purpose): ?object
    {
        return OtpChallenge::query()
            ->forRecipient($recipient, $purpose)
            ->whereNull('consumed_at')
            ->orderByDesc('id')
            ->first();
    }

    public function incrementAttempts(object $challenge): object
    {
        /** @var OtpChallenge $challenge */
        $updated = OtpChallenge::query()
            ->whereKey($challenge->id)
            ->whereNull('consumed_at')
            ->increment('attempts');

        if ($updated === 0) {
            return $challenge->fresh() ?? $challenge;
        }

        return $challenge->fresh() ?? $challenge;
    }

    public function consume(object $challenge): bool
    {
        /** @var OtpChallenge $challenge */
        $updated = OtpChallenge::query()
            ->whereKey($challenge->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->update(['consumed_at' => now()]);

        return $updated > 0;
    }

    public function invalidate(string $recipient, string $purpose): int
    {
        return OtpChallenge::query()
            ->forRecipient($recipient, $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);
    }
}
