<?php

namespace Alizeinodin\SmsApi\Support;

/**
 * Redact sensitive OTP / template parameters before persistence.
 */
final class SensitiveData
{
    /**
     * @param  array<string, mixed>|list<mixed>|null  $parameters
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|list<mixed>|null
     */
    public static function redactParameters(?array $parameters, array $keys = [], string $replacement = '[redacted]'): ?array
    {
        if ($parameters === null) {
            return null;
        }

        $keys = $keys !== [] ? $keys : self::defaultKeys();
        $lookup = array_fill_keys(array_map('strtolower', $keys), true);

        if ($parameters === []) {
            return [];
        }

        // Named list: [['name' => 'CODE', 'value' => '1234'], ...]
        if (array_is_list($parameters) && isset($parameters[0]) && is_array($parameters[0]) && array_key_exists('name', $parameters[0])) {
            return array_map(static function ($item) use ($lookup, $replacement) {
                if (! is_array($item)) {
                    return $item;
                }

                $name = strtolower((string) ($item['name'] ?? ''));

                if (isset($lookup[$name])) {
                    $item['value'] = $replacement;
                }

                return $item;
            }, $parameters);
        }

        $redacted = [];

        foreach ($parameters as $key => $value) {
            if (is_string($key) && isset($lookup[strtolower($key)])) {
                $redacted[$key] = $replacement;
            } elseif (is_array($value)) {
                $redacted[$key] = self::redactParameters($value, $keys, $replacement) ?? $value;
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    /**
     * Deep-redact nested arrays (e.g. provider response payloads).
     *
     * @param  array<string, mixed>|list<mixed>|null  $payload
     * @param  array<int, string>  $keys
     * @return array<string, mixed>|list<mixed>|null
     */
    public static function redactArray(?array $payload, array $keys = [], string $replacement = '[redacted]'): ?array
    {
        return self::redactParameters($payload, $keys, $replacement);
    }

    /**
     * @param  array<int, string>  $keys
     */
    public static function redactContent(?string $content, array $keys = [], string $replacement = '[redacted]'): ?string
    {
        if ($content === null || $content === '') {
            return $content;
        }

        $keys = $keys !== [] ? $keys : self::defaultKeys();

        foreach ($keys as $key) {
            $pattern = '/\b'.preg_quote($key, '/').'\b\s*[:=]\s*\S+/iu';
            $content = preg_replace($pattern, $key.'='.$replacement, $content) ?? $content;
        }

        // Best-effort: short messages that are mostly an OTP digit run.
        if (mb_strlen($content) <= 80) {
            $content = preg_replace('/(?<!\d)(\d{4,8})(?!\d)/u', $replacement, $content) ?? $content;
        }

        return $content;
    }

    /**
     * @return list<string>
     */
    public static function defaultKeys(): array
    {
        return [
            'CODE', 'code', 'otp', 'OTP', 'token', 'Token', 'token2', 'token3',
            'password', 'pass', 'pin', 'PIN',
        ];
    }
}
