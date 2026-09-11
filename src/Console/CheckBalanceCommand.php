<?php

namespace Alizeinodin\SmsApi\Console;

use Alizeinodin\SmsApi\Balance\BalanceMonitor;
use Illuminate\Console\Command;
use Throwable;

class CheckBalanceCommand extends Command
{
    protected $signature = 'smsapi:check-balance
                            {--force : Send alert even if cooldown cache is set}';

    protected $description = 'Check SMS wallet credit and notify admins when below the configured threshold';

    public function handle(BalanceMonitor $monitor): int
    {
        if ($this->option('force')) {
            cache()->forget(BalanceMonitor::CACHE_KEY);
        }

        try {
            $result = $monitor->check();
        } catch (Throwable $e) {
            $this->error('Balance check failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if (! $result['checked']) {
            $this->warn('Balance alert is disabled. Set SMSAPI_BALANCE_ALERT_ENABLED=true');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Driver [%s] balance: %s',
            $result['driver'],
            number_format((float) $result['balance'], 0, '.', ',')
        ));

        if (! $result['below_threshold']) {
            $this->info('Balance is above the threshold.');

            return self::SUCCESS;
        }

        if ($result['notified']) {
            $this->warn('Balance is below threshold — admins were notified.');
        } else {
            $this->warn('Balance is below threshold — alert skipped (cooldown active).');
        }

        return self::SUCCESS;
    }
}
