<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Drivers\AmootDriver;
use Alizeinodin\SmsApi\Drivers\AvanakDriver;
use Alizeinodin\SmsApi\Drivers\BahmanpayamDriver;
use Alizeinodin\SmsApi\Drivers\BehinpayamDriver;
use Alizeinodin\SmsApi\Drivers\FarapayamakDriver;
use Alizeinodin\SmsApi\Drivers\FarazsmsDriver;
use Alizeinodin\SmsApi\Drivers\GhasedakDriver;
use Alizeinodin\SmsApi\Drivers\IppanelDriver;
use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\LimosmsDriver;
use Alizeinodin\SmsApi\Drivers\MagfaDriver;
use Alizeinodin\SmsApi\Drivers\MedianaDriver;
use Alizeinodin\SmsApi\Drivers\MelipayamakDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\BaleDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\EitaaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\FacebookMessengerDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\GapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\RubikaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\SlackDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\ViberDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\WhatsAppDriver;
use Alizeinodin\SmsApi\Drivers\NiksmsDriver;
use Alizeinodin\SmsApi\Drivers\PayamaknovinDriver;
use Alizeinodin\SmsApi\Drivers\PayamitoDriver;
use Alizeinodin\SmsApi\Drivers\PayamresanDriver;
use Alizeinodin\SmsApi\Drivers\RastinsmsDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Alizeinodin\SmsApi\SmsManager;
use Alizeinodin\SmsApi\Tests\TestCase;

class ProviderDriversTest extends TestCase
{
    /**
     * @dataProvider providerDriverProvider
     *
     * @param  class-string  $class
     */
    public function test_builtin_provider_drivers_can_be_created(string $type, string $class, array $config): void
    {
        $driver = DriverRegistry::make($type, $config);

        $this->assertInstanceOf($class, $driver);
        $this->assertSame($config['name'], $driver->getName());
        $this->assertSame($config['driver'], $driver->getProvider());
    }

    public function test_sms_manager_resolves_registered_driver(): void
    {
        $manager = $this->app->make(SmsManager::class);
        $driver = $manager->driver('kavenegar');

        $this->assertSame('kavenegar', $driver->getProvider());
        $this->assertSame('kavenegar', $driver->getName());
    }

    /**
     * @return array<string, array{0: string, 1: class-string, 2: array<string, mixed>}>
     */
    public static function providerDriverProvider(): array
    {
        $panel = static fn (string $driver, string $class): array => [
            $driver,
            $class,
            [
                'driver' => $driver,
                'name' => $driver,
                'username' => 'user',
                'password' => 'pass',
                'line_number' => '3000',
            ],
        ];

        $apiKey = static fn (string $driver, string $class): array => [
            $driver,
            $class,
            ['driver' => $driver, 'name' => $driver, 'api_key' => 'key', 'line_number' => '3000'],
        ];

        return [
            'smsir' => $apiKey('smsir', SmsIrDriver::class),
            'kavenegar' => $apiKey('kavenegar', KavenegarDriver::class),
            'ghasedak' => $apiKey('ghasedak', GhasedakDriver::class),
            'melipayamak' => $panel('melipayamak', MelipayamakDriver::class),
            'farapayamak' => $panel('farapayamak', FarapayamakDriver::class),
            'payamito' => $panel('payamito', PayamitoDriver::class),
            'payamaknovin' => $panel('payamaknovin', PayamaknovinDriver::class),
            'bahmanpayam' => $panel('bahmanpayam', BahmanpayamDriver::class),
            'amoot' => $panel('amoot', AmootDriver::class),
            'payamresan' => $apiKey('payamresan', PayamresanDriver::class),
            'behinpayam' => $apiKey('behinpayam', BehinpayamDriver::class),
            'rastinsms' => $apiKey('rastinsms', RastinsmsDriver::class),
            'farazsms' => $apiKey('farazsms', FarazsmsDriver::class),
            'niksms' => [
                'niksms',
                NiksmsDriver::class,
                [
                    'driver' => 'niksms',
                    'name' => 'niksms',
                    'username' => 'user',
                    'password' => 'pass',
                    'line_number' => '3000',
                ],
            ],
            'magfa' => [
                'magfa',
                MagfaDriver::class,
                [
                    'driver' => 'magfa',
                    'name' => 'magfa',
                    'username' => 'user',
                    'password' => 'pass',
                    'domain' => 'magfa',
                    'line_number' => '3000',
                ],
            ],
            'avanak' => [
                'avanak',
                AvanakDriver::class,
                ['driver' => 'avanak', 'name' => 'avanak', 'api_token' => 'token'],
            ],
            'limosms' => $apiKey('limosms', LimosmsDriver::class),
            'mediana' => $apiKey('mediana', MedianaDriver::class),
            'ippanel' => $apiKey('ippanel', IppanelDriver::class),
            'telegram' => [
                'telegram',
                TelegramDriver::class,
                ['driver' => 'telegram', 'name' => 'telegram', 'bot_token' => 'token', 'channel' => 'telegram'],
            ],
            'whatsapp' => [
                'whatsapp',
                WhatsAppDriver::class,
                [
                    'driver' => 'whatsapp',
                    'name' => 'whatsapp',
                    'access_token' => 'token',
                    'phone_number_id' => '100',
                    'channel' => 'whatsapp',
                ],
            ],
            'bale' => [
                'bale',
                BaleDriver::class,
                ['driver' => 'bale', 'name' => 'bale', 'bot_token' => 'token', 'channel' => 'bale'],
            ],
            'eitaa' => [
                'eitaa',
                EitaaDriver::class,
                ['driver' => 'eitaa', 'name' => 'eitaa', 'token' => 'token', 'channel' => 'eitaa'],
            ],
            'rubika' => [
                'rubika',
                RubikaDriver::class,
                ['driver' => 'rubika', 'name' => 'rubika', 'bot_token' => 'token', 'channel' => 'rubika'],
            ],
            'gap' => [
                'gap',
                GapDriver::class,
                ['driver' => 'gap', 'name' => 'gap', 'token' => 'token', 'channel' => 'gap'],
            ],
            'messenger' => [
                'messenger',
                FacebookMessengerDriver::class,
                ['driver' => 'messenger', 'name' => 'messenger', 'page_access_token' => 'token', 'channel' => 'messenger'],
            ],
            'viber' => [
                'viber',
                ViberDriver::class,
                ['driver' => 'viber', 'name' => 'viber', 'auth_token' => 'token', 'channel' => 'viber'],
            ],
            'slack' => [
                'slack',
                SlackDriver::class,
                ['driver' => 'slack', 'name' => 'slack', 'bot_token' => 'token', 'channel' => 'slack'],
            ],
        ];
    }
}
