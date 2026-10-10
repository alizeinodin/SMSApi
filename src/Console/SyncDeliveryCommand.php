<?php

namespace Alizeinodin\SmsApi\Console;

use Alizeinodin\SmsApi\Delivery\DeliveryStatus;
use Alizeinodin\SmsApi\Delivery\DeliveryStatusUpdater;
use Alizeinodin\SmsApi\Delivery\DeliveryUpdate;
use Alizeinodin\SmsApi\Models\SmsLog;
use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class SyncDeliveryCommand extends Command
{
    protected $signature = 'smsapi:sync-delivery
                            {--limit=50 : Max logs to check}
                            {--driver= : Only sync this driver}
                            {--hours=48 : Only logs newer than N hours}';

    protected $description = 'Poll providers for delivery status of recent sent messages (useful for sms.ir)';

    public function handle(SmsManager $manager, DeliveryStatusUpdater $updater): int
    {
        if (! (bool) config('smsapi.logging.enabled', false)) {
            $this->warn('Logging is disabled. Install it first:');
            $this->line('  php artisan smsapi:install logging');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $hours = max(1, (int) $this->option('hours'));
        $driverFilter = $this->option('driver');

        $query = SmsLog::query()
            ->whereNotNull('message_id')
            ->where('message_id', '!=', '')
            ->where('status', 'sent')
            ->where(function ($q) {
                $q->whereNull('delivery_status')
                    ->orWhere('delivery_status', DeliveryStatus::PENDING)
                    ->orWhere('delivery_status', DeliveryStatus::UNKNOWN);
            })
            ->where('created_at', '>=', now()->subHours($hours))
            ->latest('id')
            ->limit($limit);

        if (is_string($driverFilter) && $driverFilter !== '') {
            $query->where('driver', $driverFilter);
        }

        $logs = $query->get();

        if ($logs->isEmpty()) {
            $this->info('No pending delivery logs found.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($logs as $log) {
            $driverName = $log->driver ?: $manager->getDefaultDriver();

            try {
                $response = $manager->driver($driverName)->getMessage((int) $log->message_id);
                $data = is_array($response->data) ? $response->data : [];

                $state = isset($data['deliveryState']) ? (int) $data['deliveryState'] : null;
                $status = $state !== null
                    ? DeliveryStatus::fromSmsIrState($state)
                    : DeliveryStatus::UNKNOWN;

                $deliveredAt = null;
                if (! empty($data['deliveryDateTime']) && is_numeric($data['deliveryDateTime'])) {
                    $deliveredAt = Carbon::createFromTimestamp((int) $data['deliveryDateTime']);
                } elseif ($status === DeliveryStatus::DELIVERED) {
                    $deliveredAt = now();
                }

                $matches = $updater->apply(new DeliveryUpdate(
                    messageId: (string) $log->message_id,
                    status: $status,
                    state: $state,
                    mobile: $log->mobile,
                    driver: $driverName,
                    deliveredAt: $deliveredAt,
                    raw: $data,
                ));

                if ($matches !== []) {
                    $updated++;
                    $this->line("#{$log->id} → {$status}");
                }
            } catch (Throwable $e) {
                $failed++;
                $this->warn("#{$log->id} failed: {$e->getMessage()}");
            }
        }

        $this->info("Synced: {$updated}, failed: {$failed}, checked: {$logs->count()}");

        return self::SUCCESS;
    }
}
