<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\AbstractDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Tests\TestCase;

class EasyDriverTest extends TestCase
{
    public function test_custom_driver_only_needs_sender_methods(): void
    {
        Sms::extend('mini', function ($app, $name) {
            return new class(['name' => is_string($name) ? $name : 'mini']) extends AbstractDriver
            {
                public function getName(): string
                {
                    return (string) ($this->config['name'] ?? 'mini');
                }

                public function send(string|array $mobiles, string $message, array $options = []): SendResult
                {
                    return $this->toResponse([
                        'message' => 'OK',
                        'data' => [
                            'mobiles' => $this->normalizeMobiles($mobiles),
                            'message' => $message,
                        ],
                    ]);
                }
            };
        });

        $this->app['config']->set('smsapi.drivers.mini', [
            'driver' => 'mini',
        ]);

        /** @var SmsDriver $driver */
        $driver = Sms::driver('mini');

        $response = $driver->send('+989123456789', 'ping');

        $this->assertTrue($response->success);
        $this->assertSame(['09123456789'], $response->data['mobiles'] ?? null);
        $this->assertSame('mini', $driver->getName());
        $this->assertSame('mini', $driver->getProvider());

        $this->expectException(UnsupportedDriverMethodException::class);
        $driver->getLatestReceived();
    }

    public function test_named_connection_keeps_provider_separate_from_name(): void
    {
        Sms::extend('mini', function ($app, $name) {
            return new class(['name' => is_string($name) ? $name : 'mini', 'driver' => 'mini']) extends AbstractDriver
            {
                public function getName(): string
                {
                    return (string) ($this->config['name'] ?? 'mini');
                }

                public function send(string|array $mobiles, string $message, array $options = []): SendResult
                {
                    return $this->toResponse(['message' => 'OK']);
                }
            };
        });

        $this->app['config']->set('smsapi.connections.mini-otp', [
            'driver' => 'mini',
            'name' => 'mini-otp',
        ]);

        /** @var SmsDriver $driver */
        $driver = Sms::driver('mini-otp');

        $this->assertSame('mini-otp', $driver->getName());
        $this->assertSame('mini', $driver->getProvider());
    }
}
