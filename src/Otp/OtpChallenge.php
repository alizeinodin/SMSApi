<?php

namespace Alizeinodin\SmsApi\Otp;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persisted OTP challenge (HMAC hash only — never stores plaintext).
 *
 * @property int $id
 * @property string $recipient
 * @property string $purpose
 * @property string $code_hash
 * @property Carbon $expires_at
 * @property int $attempts
 * @property int $max_attempts
 * @property Carbon|null $consumed_at
 */
class OtpChallenge extends Model
{
    protected $table = 'sms_otp_challenges';

    protected $fillable = [
        'recipient',
        'purpose',
        'code_hash',
        'expires_at',
        'attempts',
        'max_attempts',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    protected $hidden = [
        'code_hash',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function hasAttemptsLeft(): bool
    {
        return $this->attempts < $this->max_attempts;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now());
    }

    public function scopeForRecipient(Builder $query, string $recipient, string $purpose): Builder
    {
        return $query
            ->where('recipient', $recipient)
            ->where('purpose', $purpose);
    }
}
