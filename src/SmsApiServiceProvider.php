<?php

namespace Alizeinodin\SmsApi;

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

        $this->app->singleton(SmsManager::class, function ($app) {
            return new SmsManager($app);
        });

        $this->app->alias(SmsManager::class, 'smsapi');
    }

    /**
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [SmsManager::class, 'smsapi'];
    }
}
