<?php

namespace Alizeinodin\SmsApi\Http\Controllers;

use Alizeinodin\SmsApi\Delivery\DeliveryStatusUpdater;
use Alizeinodin\SmsApi\Delivery\Parsers\GenericDeliveryWebhookParser;
use Alizeinodin\SmsApi\Http\Requests\DeliveryWebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class DeliveryWebhookController extends Controller
{
    public function __invoke(
        DeliveryWebhookRequest $request,
        DeliveryStatusUpdater $updater,
        ?string $driver = null,
    ): JsonResponse {
        $parser = app(GenericDeliveryWebhookParser::class);
        $updates = $parser->parse($request, $driver);

        if ($updates === []) {
            return response()->json([
                'message' => 'No usable delivery payload found.',
                'hint' => 'Send message_id (or mobile) and status/deliveryState.',
            ], 422);
        }

        $updated = [];

        foreach ($updates as $update) {
            foreach ($updater->apply($update) as $log) {
                $updated[] = [
                    'id' => $log->id,
                    'message_id' => $log->message_id,
                    'delivery_status' => $log->delivery_status,
                ];
            }
        }

        return response()->json([
            'status' => 'ok',
            'matched' => count($updated),
            'logs' => $updated,
        ]);
    }
}
