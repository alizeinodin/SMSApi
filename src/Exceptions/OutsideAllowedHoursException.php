<?php

namespace Alizeinodin\SmsApi\Exceptions;

use Alizeinodin\SmsApi\Policies\MessagePolicy;
use DateTimeInterface;

class OutsideAllowedHoursException extends SmsApiException
{
    public function __construct(
        public readonly string $messageType,
        public readonly string $at,
        public readonly ?string $timezone = null,
        public readonly ?array $allowedHours = null,
        public readonly ?array $quietHours = null,
    ) {
        $tz = $timezone !== null && $timezone !== '' ? " ({$timezone})" : '';

        parent::__construct(
            sprintf(
                'SMS message type [%s] cannot be sent at %s%s — outside the configured send window.',
                $messageType,
                $at,
                $tz,
            ),
            422,
            [
                'message_type' => $messageType,
                'at' => $at,
                'timezone' => $timezone,
                'allowed_hours' => $allowedHours,
                'quiet_hours' => $quietHours,
            ],
        );
    }

    public static function forPolicy(MessagePolicy $policy, DateTimeInterface $at): self
    {
        return new self(
            messageType: $policy->type,
            at: $at->format('Y-m-d H:i:s'),
            timezone: $policy->timezone,
            allowedHours: $policy->resolvedAllowedRange(),
            quietHours: $policy->resolvedQuietRange(),
        );
    }
}
