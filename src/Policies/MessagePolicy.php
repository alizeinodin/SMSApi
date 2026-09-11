<?php

namespace Alizeinodin\SmsApi\Policies;

use Alizeinodin\SmsApi\Exceptions\OutsideAllowedHoursException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Named send policy (otp / marketing / transactional, …).
 */
final class MessagePolicy
{
    /**
     * @param  array<int, string>|null  $failover
     * @param  array{start?: int|string, end?: int|string}|array{0?: int|string, 1?: int|string}|null  $allowedHours
     * @param  array{start?: int|string, end?: int|string}|array{0?: int|string, 1?: int|string}|null  $quietHours
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $driver = null,
        public readonly ?array $failover = null,
        public readonly ?string $queue = null,
        public readonly ?string $rateLimit = null,
        public readonly ?array $allowedHours = null,
        public readonly ?array $quietHours = null,
        public readonly ?string $timezone = null,
    ) {
    }

    /**
     * Rate-limit / retry channel name (defaults to the message type).
     */
    public function channel(): string
    {
        if ($this->rateLimit !== null && $this->rateLimit !== '') {
            return $this->rateLimit;
        }

        return $this->type;
    }

    public function hasWindow(): bool
    {
        return $this->normalizeWindow($this->allowedHours) !== null
            || $this->normalizeWindow($this->quietHours) !== null;
    }

    /**
     * @throws OutsideAllowedHoursException
     */
    public function assertWithinAllowedWindow(?DateTimeInterface $at = null): void
    {
        if (! $this->hasWindow()) {
            return;
        }

        $now = $this->now($at);

        if (! $this->isWithinAllowedWindow($now)) {
            throw OutsideAllowedHoursException::forPolicy($this, $now);
        }
    }

    public function isWithinAllowedWindow(?DateTimeInterface $at = null): bool
    {
        if (! $this->hasWindow()) {
            return true;
        }

        $now = $this->now($at);
        $minuteOfDay = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        $allowed = $this->normalizeWindow($this->allowedHours);
        if ($allowed !== null) {
            return $this->minuteInRange($minuteOfDay, $allowed['start'], $allowed['end']);
        }

        $quiet = $this->normalizeWindow($this->quietHours);
        if ($quiet !== null) {
            // Quiet hours = blocked; send only when outside that range.
            return ! $this->minuteInRange($minuteOfDay, $quiet['start'], $quiet['end']);
        }

        return true;
    }

    /**
     * @return array{start: int, end: int}|null  Minutes from midnight
     */
    public function resolvedAllowedRange(): ?array
    {
        return $this->normalizeWindow($this->allowedHours);
    }

    /**
     * @return array{start: int, end: int}|null
     */
    public function resolvedQuietRange(): ?array
    {
        return $this->normalizeWindow($this->quietHours);
    }

    private function now(?DateTimeInterface $at): DateTimeImmutable
    {
        $tz = $this->timezone !== null && $this->timezone !== ''
            ? new DateTimeZone($this->timezone)
            : null;

        if ($at === null) {
            return new DateTimeImmutable('now', $tz);
        }

        $immutable = DateTimeImmutable::createFromInterface($at);

        return $tz !== null ? $immutable->setTimezone($tz) : $immutable;
    }

    /**
     * @param  array{start?: int|string, end?: int|string}|array{0?: int|string, 1?: int|string}|null  $window
     * @return array{start: int, end: int}|null
     */
    private function normalizeWindow(?array $window): ?array
    {
        if ($window === null || $window === []) {
            return null;
        }

        $startRaw = $window['start'] ?? $window[0] ?? null;
        $endRaw = $window['end'] ?? $window[1] ?? null;

        if ($startRaw === null || $endRaw === null) {
            return null;
        }

        $start = $this->toMinuteOfDay($startRaw);
        $end = $this->toMinuteOfDay($endRaw);

        if ($start === null || $end === null) {
            return null;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function toMinuteOfDay(int|string $value): ?int
    {
        if (is_int($value)) {
            if ($value < 0 || $value > 23) {
                return null;
            }

            return $value * 60;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $hour = (int) $value;

            return ($hour >= 0 && $hour <= 23) ? $hour * 60 : null;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m) !== 1) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return $hour * 60 + $minute;
    }

    private function minuteInRange(int $minute, int $start, int $end): bool
    {
        if ($start === $end) {
            return true; // full day
        }

        if ($start < $end) {
            return $minute >= $start && $minute < $end;
        }

        // Overnight window (e.g. 21:00 → 09:00)
        return $minute >= $start || $minute < $end;
    }
}
