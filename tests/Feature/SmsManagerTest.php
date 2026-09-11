<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Contracts\SmsSender;
use Alizeinodin\SmsApi\Contracts\SmsWallet;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Testing\RecordedMessage;
use Alizeinodin\SmsApi\Tests\TestCase;

class SmsManagerTest extends TestCase
{
    public function test_fake_records_normalized_verify_and_send(): void
    {
        Sms::fake();

        Sms::send('+989123456789', 'hello');
        Sms::sendTemplate('9121111111', 'otp-login', ['CODE' => '1']);

        Sms::assertSentTimes(2);
        Sms::assertSentTo('09123456789');
        Sms::assertTemplateSent('09121111111', 'otp-login');

        Sms::assertSent(function (RecordedMessage $sms): bool {
            return $sms->isSend() && $sms->content() === 'hello';
        });
    }

    public function test_array_driver_implements_narrow_contracts(): void
    {
        $driver = Sms::driver('array');

        $this->assertInstanceOf(SmsSender::class, $driver);
        $this->assertInstanceOf(SmsWallet::class, $driver);

        $credit = $driver->getCredit();
        $this->assertTrue($credit->success);
    }

    public function test_named_template_resolves_on_array_driver(): void
    {
        Sms::fake();

        Sms::sendTemplate('09123456789', 'otp-login', ['CODE' => '42']);

        Sms::assertTemplateSent('09123456789', 'otp-login');
    }

    public function test_assert_nothing_sent(): void
    {
        Sms::fake();
        Sms::assertNothingSent();
    }
}
