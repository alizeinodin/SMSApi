<?php

namespace Alizeinodin\SmsApi\Otp;

use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * Cache-backed OTP store (no migration). One active challenge per recipient+purpose.
 */
class CacheOtpStore implements OtpStore
{
    public function __construct(
        private CacheRepository $cache,
        private string $prefix = 'smsapi:otp',
    ) {}

    public function create(
        string $recipient,
        string $purpose,
        string $codeHash,
        DateTimeInterface $expiresAt,
        int $maxAttempts,
    ): object {
        return $this->lock($recipient, $purpose, function () use ($recipient, $purpose, $codeHash, $expiresAt, $maxAttempts) {
            $this->invalidateUnlocked($recipient, $purpose);

            $challenge = $this->hydrate([
                'id' => uniqid('otp_', true),
                'recipient' => $recipient,
                'purpose' => $purpose,
                'code_hash' => $codeHash,
                'expires_at' => Carbon::instance(\DateTimeImmutable::createFromInterface($expiresAt))->toIso8601String(),
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'consumed_at' => null,
            ]);

            $this->persist($challenge);

            return $challenge;
        });
    }

    public function findLatest(string $recipient, string $purpose): ?object
    {
        $payload = $this->cache->get($this->key($recipient, $purpose));

        if (! is_array($payload)) {
            return null;
        }

        $challenge = $this->hydrate($payload);

        if ($challenge->consumed_at !== null) {
            return null;
        }

        return $challenge;
    }

    public function incrementAttempts(object $challenge): object
    {
        return $this->lock((string) $challenge->recipient, (string) $challenge->purpose, function () use ($challenge) {
            $fresh = $this->findLatest((string) $challenge->recipient, (string) $challenge->purpose);

            if ($fresh === null || (string) $fresh->id !== (string) $challenge->id) {
                return $challenge;
            }

            $fresh->attempts = (int) $fresh->attempts + 1;
            $this->persist($fresh);

            return $fresh;
        });
    }

    public function consume(object $challenge): bool
    {
        return $this->lock((string) $challenge->recipient, (string) $challenge->purpose, function () use ($challenge) {
            $fresh = $this->findLatest((string) $challenge->recipient, (string) $challenge->purpose);

            if ($fresh === null || (string) $fresh->id !== (string) $challenge->id) {
                return false;
            }

            $expiresAt = $fresh->expires_at instanceof DateTimeInterface
                ? Carbon::instance(\DateTimeImmutable::createFromInterface($fresh->expires_at))
                : Carbon::parse((string) $fresh->expires_at);

            if ($expiresAt->isPast()) {
                return false;
            }

            $fresh->consumed_at = now();
            $this->persist($fresh);

            return true;
        });
    }

    public function invalidate(string $recipient, string $purpose): int
    {
        return $this->lock($recipient, $purpose, function () use ($recipient, $purpose) {
            return $this->invalidateUnlocked($recipient, $purpose);
        });
    }

    private function invalidateUnlocked(string $recipient, string $purpose): int
    {
        $existing = $this->findLatest($recipient, $purpose);

        if ($existing === null) {
            $this->cache->forget($this->key($recipient, $purpose));

            return 0;
        }

        $existing->consumed_at = now();
        $this->persist($existing);

        return 1;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function lock(string $recipient, string $purpose, callable $callback): mixed
    {
        $lock = $this->cache->lock($this->prefix.':lock:'.hash('sha256', $purpose.'|'.$recipient), 5);

        try {
            return $lock->block(5, $callback);
        } catch (\Throwable) {
            // File/array cache may not support locks — fall back to unlocked path.
            return $callback();
        }
    }

    private function persist(object $challenge): void
    {
        $expiresAt = $challenge->expires_at instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($challenge->expires_at))
            : Carbon::parse((string) $challenge->expires_at);

        $ttl = max(1, $expiresAt->getTimestamp() - time() + 60);
        $this->cache->put(
            $this->key((string) $challenge->recipient, (string) $challenge->purpose),
            $this->toArray($challenge),
            $ttl,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hydrate(array $payload): object
    {
        $challenge = new stdClass;
        $challenge->id = $payload['id'];
        $challenge->recipient = (string) $payload['recipient'];
        $challenge->purpose = (string) $payload['purpose'];
        $challenge->code_hash = (string) $payload['code_hash'];
        $challenge->expires_at = Carbon::parse((string) $payload['expires_at']);
        $challenge->attempts = (int) $payload['attempts'];
        $challenge->max_attempts = (int) $payload['max_attempts'];
        $challenge->consumed_at = isset($payload['consumed_at']) && $payload['consumed_at'] !== null
            ? Carbon::parse((string) $payload['consumed_at'])
            : null;

        return $challenge;
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(object $challenge): array
    {
        $expiresAt = $challenge->expires_at instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($challenge->expires_at))->toIso8601String()
            : (string) $challenge->expires_at;

        $consumedAt = null;
        if ($challenge->consumed_at instanceof DateTimeInterface) {
            $consumedAt = Carbon::instance(\DateTimeImmutable::createFromInterface($challenge->consumed_at))->toIso8601String();
        } elseif ($challenge->consumed_at !== null) {
            $consumedAt = (string) $challenge->consumed_at;
        }

        return [
            'id' => $challenge->id,
            'recipient' => $challenge->recipient,
            'purpose' => $challenge->purpose,
            'code_hash' => $challenge->code_hash,
            'expires_at' => $expiresAt,
            'attempts' => (int) $challenge->attempts,
            'max_attempts' => (int) $challenge->max_attempts,
            'consumed_at' => $consumedAt,
        ];
    }

    private function key(string $recipient, string $purpose): string
    {
        return $this->prefix.':'.hash('sha256', $purpose.'|'.$recipient);
    }
}
