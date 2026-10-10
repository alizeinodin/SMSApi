<?php

namespace Alizeinodin\SmsApi\Delivery\Parsers;

use Alizeinodin\SmsApi\Delivery\DeliveryStatus;
use Alizeinodin\SmsApi\Delivery\DeliveryUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GenericDeliveryWebhookParser implements DeliveryWebhookParser
{
    public function supports(?string $driver, Request $request): bool
    {
        return true;
    }

    public function parse(Request $request, ?string $driver = null): array
    {
        $payload = $request->all();

        // Batch: { "items": [ {...}, ... ] }
        if (isset($payload['items']) && is_array($payload['items'])) {
            $updates = [];

            foreach ($payload['items'] as $item) {
                if (is_array($item)) {
                    $updates[] = $this->mapItem($item, $driver);
                }
            }

            return array_values(array_filter($updates));
        }

        // Single object or flat query/body
        $update = $this->mapItem($payload, $driver);

        return $update ? [$update] : [];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function mapItem(array $item, ?string $driver): ?DeliveryUpdate
    {
        $messageId = $this->firstString($item, [
            'message_id', 'messageId', 'messageid', 'MessageId', 'id',
        ]);

        $mobile = $this->firstString($item, [
            'mobile', 'Mobile', 'receptor', 'to', 'phone',
        ]);

        if ($messageId === null && $mobile === null) {
            return null;
        }

        $state = $this->firstInt($item, [
            'delivery_state', 'deliveryState', 'DeliveryState', 'state',
        ]);

        $statusRaw = $this->firstString($item, [
            'delivery_status', 'status', 'statustext', 'Status',
        ]);

        $status = $state !== null
            ? DeliveryStatus::fromSmsIrState($state)
            : DeliveryStatus::fromString($statusRaw);

        // Prefer explicit Kavenegar numeric status when present and no sms.ir state
        if ($state === null && isset($item['status']) && is_numeric($item['status'])) {
            $status = DeliveryStatus::fromKavenegarStatus((int) $item['status']);
        }

        $deliveredAt = null;
        $timestamp = $this->firstInt($item, [
            'delivered_at', 'deliveryDateTime', 'DeliveryDateTime', 'date',
        ]);

        if ($timestamp !== null && $timestamp > 0) {
            $deliveredAt = Carbon::createFromTimestamp($timestamp);
        } elseif (! empty($item['delivered_at']) && is_string($item['delivered_at'])) {
            $deliveredAt = Carbon::parse($item['delivered_at']);
        } elseif ($status === DeliveryStatus::DELIVERED) {
            $deliveredAt = now();
        }

        return new DeliveryUpdate(
            messageId: $messageId,
            status: $status,
            state: $state,
            mobile: $mobile,
            driver: $this->firstString($item, ['driver']) ?? $driver,
            deliveredAt: $deliveredAt,
            raw: $item,
        );
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item) || $item[$key] === null || $item[$key] === '') {
                continue;
            }

            return (string) $item[$key];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<int, string>  $keys
     */
    private function firstInt(array $item, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $item) || $item[$key] === null || $item[$key] === '') {
                continue;
            }

            if (is_numeric($item[$key])) {
                return (int) $item[$key];
            }
        }

        return null;
    }
}
