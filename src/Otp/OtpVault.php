<?php

namespace Alizeinodin\SmsApi\Otp;

use Alizeinodin\SmsApi\Exceptions\InvalidOtpException;
use Alizeinodin\SmsApi\Exceptions\OtpAttemptsExceededException;
use Alizeinodin\SmsApi\Exceptions\OtpException;
use Alizeinodin\SmsApi\Exceptions\OtpExpiredException;
use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Support\ConfigBoolean;
use Alizeinodin\SmsApi\Support\RecipientResolver;
use DateTimeInterface;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Carbon;

/**
 * Optional OTP vault: generate → HMAC-store → check (delivery is separate via OtpIssue).
 *
 * Does not replace Sms::otp()/template() delivery; it adds auth-style challenge lifecycle.
 */
class OtpVault
{
    public function __construct(
        private OtpStore $store,
        private RateLimiter $limiter,
        private array $config = [],
    ) {
    }

    public function enabled(): bool
    {
        return ConfigBoolean::get($this->config['enabled'] ?? false);
    }

    public function assertEnabled(): void
    {
        if (! $this->enabled()) {
            throw new OtpException(
                'OTP vault is disabled. Enable it with `php artisan smsapi:install otp` or set SMSAPI_OTP_VAULT_ENABLED=true.'
            );
        }
    }

    /**
     * @return array{length: int, ttl: int, max_attempts: int, code_parameter: string, reveal_code: bool, allow_queue: bool}
     */
    public function settings(): array
    {
        return [
            'length' => max(4, min(8, (int) ($this->config['length'] ?? 5))),
            'ttl' => max(30, (int) ($this->config['ttl'] ?? 300)),
            'max_attempts' => max(1, (int) ($this->config['max_attempts'] ?? 5)),
            'code_parameter' => (string) ($this->config['code_parameter'] ?? 'CODE'),
            'reveal_code' => ConfigBoolean::get($this->config['reveal_code'] ?? false),
            'allow_queue' => ConfigBoolean::get($this->config['allow_queue'] ?? false),
        ];
    }

    public function generateCode(?int $length = null): string
    {
        $length = $length ?? $this->settings()['length'];
        $length = max(4, min(8, $length));
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }

    public function hash(string $code, string $recipient, string $purpose): string
    {
        $payload = strtolower($recipient) . '|' . $purpose . '|' . $code;

        return hash_hmac('sha256', $payload, $this->pepper());
    }

    public function normalizeRecipient(string|object $recipient): string
    {
        return RecipientResolver::forOtp($recipient);
    }

