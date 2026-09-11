<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Notifications\NotificationMessage;
use Alizeinodin\SmsApi\Tests\TestCase;
use ReflectionProperty;

class NotificationMessageQueueTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smsapi.queue.default', 'sms-default');
        $app['config']->set('smsapi.messages.otp.queue', 'otp');
    }

    public function test_queue_without_name_uses_config_default(): void
    {
        $message = NotificationMessage::create('hi')->queue();

        $this->assertSame('sms-default', $this->queueOf($message));
    }

    public function test_queue_without_name_keeps_policy_queue(): void
    {
        $message = NotificationMessage::create()->policy('otp')->queue();

        $this->assertSame('otp', $this->queueOf($message));
    }

    public function test_explicit_queue_overrides_policy(): void
    {
        $message = NotificationMessage::create()->policy('otp')->queue('marketing');

        $this->assertSame('marketing', $this->queueOf($message));
    }

    private function queueOf(NotificationMessage $message): ?string
    {
        $property = new ReflectionProperty(NotificationMessage::class, 'queue');

        return $property->getValue($message);
    }
}
