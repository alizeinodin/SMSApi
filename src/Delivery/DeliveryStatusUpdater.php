<?php

namespace Alizeinodin\SmsApi\Delivery;

use Alizeinodin\SmsApi\Events\SmsDeliveryUpdated;
use Alizeinodin\SmsApi\Models\SmsLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;

class DeliveryStatusUpdater
{
    /**
     * Apply a delivery update to matching sms_logs rows.
     *
     * @return array<int, SmsLog>
     */
    public function apply(DeliveryUpdate $update): array
    {
        $query = SmsLog::query();

        if ($update->messageId !== null && $update->messageId !== '') {
            $query->where('message_id', (string) $update->messageId);
        } elseif ($update->mobile !== null && $update->mobile !== '') {
            $query->where('mobile', $update->mobile)
                ->whereIn('status', ['sent', 'pending'])
                ->where(function ($q) {
                    $q->whereNull('delivery_status')
                        ->orWhere('delivery_status', DeliveryStatus::PENDING)
                        ->orWhere('delivery_status', DeliveryStatus::UNKNOWN);
                })
                ->latest('id')
                ->limit(1);
        } else {
            return [];
        }

        if ($update->driver) {
            $query->where('driver', $update->driver);
        }

        /** @var Collection<int, SmsLog> $logs */
        $logs = $query->get();

        foreach ($logs as $log) {
            $log->fill([
                'delivery_status' => $update->status,
                'delivery_state' => $update->state,
                'delivered_at' => $update->deliveredAt,
                'message_id' => $log->message_id ?: $update->messageId,
            ]);

            if ($update->raw !== []) {
                $response = is_array($log->response) ? $log->response : [];
                $response['delivery'] = $update->raw;
                $log->response = $response;
            }

            $log->save();

            Event::dispatch(new SmsDeliveryUpdated($log, $update));
        }

        return $logs->all();
    }
}