    /**
     * Store a new challenge (does not send). Invalidates previous active codes for same pair.
     *
     * @return array{challenge: object, code: string, expires_at: DateTimeInterface}
     */
    public function store(
        string|object $recipient,
        string $purpose,
        ?string $code = null,
        ?int $length = null,
        ?int $ttl = null,
        ?int $maxAttempts = null,
    ): array {
        $this->assertEnabled();

        $normalized = $this->normalizeRecipient($recipient);
        $purpose = $this->normalizePurpose($purpose);
        $this->throttle($normalized, $purpose);

        $settings = $this->settings();
        $code = trim((string) ($code ?? $this->generateCode($length ?? $settings['length'])));
        $ttl ??= $settings['ttl'];
        $maxAttempts ??= $settings['max_attempts'];
        $expiresAt = now()->addSeconds($ttl);
        $hash = $this->hash($code, $normalized, $purpose);

        $challenge = $this->store->create(
            $normalized,
            $purpose,
            $hash,
            $expiresAt,
            $maxAttempts,
        );

        return [
            'challenge' => $challenge,
            'code' => $code,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * Verify user input. Consumes on success. Increments attempts on failure.
     *
     * @throws OtpException
     * @throws OtpExpiredException
     * @throws OtpAttemptsExceededException
     * @throws InvalidOtpException
     */
    public function check(
        string|object $recipient,
        string $code,
        string $purpose,
        bool $throw = true,
    ): OtpCheckResult {
        $this->assertEnabled();

        $normalized = $this->normalizeRecipient($recipient);
        $purpose = $this->normalizePurpose($purpose);
        $code = trim($code);

        $challenge = $this->store->findLatest($normalized, $purpose);

        if ($challenge === null) {
            return $this->fail($throw, OtpCheckResult::invalid('not_found'), new OtpException('No OTP challenge found.'));
        }

        $expiresAt = $challenge->expires_at instanceof DateTimeInterface
            ? Carbon::instance(\DateTimeImmutable::createFromInterface($challenge->expires_at))
            : Carbon::parse((string) $challenge->expires_at);

        if ($expiresAt->isPast()) {
            return $this->fail(
                $throw,
                OtpCheckResult::invalid('expired', 0, $challenge->id ?? null),
                new OtpExpiredException(),
            );
        }

        if ((int) $challenge->attempts >= (int) $challenge->max_attempts) {
            return $this->fail(
                $throw,
                OtpCheckResult::invalid('attempts_exceeded', 0, $challenge->id ?? null),
                new OtpAttemptsExceededException(),
            );
        }

        $expected = (string) $challenge->code_hash;
        $actual = $this->hash($code, $normalized, $purpose);

        if (! hash_equals($expected, $actual)) {
            $challenge = $this->store->incrementAttempts($challenge);
            $remaining = max(0, (int) $challenge->max_attempts - (int) $challenge->attempts);

            if ($remaining === 0) {
                return $this->fail(
                    $throw,
                    OtpCheckResult::invalid('attempts_exceeded', 0, $challenge->id ?? null),
                    new OtpAttemptsExceededException(),
                );
            }

            return $this->fail(
                $throw,
                OtpCheckResult::invalid('mismatch', $remaining, $challenge->id ?? null),
                new InvalidOtpException('Invalid OTP code.', $remaining),
            );
        }

        if (! $this->store->consume($challenge)) {
            return $this->fail(
                $throw,
                OtpCheckResult::invalid('already_consumed', 0, $challenge->id ?? null),
                new OtpException('OTP was already used or could not be consumed.'),
            );
        }

        return OtpCheckResult::success($challenge->id ?? null);
    }

    public function invalidate(string|object $recipient, string $purpose): int
    {
        $this->assertEnabled();

        return $this->store->invalidate(
            $this->normalizeRecipient($recipient),
            $this->normalizePurpose($purpose),
        );
    }

    private function pepper(): string
    {
        $pepper = $this->config['pepper'] ?? null;

        if (is_string($pepper) && $pepper !== '') {
            return $pepper;
        }

        $appKey = (string) config('app.key', '');

        if ($appKey === '') {
            throw new OtpException('OTP vault requires app.key or smsapi.otp.vault.pepper.');
        }

        return $appKey;
    }

    private function normalizePurpose(string $purpose): string
    {
        $purpose = trim($purpose);

        if ($purpose === '') {
            throw new OtpException('OTP purpose cannot be empty.');
        }

        return $purpose;
    }

    /**
     * @throws RateLimitExceededException
     */
    private function throttle(string $recipient, string $purpose): void
    {
        $throttle = (array) ($this->config['throttle'] ?? []);

        if (! ConfigBoolean::get($throttle['enabled'] ?? true, true)) {
            return;
        }

        $max = (int) ($throttle['max'] ?? 3);
        $perSeconds = max(1, (int) ($throttle['per_seconds'] ?? 60));

        if ($max <= 0) {
            return;
        }

        $perPurpose = ConfigBoolean::get($throttle['per_purpose'] ?? true, true);
        $key = $perPurpose
            ? 'smsapi:otp:throttle:' . hash('sha256', $purpose . '|' . $recipient)
            : 'smsapi:otp:throttle:' . hash('sha256', $recipient);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            throw new RateLimitExceededException(
                'otp_recipient',
                $perPurpose ? $purpose : 'recipient',
                $this->limiter->availableIn($key),
            );
        }

        $this->limiter->hit($key, $perSeconds);
    }

    /**
     * @throws OtpException
     */
    private function fail(bool $throw, OtpCheckResult $result, OtpException $exception): OtpCheckResult
    {
        if ($throw) {
            throw $exception;
        }

        return $result;
    }
}
