<?php

namespace Alizeinodin\SmsApi\Tests\Integration\Sandbox;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Dotenv\Dotenv;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Live panel tests. Credentials come from `.env.sandbox` (see `.env.sandbox.example`).
 * Skipped automatically when required env vars are missing.
 *
 * Run: vendor/bin/phpunit --group sandbox
 */
#[Group('sandbox')]
class PanelSandboxTest extends TestCase
{
    private static bool $envLoaded = false;

    protected function setUp(): void
    {
        parent::setUp();
        self::loadSandboxEnv();
    }

    public function test_smsir_sandbox_verify(): void
    {
        $apiKey = self::env('SMSAPI_API_KEY');
        $mobile = self::env('SMSAPI_SANDBOX_MOBILE');

        if ($apiKey === null || $mobile === null) {
            $this->markTestSkipped('Set SMSAPI_API_KEY (Sandbox) and SMSAPI_SANDBOX_MOBILE in .env.sandbox');
        }

        $driver = DriverRegistry::make('smsir', [
            'api_key' => $apiKey,
            'line_number' => self::env('SMSAPI_LINE_NUMBER'),
            'base_url' => self::env('SMSAPI_BASE_URL') ?? 'https://api.sms.ir/v1',
        ]);

        $this->assertInstanceOf(SmsIrDriver::class, $driver);

        /** @var SmsIrDriver $driver */
        $result = $driver->sendSandboxVerify($mobile, '12345');

        $this->assertTrue($result->success, $result->message.' '.json_encode($result->raw));
        $this->assertSame('smsir', $result->driver);
    }

    /**
     * @param  array<int, string>  $requiredEnv
     * @param  array<string, string>  $configMap  configKey => envKey
     */
    #[DataProvider('panelDriverProvider')]
    public function test_panel_send_or_template(
        string $driver,
        array $requiredEnv,
        array $configMap,
        string $mode,
    ): void {
        foreach ($requiredEnv as $key) {
            if (self::env($key) === null) {
                $this->markTestSkipped(sprintf(
                    'Skipping [%s]: missing %s (copy .env.sandbox.example → .env.sandbox)',
                    $driver,
                    $key,
                ));
            }
        }

        $mobile = self::env('SMSAPI_SANDBOX_MOBILE');
        if ($mobile === null) {
            $this->markTestSkipped('Set SMSAPI_SANDBOX_MOBILE in .env.sandbox');
        }

        $config = ['driver' => $driver, 'name' => $driver];
        foreach ($configMap as $configKey => $envKey) {
            $value = self::env($envKey);
            if ($value !== null) {
                $config[$configKey] = $value;
            }
        }

        /** @var SmsDriver $instance */
        $instance = DriverRegistry::make($driver, $config);

        if ($mode === 'template') {
            $template = self::env(strtoupper($driver).'_TEMPLATE')
                ?? self::env('KAVENEGAR_TEMPLATE')
                ?? self::env('GHASEDAK_TEMPLATE')
                ?? self::env('FARAZSMS_TEMPLATE')
                ?? self::env('IPPANEL_TEMPLATE');

            if ($template === null || $template === '') {
                $this->markTestSkipped(sprintf('[%s] needs a template env (e.g. %s_TEMPLATE)', $driver, strtoupper($driver)));
            }

            $result = $instance->sendTemplate($mobile, $template, [
                'Code' => '12345',
                'token' => '12345',
                'code' => '12345',
            ]);
        } else {
            $result = $instance->send($mobile, 'sandbox test from alizeinodin/smsapi');
        }

        $this->assertTrue(
            $result->success,
            sprintf('[%s] failed: %s | raw=%s', $driver, $result->message, json_encode($result->raw)),
        );
        $this->assertSame($driver, $result->driver);
    }

