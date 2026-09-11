<?php

namespace Alizeinodin\SmsApi;

use Alizeinodin\SmsApi\Balance\BalanceMonitor;
use Alizeinodin\SmsApi\Console\CheckBalanceCommand;
use Alizeinodin\SmsApi\Console\InstallCommand;
use Alizeinodin\SmsApi\Console\SyncDeliveryCommand;
use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Delivery\DeliveryStatusUpdater;
use Alizeinodin\SmsApi\Delivery\Parsers\GenericDeliveryWebhookParser;
use Alizeinodin\SmsApi\Logging\SmsLogger;
use Alizeinodin\SmsApi\Mail\TemplateMailer;
use Alizeinodin\SmsApi\Notifications\SmsChannel;
use Alizeinodin\SmsApi\Otp\CacheOtpStore;
use Alizeinodin\SmsApi\Otp\DatabaseOtpStore;
use Alizeinodin\SmsApi\Otp\OtpStore;
use Alizeinodin\SmsApi\Otp\OtpVault;
use Alizeinodin\SmsApi\Http\Middleware\VerifyApiToken;
use Alizeinodin\SmsApi\Http\Middleware\VerifyWebhookToken;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\Resilience\CircuitBreaker;
use Alizeinodin\SmsApi\Resilience\SmsRateLimiter;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use Illuminate\Cache\RateLimiter;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class SmsApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'smsapi');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/smsapi.php' => config_path('smsapi.php'),
            ], 'smsapi-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'smsapi-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views/mail' => resource_path('views/vendor/smsapi/mail'),
            ], 'smsapi-mail');

            $this->commands([
                CheckBalanceCommand::class,
                InstallCommand::class,
                SyncDeliveryCommand::class,
            ]);
        }

        // Optional features — off until enabled via `php artisan smsapi:install …`
        // (views already loaded above for mail OTP)

        // Routes are always registered; middleware returns 404 when feature is disabled.
        $this->registerWebhookRoutes();
        $this->registerApiRoutes();

        $this->registerSmsChannel();
        $this->registerSchedules();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/smsapi.php', 'smsapi');

        $this->app->alias(VerifyWebhookToken::class, 'smsapi.webhook');
        $this->app->alias(VerifyApiToken::class, 'smsapi.api');

        $this->app->singleton(SmsManager::class, function ($app) {
            return new SmsManager($app);
        });

        $this->app->alias(SmsManager::class, 'smsapi');

        $this->app->bind(MessageDriver::class, function ($app) {
            return $app->make(SmsManager::class)->driver();
        });

        $this->app->bind(SmsDriver::class, function ($app) {
            return $app->make(SmsManager::class)->smsDriver();
        });

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel($app->make(SmsManager::class));
        });

        $this->app->singleton(TemplateMailer::class, function () {
            return new TemplateMailer();
        });

        $this->app->singleton(BalanceMonitor::class, function ($app) {
            return new BalanceMonitor($app->make(SmsManager::class));
        });

        $this->app->singleton(SmsLogger::class, function () {
            return new SmsLogger();
        });

        $this->app->singleton(TemplateRegistry::class, function () {
            return new TemplateRegistry(
                (array) config('smsapi.templates', []),
            );
        });

        $this->app->singleton(MessagePolicyRegistry::class, function () {
            return new MessagePolicyRegistry(
                (array) config('smsapi.messages', []),
            );
        });

        $this->app->singleton(DeliveryStatusUpdater::class, function () {
            return new DeliveryStatusUpdater();
        });

        $this->app->singleton(GenericDeliveryWebhookParser::class, function () {
            return new GenericDeliveryWebhookParser();
        });

        $this->app->singleton(CircuitBreaker::class, function ($app) {
            return new CircuitBreaker(
                $app->make('cache')->store(),
                (array) config('smsapi.resilience.circuit_breaker', []),
            );
        });

        $this->app->singleton(SmsRateLimiter::class, function ($app) {
            return new SmsRateLimiter(
                $app->make(RateLimiter::class),
                (array) config('smsapi.resilience.rate_limit', []),
            );
        });

        $this->app->singleton(OtpStore::class, function ($app) {
            $store = (string) config('smsapi.otp.vault.store', 'database');

            if ($store === 'cache') {
                return new CacheOtpStore($app->make('cache')->store());
            }

            return new DatabaseOtpStore();
        });

        $this->app->singleton(OtpVault::class, function ($app) {
            return new OtpVault(
                $app->make(OtpStore::class),
                $app->make(RateLimiter::class),
                (array) config('smsapi.otp.vault', []),
            );
        });
    }

    protected function registerWebhookRoutes(): void
    {
        if (! class_exists(Route::class)) {
            return;
        }

        Route::group([], __DIR__ . '/../routes/webhooks.php');
    }

    protected function registerApiRoutes(): void
    {
        if (! class_exists(Route::class)) {
            return;
        }

        Route::group([], __DIR__ . '/../routes/api.php');
    }

    /**
     * @throws BindingResolutionException
     */
    protected function registerSmsChannel(): void
    {
        if (! class_exists(ChannelManager::class)) {
            return;
        }

        $this->app->make(ChannelManager::class)->extend('sms', function ($app) {
            return $app->make(SmsChannel::class);
        });
    }

    protected function registerSchedules(): void
    {
        if (! class_exists(Schedule::class)) {
            return;
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            if ((bool) config('smsapi.balance_alert.enabled', false)) {
                $schedule
                    ->command('smsapi:check-balance')
                    ->cron((string) config('smsapi.balance_alert.schedule', '0 * * * *'))
                    ->withoutOverlapping()
                    ->name('smsapi-check-balance');
            }

            if ((bool) config('smsapi.webhook.sync.enabled', false)) {
                $schedule
                    ->command('smsapi:sync-delivery')
                    ->cron((string) config('smsapi.webhook.sync.schedule', '*/10 * * * *'))
                    ->withoutOverlapping()
                    ->name('smsapi-sync-delivery');
            }
        });
    }
}
