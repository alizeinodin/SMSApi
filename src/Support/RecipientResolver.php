<?php

namespace Alizeinodin\SmsApi\Support;

use Alizeinodin\SmsApi\Exceptions\InvalidPhoneNumberException;
use Alizeinodin\SmsApi\Exceptions\OtpException;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;

/**
 * Resolve mobile / email from a string or Notifiable-style object.
 * Shared by TemplateSend and OtpVault so delivery and challenge keys match.
 */
final class RecipientResolver
{
    /**
     * Prefer SMS route, then attributes, then email (for mail-only challenges).
     *
     * @throws SmsApiException
     */
    public static function forOtp(string|object $recipient): string
    {
        if (is_string($recipient)) {
            return self::normalizeString($recipient);
        }

        $mobile = self::extractMobile($recipient);
        if ($mobile !== null) {
            return PhoneNumber::normalize($mobile, (array) config('smsapi.phone', []));
        }

        $email = self::extractEmail($recipient);
        if ($email !== null) {
            return strtolower($email);
        }

        throw new OtpException('Unable to resolve a mobile number or email for OTP challenge.');
    }

    public static function extractMobile(string|object $recipient): ?string
    {
        if (is_string($recipient)) {
            $value = trim($recipient);

            if ($value === '' || self::looksLikeEmail($value)) {
                return null;
            }

            return $value;
        }

        if (method_exists($recipient, 'routeNotificationFor')) {
            $route = $recipient->routeNotificationFor('sms');

            if (is_string($route) && trim($route) !== '') {
                return trim($route);
            }
        }

        if (method_exists($recipient, 'routeNotificationForSms')) {
            try {
                $route = $recipient->routeNotificationForSms();
            } catch (\ArgumentCountError) {
                $route = null;
            }

            if (is_string($route) && trim($route) !== '') {
                return trim($route);
            }
        }

        foreach (['mobile', 'phone', 'phone_number'] as $attribute) {
            if (isset($recipient->{$attribute}) && is_scalar($recipient->{$attribute})) {
                $value = trim((string) $recipient->{$attribute});

                if ($value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    public static function extractEmail(string|object $recipient): ?string
    {
        if (is_string($recipient)) {
            $value = trim($recipient);

            return self::looksLikeEmail($value) ? strtolower($value) : null;
        }

        if (method_exists($recipient, 'routeNotificationFor')) {
            $route = $recipient->routeNotificationFor('mail');

            if (is_string($route) && trim($route) !== '') {
                return strtolower(trim($route));
            }

            if (is_array($route)) {
                foreach ($route as $email) {
                    if (is_string($email) && trim($email) !== '') {
                        return strtolower(trim($email));
                    }
                }
            }
        }

        if (method_exists($recipient, 'routeNotificationForMail')) {
            try {
                $route = $recipient->routeNotificationForMail();
            } catch (\ArgumentCountError) {
                $route = null;
            }

            if (is_string($route) && trim($route) !== '') {
                return strtolower(trim($route));
            }

            if (is_array($route)) {
                foreach ($route as $email) {
                    if (is_string($email) && trim($email) !== '') {
                        return strtolower(trim($email));
                    }
                }
            }
        }

        foreach (['email', 'mail', 'email_address'] as $attribute) {
            if (isset($recipient->{$attribute}) && is_scalar($recipient->{$attribute})) {
                $value = trim((string) $recipient->{$attribute});

                if ($value !== '' && self::looksLikeEmail($value)) {
                    return strtolower($value);
                }
            }
        }

        return null;
    }

    public static function looksLikeEmail(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * @throws OtpException|InvalidPhoneNumberException
     */
    private static function normalizeString(string $recipient): string
    {
        $value = trim($recipient);

        if ($value === '') {
            throw new OtpException('OTP recipient cannot be empty.');
        }

        if (self::looksLikeEmail($value)) {
            return strtolower($value);
        }

        return PhoneNumber::normalize($value, (array) config('smsapi.phone', []));
    }
}
