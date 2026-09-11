<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Tests\TestCase;

class MessagePolicyIntegrationTest extends TestCase
{
    public function test_message_policy_applies_queue_and_driver(): void
    {
        $this->app['config']->set('smsapi.messages', [
            'otp' => [
                'driver' => 'array',
                'queue' => 'otp',
            ],
        ]);

        $pending = Sms::policy('otp');

        $this->assertSame('otp', $pending->type());
        $this->assertSame('array', $pending->policy()->driver);
    }

    public function test_message_policy_merges_rate_limit_channel(): void
    {
        $this->app['config']->set('smsapi.messages', [
            'otp' => [
                'driver' => 'array',
                'rate_limit' => 'otp',
            ],
        ]);

        Sms::fake();

        Sms::policy('otp')
            ->sync()
            ->send('09123456789', 'hello');

        Sms::assertSent(function ($sms) {
            return ($sms->options()['channel'] ?? null) === 'otp';
        });
    }
}
