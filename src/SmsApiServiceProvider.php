<?php

namespace Alizeinodin\SmsApi;

use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Illuminate\Support\ServiceProvider;

class SmsApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/smsapi.php' => config_path('smsapi.php'),
        ], 'smsapi-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/smsapi.php', 'smsapi');

        $this->app->singleton('smsapi.driver', function ($app) {
            $default = (string) $app['config']->get('smsapi.default', 'smsir');
            $config = (array) $app['config']->get("smsapi.drivers.{$default}", []);

            return DriverRegistry::make($default, $config);
        });
    }
}
