<?php

namespace Alizeinodin\SmsApi\Http\Controllers;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Http\Requests\SendMessageRequest;
use Alizeinodin\SmsApi\SmsManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class SendMessageController extends Controller
{
    public function __invoke(SendMessageRequest $request, SmsManager $manager): JsonResponse
    {
        $data = $request->validated();

        try {
            $driver = $this->resolveDriver($manager, $data);
            $options = (array) ($data['options'] ?? []);

            if (($data['type'] ?? 'send') === 'sendTemplate') {
                $response = $driver->sendTemplate(
                    (string) $data['to'],
                    $data['template_id'],
                    (array) ($data['parameters'] ?? []),
                    $options,
                );
            } else {
                $response = $driver->send((string) $data['to'], (string) $data['message'], $options);
            }

            return response()->json([
                'success' => $response->success,
                'message' => $response->message,
                'driver' => $response->driver,
                'data' => $response->data,
            ]);
        } catch (SmsApiException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDriver(SmsManager $manager, array $data)
    {
        if (! empty($data['failover']) && is_array($data['failover'])) {
            return $manager->failover(array_values($data['failover']));
        }

        if (! empty($data['driver'])) {
            return $manager->driver((string) $data['driver']);
        }

        return $manager->driver();
    }
}