    /**
     * @return array<string, array{0: string, 1: array<int, string>, 2: array<string, string>, 3: string}>
     */
    public static function panelDriverProvider(): array
    {
        $api = static fn (string $prefix): array => [
            "{$prefix}_API_KEY",
            "{$prefix}_LINE_NUMBER",
        ];

        $user = static fn (string $prefix): array => [
            "{$prefix}_USERNAME",
            "{$prefix}_PASSWORD",
            "{$prefix}_LINE_NUMBER",
        ];

        return [
            // smsir covered by dedicated sandbox verify test
            'kavenegar' => [
                'kavenegar',
                ['KAVENEGAR_API_KEY', 'KAVENEGAR_LINE_NUMBER', 'KAVENEGAR_TEMPLATE'],
                ['api_key' => 'KAVENEGAR_API_KEY', 'line_number' => 'KAVENEGAR_LINE_NUMBER'],
                'template',
            ],
            'ghasedak' => [
                'ghasedak',
                ['GHASEDAK_API_KEY', 'GHASEDAK_LINE_NUMBER', 'GHASEDAK_TEMPLATE'],
                ['api_key' => 'GHASEDAK_API_KEY', 'line_number' => 'GHASEDAK_LINE_NUMBER'],
                'template',
            ],
            'farazsms' => [
                'farazsms',
                ['FARAZSMS_API_KEY', 'FARAZSMS_LINE_NUMBER'],
                ['api_key' => 'FARAZSMS_API_KEY', 'line_number' => 'FARAZSMS_LINE_NUMBER'],
                'send',
            ],
            'ippanel' => [
                'ippanel',
                ['IPPANEL_API_KEY', 'IPPANEL_LINE_NUMBER'],
                ['api_key' => 'IPPANEL_API_KEY', 'line_number' => 'IPPANEL_LINE_NUMBER'],
                'send',
            ],
            'mediana' => [
                'mediana',
                $api('MEDIANA'),
                ['api_key' => 'MEDIANA_API_KEY', 'line_number' => 'MEDIANA_LINE_NUMBER'],
                'send',
            ],
            'limosms' => [
                'limosms',
                $api('LIMOSMS'),
                ['api_key' => 'LIMOSMS_API_KEY', 'line_number' => 'LIMOSMS_LINE_NUMBER'],
                'send',
            ],
            'niksms' => [
                'niksms',
                $user('NIKSMS'),
                ['username' => 'NIKSMS_USERNAME', 'password' => 'NIKSMS_PASSWORD', 'line_number' => 'NIKSMS_LINE_NUMBER'],
                'send',
            ],
            'magfa' => [
                'magfa',
                ['MAGFA_USERNAME', 'MAGFA_PASSWORD', 'MAGFA_LINE_NUMBER'],
                [
                    'username' => 'MAGFA_USERNAME',
                    'password' => 'MAGFA_PASSWORD',
                    'domain' => 'MAGFA_DOMAIN',
                    'line_number' => 'MAGFA_LINE_NUMBER',
                ],
                'send',
            ],
            'melipayamak' => [
                'melipayamak',
                $user('MELIPAYAMAK'),
                ['username' => 'MELIPAYAMAK_USERNAME', 'password' => 'MELIPAYAMAK_PASSWORD', 'line_number' => 'MELIPAYAMAK_LINE_NUMBER'],
                'send',
            ],
            'farapayamak' => [
                'farapayamak',
                $user('FARAPAYAMAK'),
                ['username' => 'FARAPAYAMAK_USERNAME', 'password' => 'FARAPAYAMAK_PASSWORD', 'line_number' => 'FARAPAYAMAK_LINE_NUMBER'],
                'send',
            ],
            'payamito' => [
                'payamito',
                $user('PAYAMITO'),
                ['username' => 'PAYAMITO_USERNAME', 'password' => 'PAYAMITO_PASSWORD', 'line_number' => 'PAYAMITO_LINE_NUMBER'],
                'send',
            ],
            'payamaknovin' => [
                'payamaknovin',
                $user('PAYAMAKNOVIN'),
                ['username' => 'PAYAMAKNOVIN_USERNAME', 'password' => 'PAYAMAKNOVIN_PASSWORD', 'line_number' => 'PAYAMAKNOVIN_LINE_NUMBER'],
                'send',
            ],
            'bahmanpayam' => [
                'bahmanpayam',
                $user('BAHMANPAYAM'),
                ['username' => 'BAHMANPAYAM_USERNAME', 'password' => 'BAHMANPAYAM_PASSWORD', 'line_number' => 'BAHMANPAYAM_LINE_NUMBER'],
                'send',
            ],
            'amoot' => [
                'amoot',
                $user('AMOOT'),
                ['username' => 'AMOOT_USERNAME', 'password' => 'AMOOT_PASSWORD', 'line_number' => 'AMOOT_LINE_NUMBER'],
                'send',
            ],
            'payamresan' => [
                'payamresan',
                $api('PAYAMRESAN'),
                ['api_key' => 'PAYAMRESAN_API_KEY', 'line_number' => 'PAYAMRESAN_LINE_NUMBER'],
                'send',
            ],
            'behinpayam' => [
                'behinpayam',
                $api('BEHINPAYAM'),
                ['api_key' => 'BEHINPAYAM_API_KEY', 'line_number' => 'BEHINPAYAM_LINE_NUMBER'],
                'send',
            ],
            'rastinsms' => [
                'rastinsms',
                $api('RASTINSMS'),
                ['api_key' => 'RASTINSMS_API_KEY', 'line_number' => 'RASTINSMS_LINE_NUMBER'],
                'send',
            ],
            'avanak' => [
                'avanak',
                ['AVANAK_API_TOKEN'],
                ['api_token' => 'AVANAK_API_TOKEN', 'server_id' => 'AVANAK_SERVER_ID'],
                'send',
            ],
        ];
    }

    private static function loadSandboxEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $path = dirname(__DIR__, 3);
        $file = $path.'/.env.sandbox';

        if (is_file($file)) {
            Dotenv::createImmutable($path, '.env.sandbox')->safeLoad();
        }

        self::$envLoaded = true;
    }

    private static function env(string $key): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
