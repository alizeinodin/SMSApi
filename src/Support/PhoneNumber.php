<?php

namespace Alizeinodin\SmsApi\Support;

use Alizeinodin\SmsApi\Exceptions\InvalidPhoneNumberException;
use Illuminate\Support\Arr;

/**
 * Normalize Iranian mobile numbers to a consistent format before send.
 *
 * Accepts: 0912… / 912… / +98912… / 0098912… / 98912…
 */
final class PhoneNumber
{
    public const FORMAT_LOCAL = 'local';

    public const FORMAT_E164 = 'e164';

    public const FORMAT_DIGITS = 'digits';

    /**
     * @return array{enabled: bool, format: string, country_code: string, strict: bool}
     */
    public static function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'format' => self::FORMAT_LOCAL,
            'country_code' => '98',
            'strict' => false,
        ];
    }

    /**
     * @param  array{enabled?: bool, format?: string, country_code?: string, strict?: bool}  $config
     *
     * @throws InvalidPhoneNumberException
     */
    public static function normalize(string $mobile, array $config = []): string
    {
        $config = array_merge(self::defaultConfig(), $config);

        if (! (bool) $config['enabled']) {
            return trim($mobile);
        }

        $format = (string) $config['format'];
        $country = preg_replace('/\D+/', '', (string) $config['country_code']) ?: '98';
        $strict = (bool) $config['strict'];

        $trimmed = trim($mobile);

        if ($trimmed === '') {
            if ($strict) {
                throw new InvalidPhoneNumberException($mobile, 'Phone number is empty.');
            }

            return $trimmed;
        }

        $national = self::toNationalDigits($trimmed, $country);

        if ($national === null) {
            if ($strict) {
                throw new InvalidPhoneNumberException($mobile);
            }

            return $trimmed;
        }

        return match ($format) {
            self::FORMAT_E164 => '+'.$country.$national,
            self::FORMAT_DIGITS => $country.$national,
            default => '0'.$national,
        };
    }

    /**
     * @param  string|array<int, string>  $mobiles
     * @param  array{enabled?: bool, format?: string, country_code?: string, strict?: bool}  $config
     * @return array<int, string>
     *
     * @throws InvalidPhoneNumberException
     */
    public static function normalizeMany(string|array $mobiles, array $config = []): array
    {
        $normalized = [];

        foreach (Arr::wrap($mobiles) as $mobile) {
            if (! is_scalar($mobile)) {
                continue;
            }

            $value = self::normalize((string) $mobile, $config);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values($normalized);
    }

    /**
     * National subscriber number without leading 0 / country code (e.g. 9123456789).
     */
    private static function toNationalDigits(string $mobile, string $country): ?string
    {
        $hasPlus = str_starts_with($mobile, '+');
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        // +98 / 0098 / 98 + 10-digit national
        if (str_starts_with($digits, $country) && strlen($digits) === strlen($country) + 10) {
            $national = substr($digits, strlen($country));
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $national = substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $national = $digits;
        } elseif ($hasPlus && str_starts_with($digits, $country) && strlen($digits) >= strlen($country) + 10) {
            $national = substr($digits, strlen($country), 10);
        } else {
            return null;
        }

        // Iranian mobiles: 9XXXXXXXXX
        if (! preg_match('/^9\d{9}$/', $national)) {
            return null;
        }

        return $national;
    }
}
