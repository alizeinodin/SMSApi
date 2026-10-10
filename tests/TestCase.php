<?php

namespace Alizeinodin\SmsApi\Tests;

use Alizeinodin\SmsApi\SmsApiServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SmsApiServiceProvider::class];
    }
}
