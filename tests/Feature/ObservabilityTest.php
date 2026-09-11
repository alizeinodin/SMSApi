<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Events\SmsFailed;
use Alizeinodin\SmsApi\Events\SmsSending;
use Alizeinodin\SmsApi\Events\SmsSent;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Tests\Support\FailingDriver;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Support\Facades\Event;

class ObservabilityTest extends TestCase
{
    public function test_it_dispatches_sms_events_on_send(): void
    {
        Event::fake([SmsSending::class, SmsSent::class, SmsFailed::class]);

        Sms::fake();
        Sms::send('09123456789', 'hello');

        Event::assertDispatched(SmsSending::class);
        Event::assertDispatched(SmsSent::class);
        Event::assertNotDispatched(SmsFailed::class);
    }
}
