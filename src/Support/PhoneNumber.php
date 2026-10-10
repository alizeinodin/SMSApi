<?php

namespace Alizeinodin\SmsApi\Support;

final class PhoneNumber
{
    /**
     * @return array{enabled: bool, format: string, country_code: string, strict: bool}
     */
    public static function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'format' => 'local',
            'country_code' => '98',
            'strict' => false,
        ];
    }

    /**
     * @param  string|array<int, string>  $mobiles
     * @param  array{enabled?: bool, format?: string, country_code?: string, strict?: bool}  $config
     * @return array<int, string>
     */
    public static function normalize(string|array $mobiles, array $config = []): array
    {
        $config = array_merge(self::defaultConfig(), $config);
        $list = is_array($mobiles) ? $mobiles : [$mobiles];
        $out = [];

        foreach ($list as $mobile) {
            $digits = preg_replace('/\D+/', '', (string) $mobile) ?? '';
            $cc = $config['country_code'] ?? '98';

            if (str_starts_with($digits, '00'.$cc)) {
                $digits = substr($digits, 2);
            }
            if (str_starts_with($digits, $cc) && strlen($digits) === strlen($cc) + 10) {
                $local = '0'.substr($digits, strlen($cc));
            } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
                $local = $digits;
            } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
                $local = '0'.$digits;
            } else {
                $local = (string) $mobile;
            }

            $out[] = match ($config['format'] ?? 'local') {
                'e164' => '+'.$cc.ltrim($local, '0'),
                'digits' => $cc.ltrim($local, '0'),
                default => $local,
            };
        }

        return $out;
    }
}
