<?php

namespace Alizeinodin\SmsApi\Delivery;

final class DeliveryStatus
{
    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const UNDELIVERED = 'undelivered';

    public const REJECTED = 'rejected';

    public const EXPIRED = 'expired';

    public const UNKNOWN = 'unknown';

    /**
     * Map sms.ir deliveryState codes.
     *
     * @see https://sms.ir/rest-api/
     */
    public static function fromSmsIrState(?int $state): string
    {
        return match ($state) {
            1 => self::DELIVERED,
            2, 4, 7 => self::UNDELIVERED,
            3, 5 => self::PENDING,
            6 => self::REJECTED,
            default => self::UNKNOWN,
        };
    }

    /**
     * Map common Kavenegar status codes.
     */
    public static function fromKavenegarStatus(?int $status): string
    {
        return match ($status) {
            1, 2, 4, 5 => self::PENDING,
            10 => self::DELIVERED,
            11 => self::UNDELIVERED,
            6, 13, 14, 100 => self::REJECTED,
            default => self::UNKNOWN,
        };
    }

    public static function fromString(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'delivered', 'deliver', 'success', 'ok', '1' => self::DELIVERED,
            'undelivered', 'failed', 'fail', '2' => self::UNDELIVERED,
            'pending', 'sent', 'processing', '3', '5' => self::PENDING,
            'rejected', 'blacklist', 'blocked', '6', '7' => self::REJECTED,
            'expired' => self::EXPIRED,
            default => self::UNKNOWN,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::DELIVERED => 'Delivered',
            self::UNDELIVERED => 'Undelivered',
            self::REJECTED => 'Rejected',
            self::EXPIRED => 'Expired',
            self::UNKNOWN => 'Unknown',
        ];
    }
}
