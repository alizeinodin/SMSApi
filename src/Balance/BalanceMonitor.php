<?php

namespace Alizeinodin\SmsApi\Balance;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Notifications\LowBalanceNotification;
use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class BalanceMonitor
{
    public const CACHE_KEY = 'smsapi.balance_alert.notified';

    public function __construct(private SmsManager $manager)
    {
    }

    /**
     * Check wallet credit and notify admins when below the configured threshold.
     *
     * @return array{checked: bool, below_threshold: bool, balance: float|null, notified: bool, driver: string|null}
     *
     * @throws SmsApiException
     */
    public function check(): array
    {
        if (! (bool) config('smsapi.balance_alert.enabled', false)) {
            return [
                'checked' => false,
                'below_threshold' => false,
                'balance' => null,
                'notified' => false,
                'driver' => null,
            ];
        }

        $driverName = (string) (config('smsapi.balance_alert.driver') ?: $this->manager->getDefaultDriver());
        $threshold = (float) config('smsapi.balance_alert.threshold', 0);
        $response = $this->manager->driver($driverName)->getCredit();
        $balance = $this->extractBalance($response->data);

        if ($balance >= $threshold) {
            Cache::forget(self::CACHE_KEY);

            return [
                'checked' => true,
                'below_threshold' => false,
                'balance' => $balance,
                'notified' => false,
                'driver' => $driverName,
            ];
        }

        $shouldNotify = ! Cache::has(self::CACHE_KEY);

        if ($shouldNotify) {
            $this->notifyAdmins($balance, $threshold, $driverName);
            $this->markNotified();
        }

        return [
            'checked' => true,
            'below_threshold' => true,
            'balance' => $balance,
            'notified' => $shouldNotify,
            'driver' => $driverName,
        ];
    }

    protected function markNotified(): void
    {
        $cooldown = max(1, (int) config('smsapi.balance_alert.cooldown_minutes', 360));

        Cache::put(self::CACHE_KEY, now()->toIso8601String(), now()->addMinutes($cooldown));
    }

    protected function notifyAdmins(float $balance, float $threshold, string $driver): void
    {
        $channels = $this->channels();
        $notification = new LowBalanceNotification($balance, $threshold, $driver);

        if (in_array('log', $channels, true)) {
            Log::warning('SMS wallet balance is below threshold.', [
                'driver' => $driver,
                'balance' => $balance,
                'threshold' => $threshold,
            ]);
        }

        foreach ($this->mailRecipients() as $email) {
            Notification::route('mail', $email)->notify($notification);
        }

        foreach ($this->smsRecipients() as $mobile) {
            Notification::route('sms', $mobile)->notify($notification);
        }

        if (in_array('database', $channels, true)) {
            foreach ($this->databaseNotifiables() as $notifiable) {
                $notifiable->notify($notification);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    protected function channels(): array
    {
        $channels = config('smsapi.balance_alert.channels', ['log']);

        if (is_string($channels)) {
            $channels = array_map('trim', explode(',', $channels));
        }

        return array_values(array_filter((array) $channels));
    }

    /**
     * @return array<int, string>
     */
    protected function mailRecipients(): array
    {
        if (! in_array('mail', $this->channels(), true)) {
            return [];
        }

        return $this->csvList(config('smsapi.balance_alert.mail.to', []));
    }

    /**
     * @return array<int, string>
     */
    protected function smsRecipients(): array
    {
        if (! in_array('sms', $this->channels(), true)) {
            return [];
        }

        return $this->csvList(config('smsapi.balance_alert.sms.to', []));
    }

    /**
     * @return array<int, object>
     */
    protected function databaseNotifiables(): array
    {
        $modelClass = (string) config('smsapi.balance_alert.database.model', '');
        $ids = $this->csvList(config('smsapi.balance_alert.database.ids', []));

        if ($modelClass === '' || $ids === [] || ! class_exists($modelClass)) {
            return [];
        }

        try {
            return $modelClass::query()->whereIn((new $modelClass)->getKeyName(), $ids)->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    protected function csvList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        return array_values(array_filter(array_map(
            static fn ($item) => trim((string) $item),
            (array) $value
        ), static fn ($item) => $item !== ''));
    }

    protected function extractBalance(mixed $data): float
    {
        if (is_numeric($data)) {
            return (float) $data;
        }

        if (is_array($data) && isset($data['credit']) && is_numeric($data['credit'])) {
            return (float) $data['credit'];
        }

        throw new SmsApiException('Unable to read wallet credit from provider response.');
    }
}
