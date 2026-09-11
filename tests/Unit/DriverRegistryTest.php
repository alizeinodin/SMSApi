<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use PHPUnit\Framework\TestCase;

class DriverRegistryTest extends TestCase
{
    public function test_it_instantiates_registered_driver(): void
    {
        $driver = DriverRegistry::make('kavenegar', [
            'driver' => 'kavenegar',
            'api_key' => 'key',
            'line_number' => '3000',
        ]);

        $this->assertInstanceOf(KavenegarDriver::class, $driver);
    }

    public function test_it_throws_for_unknown_transport(): void
    {
        $this->expectException(SmsApiException::class);

        DriverRegistry::make('unknown-provider', ['driver' => 'unknown-provider']);
    }

    public function test_custom_driver_can_be_registered(): void
    {
        DriverRegistry::register('custom-test', KavenegarDriver::class);

        $this->assertTrue(DriverRegistry::has('custom-test'));
        $this->assertInstanceOf(
            KavenegarDriver::class,
            DriverRegistry::make('custom-test', ['driver' => 'custom-test', 'api_key' => 'x', 'line_number' => '1'])
        );
    }
}
