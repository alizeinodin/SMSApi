<?php

namespace Alizeinodin\SmsApi\Tests;

use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\SmsApiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [SmsApiServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'Sms' => Sms::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('smsapi.default', 'array');
        $app['config']->set('smsapi.logging.enabled', false);
        $app['config']->set('smsapi.resilience.circuit_breaker.enabled', false);
        $app['config']->set('smsapi.resilience.rate_limit.enabled', false);
        $app['config']->set('smsapi.phone', [
            'enabled' => true,
            'format' => 'local',
            'country_code' => '98',
            'strict' => false,
        ]);
        $app['config']->set('smsapi.drivers.array', [
            'credit' => 999999,
        ]);
        $app['config']->set('smsapi.templates', [
            'otp-login' => [
                'array' => 'otp-array',
                'smsir' => 100,
            ],
        ]);
    }
}
