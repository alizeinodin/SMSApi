<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Exceptions\TemplateNotFoundException;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use PHPUnit\Framework\TestCase;

class TemplateRegistryTest extends TestCase
{
    public function test_it_resolves_per_driver_ids(): void
    {
        $registry = new TemplateRegistry([
            'otp-login' => [
                'smsir' => 100,
                'kavenegar' => 'otp-login',
            ],
            'order' => [
                'drivers' => ['smsir' => 200],
                'queue' => 'otp',
            ],
        ]);

        $this->assertTrue($registry->has('otp-login'));
        $this->assertSame(100, $registry->id('otp-login', 'smsir'));
        $this->assertSame('otp-login', $registry->id('otp-login', 'kavenegar'));
        $this->assertSame(100, $registry->resolve('otp-login', 'smsir'));
        $this->assertSame(55, $registry->resolve(55, 'smsir'));
        $this->assertSame('otp', $registry->get('order')->queue);
    }

    public function test_missing_template_throws(): void
    {
        $this->expectException(TemplateNotFoundException::class);

        (new TemplateRegistry([]))->get('missing');
    }
}
