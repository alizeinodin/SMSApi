<?php

namespace Alizeinodin\SmsApi\Support;

/**
 * Strip API keys, bot tokens, and credential URLs from exception text.
 */
final class SecretRedactor
{
    /**
     * @param  list<string>  $secrets
     */
    public function __construct(private array $secrets = []) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(array $config): self
    {
        $keys = [
            'api_key', 'api_token', 'token', 'bot_token', 'password',
            'access_token', 'page_access_token', 'auth_token',
            'channel_access_token', 'secret',
        ];

        $secrets = [];

        foreach ($keys as $key) {
            $value = $config[$key] ?? null;

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);

            if (strlen($value) < 6) {
                continue;
            }

            $secrets[] = $value;
            $encoded = rawurlencode($value);

            if ($encoded !== $value) {
                $secrets[] = $encoded;
            }
        }

        return new self(array_values(array_unique($secrets)));
    }

    public function redact(string $text): string
    {
        foreach ($this->secrets as $secret) {
            $text = str_replace($secret, '[redacted]', $text);
        }

        return self::scrub($text);
    }

    public static function scrub(string $text): string
    {
        $text = preg_replace('#https?://\S+#i', '[redacted-url]', $text) ?? $text;
        $text = preg_replace('#(?<=/bot)[0-9]+:[A-Za-z0-9_-]{8,}#', '[redacted]', $text) ?? $text;
        $text = preg_replace('#([?&](?:token|api_key|apikey|access_token|bot_token|password)=)[^&\s]+#i', '$1[redacted]', $text) ?? $text;

        return $text;
    }
}
