<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Resilience\SmsRateLimiter;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;

class SmsRateLimiterTest extends TestCase
{
    public function test_hit_is_noop_when_disabled(): void
    {
        $limiter = $this->makeLimiter(['enabled' => false, 'driver' => ['max' => 1, 'per_seconds' => 60]]);

        $limiter->hit('array');
        $limiter->hit('array');

        $this->assertTrue(true);
    }

    public function test_hit_throws_when_driver_limit_exceeded(): void
    {
        $limiter = $this->makeLimiter([
            'enabled' => true,
            'driver' => ['max' => 1, 'per_seconds' => 60],
        ]);

        $limiter->hit('array');

        $this->expectException(RateLimitExceededException::class);

        $limiter->hit('array');
    }

    public function test_channel_limit_is_enforced(): void
    {
        $limiter = $this->makeLimiter([
            'enabled' => true,
            'channels' => [
                'otp' => ['max' => 1, 'per_seconds' => 60],
            ],
        ]);

        $limiter->hit('array', ['channel' => 'otp']);

        try {
            $limiter->hit('array', ['channel' => 'otp']);
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('channel', $e->scope);
            $this->assertSame('otp', $e->key);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function makeLimiter(array $config): SmsRateLimiter
    {
        return new SmsRateLimiter(
            new RateLimiter(new Repository(new ArrayStore)),
            $config,
        );
    }
}
