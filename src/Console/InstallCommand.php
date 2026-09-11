<?php

namespace Alizeinodin\SmsApi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'smsapi:install
                            {feature? : Optional feature: logging, delivery, api, messaging, balance, resilience, otp, all}
                            {--logging : Install SMS logs (migrations + logging)}
                            {--delivery : Install delivery webhook + sync (needs logging)}
                            {--api : Enable HTTP API routes (send/sendTemplate endpoint)}
                            {--messaging : Publish messenger driver config (Telegram, WhatsApp, Bale)}
                            {--balance : Enable low-balance alerts}
                            {--resilience : Enable circuit breaker + rate limit}
                            {--otp : Enable OTP vault (generate / HMAC store / check)}
                            {--all : Install every optional feature}
                            {--no-migrate : Do not run migrations}';

    protected $description = 'Publish smsapi config and optionally install features';

    public function handle(): int
    {
        $this->publishConfig();

        $features = $this->resolveFeatures();

        if ($features === []) {
            $this->info('Core SmsApi is ready (send / sendTemplate / failover / notifications / queues).');
            $this->line('Optional features are disabled until you install them:');
            $this->line('  php artisan smsapi:install logging');
            $this->line('  php artisan smsapi:install delivery');
            $this->line('  php artisan smsapi:install api');
            $this->line('  php artisan smsapi:install messaging');
            $this->line('  php artisan smsapi:install balance');
            $this->line('  php artisan smsapi:install resilience');
            $this->line('  php artisan smsapi:install otp');
            $this->line('  php artisan smsapi:install all');

            return self::SUCCESS;
        }

        if (in_array('logging', $features, true) || in_array('delivery', $features, true)) {
            $this->installLogging();
        }

        if (in_array('delivery', $features, true)) {
            $this->installDelivery();
        }

        if (in_array('api', $features, true)) {
            $this->installApi();
        }

        if (in_array('messaging', $features, true)) {
            $this->installMessaging();
        }

        if (in_array('balance', $features, true)) {
            $this->installBalance();
        }

        if (in_array('resilience', $features, true)) {
            $this->installResilience();
        }

        if (in_array('otp', $features, true)) {
            $this->installOtp();
        }

        $this->newLine();
        $this->info('Done. Review config/smsapi.php and your .env values.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    protected function resolveFeatures(): array
    {
        $known = ['logging', 'delivery', 'api', 'messaging', 'balance', 'resilience', 'otp'];
        $argument = $this->argument('feature');

        if (is_string($argument) && $argument !== '') {
            $argument = strtolower($argument);

            if ($argument === 'all') {
                return $known;
            }

            if (in_array($argument, $known, true)) {
                return [$argument];
            }

            $this->error('Unknown feature [' . $argument . ']. Use: ' . implode(', ', $known) . ', all');

            return [];
        }

        if ($this->option('all')) {
            return $known;
        }

        $features = [];

        foreach ($known as $feature) {
            if ($this->option($feature)) {
                $features[] = $feature;
            }
        }

        return $features;
    }

    protected function publishConfig(): void
    {
        $this->call('vendor:publish', [
            '--tag' => 'smsapi-config',
            '--force' => true,
        ]);
    }

    protected function installLogging(): void
    {
        $this->info('Installing logging…');

        $this->call('vendor:publish', [
            '--tag' => 'smsapi-migrations',
            '--force' => true,
        ]);

        $this->setEnv([
            'SMSAPI_LOGGING_ENABLED' => 'true',
        ]);

        $this->patchConfigValue("env('SMSAPI_LOGGING_ENABLED', false)", "env('SMSAPI_LOGGING_ENABLED', true)");

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->line('  ✓ sms_logs migrations published & logging enabled');
    }

    protected function installDelivery(): void
    {
        $this->info('Installing delivery webhook + sync…');

        $this->setEnv([
            'SMSAPI_WEBHOOK_ENABLED' => 'true',
            'SMSAPI_DELIVERY_SYNC_ENABLED' => 'true',
        ]);

        $this->patchConfigValue("env('SMSAPI_WEBHOOK_ENABLED', false)", "env('SMSAPI_WEBHOOK_ENABLED', true)");
        $this->patchConfigValue("env('SMSAPI_DELIVERY_SYNC_ENABLED', false)", "env('SMSAPI_DELIVERY_SYNC_ENABLED', true)");

        $this->line('  ✓ webhook route + delivery sync enabled');
        $this->line('  → POST /' . config('smsapi.webhook.path', 'smsapi/webhooks/delivery'));
        $this->line('  → php artisan smsapi:sync-delivery');
    }

    protected function installApi(): void
    {
        $this->info('Installing HTTP API…');

        $this->setEnv([
            'SMSAPI_API_ENABLED' => 'true',
        ]);

        $this->patchConfigValue("env('SMSAPI_API_ENABLED', false)", "env('SMSAPI_API_ENABLED', true)");

        $this->line('  ✓ POST /'.trim((string) config('smsapi.api.prefix', 'smsapi'), '/').'/messages');
        $this->line('  → set SMSAPI_API_TOKEN in .env');
        $this->line('  → header X-SMSAPI-Token or Authorization: Bearer …');
    }

    protected function installMessaging(): void
    {
        $this->info('Enabling messenger drivers (Telegram, WhatsApp, Bale)…');

        $this->line('  ✓ drivers telegram / whatsapp / bale are in config/smsapi.php');
        $this->line('  → TELEGRAM_BOT_TOKEN, WHATSAPP_ACCESS_TOKEN, BALE_BOT_TOKEN');
        $this->line('  → Sms::driver(\'telegram\')->send($chatId, $text, [\'chat_id\' => $chatId])');
    }

    protected function installBalance(): void
    {
        $this->info('Installing balance alerts…');

        $this->setEnv([
            'SMSAPI_BALANCE_ALERT_ENABLED' => 'true',
        ]);

        $this->patchConfigValue("env('SMSAPI_BALANCE_ALERT_ENABLED', false)", "env('SMSAPI_BALANCE_ALERT_ENABLED', true)");

        $this->line('  ✓ balance alerts enabled');
        $this->line('  → set SMSAPI_BALANCE_THRESHOLD / SMSAPI_BALANCE_ALERT_MAIL in .env');
        $this->line('  → php artisan smsapi:check-balance');
    }

    protected function installResilience(): void
    {
        $this->info('Installing circuit breaker + rate limit…');

        $this->setEnv([
            'SMSAPI_CIRCUIT_BREAKER_ENABLED' => 'true',
            'SMSAPI_RATE_LIMIT_ENABLED' => 'true',
        ]);

        $this->patchConfigValue(
            "env('SMSAPI_CIRCUIT_BREAKER_ENABLED', false)",
            "env('SMSAPI_CIRCUIT_BREAKER_ENABLED', true)"
        );
        $this->patchConfigValue(
            "env('SMSAPI_RATE_LIMIT_ENABLED', false)",
            "env('SMSAPI_RATE_LIMIT_ENABLED', true)"
        );

        $this->line('  ✓ circuit breaker + rate limit enabled');
        $this->line('  → tune thresholds in config/smsapi.php → resilience');
    }

    protected function installOtp(): void
    {
        $this->info('Installing OTP vault…');

        $this->call('vendor:publish', [
            '--tag' => 'smsapi-migrations',
            '--force' => true,
        ]);

        $this->setEnv([
            'SMSAPI_OTP_VAULT_ENABLED' => 'true',
            'SMSAPI_OTP_REDACT' => 'true',
        ]);

        $this->patchConfigValue(
            "env('SMSAPI_OTP_VAULT_ENABLED', false)",
            "env('SMSAPI_OTP_VAULT_ENABLED', true)"
        );

        if (! $this->option('no-migrate')) {
            $this->call('migrate', ['--force' => true]);
        }

        $this->line('  ✓ OTP vault enabled (HMAC store + check)');
        $this->line('  → Sms::challenge(\'login\')->template(\'otp-login\')->to($user)->send()');
        $this->line('  → Sms::challenge(\'login\')->check($user, $code)');
        $this->line('  → store=database (sms_otp_challenges) or SMSAPI_OTP_VAULT_STORE=cache');
    }

    /**
     * @param  array<string, string>  $values
     */
    protected function setEnv(array $values): void
    {
        $path = base_path('.env');

        if (! File::exists($path)) {
            $this->warn('  .env not found — set these manually: ' . implode(', ', array_keys($values)));

            return;
        }

        $contents = File::get($path);

        foreach ($values as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $contents)) {
                $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents) ?? $contents;
            } else {
                $contents = rtrim($contents) . PHP_EOL . "{$key}={$value}" . PHP_EOL;
            }
        }

        File::put($path, $contents);
    }

    protected function patchConfigValue(string $search, string $replace): void
    {
        $path = config_path('smsapi.php');

        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);

        if (! str_contains($contents, $search)) {
            return;
        }

        File::put($path, str_replace($search, $replace, $contents));
    }
}
