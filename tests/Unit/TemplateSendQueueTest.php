<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Pending\TemplateSend;
use Alizeinodin\SmsApi\Tests\TestCase;
use ReflectionProperty;

class TemplateSendQueueTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('smsapi.queue.default', 'sms-default');
        $app['config']->set('smsapi.templates', [
            'plain' => [
                'array' => 'plain-id',
            ],
            'otp-login' => [
                'drivers' => ['array' => 'otp-array'],
                'queue' => 'otp',
            ],
        ]);
    }

    public function test_queue_without_name_uses_config_default(): void
    {
        $pending = $this->template('plain')->queue();

        $this->assertSame('sms-default', $this->queueOf($pending));
    }

    public function test_queue_without_name_prefers_template_definition_queue(): void
    {
        $pending = $this->template('otp-login')->queue();

        $this->assertSame('otp', $this->queueOf($pending));
    }

    public function test_queue_with_explicit_name_overrides_defaults(): void
    {
        $pending = $this->template('otp-login')->queue('marketing');

        $this->assertSame('marketing', $this->queueOf($pending));
    }

    private function template(string $name): TemplateSend
    {
        return $this->app->make('smsapi')->template($name);
    }

    private function queueOf(TemplateSend $pending): ?string
    {
        $property = new ReflectionProperty(TemplateSend::class, 'queue');

        return $property->getValue($pending);
    }
}
