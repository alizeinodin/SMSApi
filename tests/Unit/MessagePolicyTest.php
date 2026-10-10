<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Exceptions\OutsideAllowedHoursException;
use Alizeinodin\SmsApi\Policies\MessagePolicy;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class MessagePolicyTest extends TestCase
{
    public function test_allowed_hours_window(): void
    {
        $policy = new MessagePolicy(
            type: 'marketing',
            allowedHours: ['start' => 9, 'end' => 21],
            timezone: 'Asia/Tehran',
        );

        $inside = new DateTimeImmutable('2026-08-15 12:00:00', new DateTimeZone('Asia/Tehran'));
        $outside = new DateTimeImmutable('2026-08-15 22:30:00', new DateTimeZone('Asia/Tehran'));

        $this->assertTrue($policy->isWithinAllowedWindow($inside));
        $this->assertFalse($policy->isWithinAllowedWindow($outside));

        $this->expectException(OutsideAllowedHoursException::class);
        $policy->assertWithinAllowedWindow($outside);
    }

    public function test_channel_falls_back_to_type(): void
    {
        $this->assertSame('otp', (new MessagePolicy(type: 'otp'))->channel());
        $this->assertSame('fast', (new MessagePolicy(type: 'otp', rateLimit: 'fast'))->channel());
    }
}
