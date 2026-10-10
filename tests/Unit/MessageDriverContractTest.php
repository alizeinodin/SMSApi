<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\MessengerDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Tests\TestCase;

class MessageDriverContractTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smsapi.drivers.telegram', [
            'driver' => 'telegram',
            'bot_token' => 'test-token',
            'channel' => 'telegram',
        ]);
    }

    public function test_sms_array_driver_implements_sms_driver_not_messenger(): void
    {
        $driver = new ArrayDriver(['name' => 'array']);

        $this->assertInstanceOf(MessageDriver::class, $driver);
        $this->assertInstanceOf(SmsDriver::class, $driver);
        $this->assertNotInstanceOf(MessengerDriver::class, $driver);
        $this->assertSame('sms', $driver->getChannel());
    }

    public function test_telegram_implements_messenger_not_sms_driver(): void
    {
        $driver = new TelegramDriver(['bot_token' => 'x', 'name' => 'telegram']);

        $this->assertInstanceOf(MessageDriver::class, $driver);
        $this->assertInstanceOf(MessengerDriver::class, $driver);
        $this->assertNotInstanceOf(SmsDriver::class, $driver);
        $this->assertSame('telegram', $driver->getChannel());
    }

    public function test_manager_sms_rejects_messenger_channel(): void
    {
        $this->expectException(SmsApiException::class);
        $this->expectExceptionMessage('messenger channel');

        Sms::smsDriver('telegram');
    }

    public function test_messenger_verify_is_unsupported_through_manager(): void
    {
        $this->expectException(UnsupportedDriverMethodException::class);

        Sms::driver('telegram')->sendTemplate('09120000000', 'otp', ['CODE' => '1']);
    }
}
