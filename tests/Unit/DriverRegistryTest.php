<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class DriverRegistryTest extends TestCase
{
    public function test_can_make_builtin_drivers(): void
    {
        $smsir = DriverRegistry::make('smsir', ['api_key' => 'key', 'line_number' => '3000']);
        $kavenegar = DriverRegistry::make('kavenegar', ['api_key' => 'key', 'line_number' => '1000']);

        $this->assertInstanceOf(SmsIrDriver::class, $smsir);
        $this->assertInstanceOf(KavenegarDriver::class, $kavenegar);
        $this->assertSame('smsir', $smsir->getProvider());
        $this->assertSame('kavenegar', $kavenegar->getName());
    }

    public function test_registry_lists_panel_drivers(): void
    {
        $all = DriverRegistry::all();

        foreach ([
            'smsir', 'kavenegar', 'ghasedak', 'farazsms', 'ippanel', 'magfa', 'niksms',
            'mediana', 'limosms', 'melipayamak', 'farapayamak', 'payamito', 'payamaknovin',
            'bahmanpayam', 'amoot', 'payamresan', 'behinpayam', 'rastinsms', 'avanak',
            'telegram', 'whatsapp', 'bale', 'eitaa', 'rubika', 'gap', 'igap',
            'messenger', 'viber', 'line', 'discord', 'slack',
        ] as $driver) {
            $this->assertArrayHasKey($driver, $all, "Missing driver map entry: {$driver}");
            $this->assertTrue(DriverRegistry::has($driver));
        }
    }
}

class PhoneNumberTest extends TestCase
{
    public function test_normalizes_iranian_mobiles_to_local(): void
    {
        $this->assertSame(['09121234567'], PhoneNumber::normalize('+989121234567'));
        $this->assertSame(['09121234567'], PhoneNumber::normalize('9121234567'));
        $this->assertSame(['09121234567'], PhoneNumber::normalize('00989121234567'));
    }
}
