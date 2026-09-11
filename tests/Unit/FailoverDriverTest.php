<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Drivers\FailoverDriver;
use Alizeinodin\SmsApi\Exceptions\FailoverException;
use Alizeinodin\SmsApi\Testing\SmsFake;
use Alizeinodin\SmsApi\Tests\Support\FailingDriver;
use PHPUnit\Framework\TestCase;

class FailoverDriverTest extends TestCase
{
    public function test_it_fails_over_on_outbound_until_success(): void
    {
        $driver = new FailoverDriver(
            ['broken', 'ok'],
            fn (string $name) => match ($name) {
                'broken' => new FailingDriver(['name' => 'broken']),
                'ok' => (new SmsFake())->actingAsDriver('ok'),
            },
        );

        $response = $driver->send('09120000002', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame('ok', $response->driver);
    }

    public function test_wallet_methods_use_primary_driver_only(): void
    {
        $driver = new FailoverDriver(
            ['primary', 'secondary'],
            fn (string $name) => (new SmsFake())->actingAsDriver($name),
        );

        $response = $driver->getCredit();

        $this->assertTrue($response->success);
        $this->assertSame('primary', $response->driver);
    }

    public function test_it_throws_when_all_providers_fail(): void
    {
        $driver = new FailoverDriver(
            ['broken'],
            fn () => new FailingDriver(['name' => 'broken']),
        );

        $this->expectException(FailoverException::class);
        $driver->send('0912', 'hello');
    }
}
