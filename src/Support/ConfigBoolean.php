<?php

namespace Alizeinodin\SmsApi\Support;

/**
 * Config helpers for package env flags (string "false" must not become true).
 */
final class ConfigBoolean
{
    public static function get(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $filtered ?? $default;
    }
}
