<?php

namespace Alizeinodin\SmsApi\Logging;

use Alizeinodin\SmsApi\Delivery\DeliveryStatus;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\FailoverException;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Models\SmsLog;
use Alizeinodin\SmsApi\Support\ConfigBoolean;
use Alizeinodin\SmsApi\Support\SensitiveData;
use Illuminate\Support\Arr;
use Throwable;

class SmsLogger
{
    /**
     * @param  string|array<int, string>|null  $mobiles
     * @param  array<string, mixed>  $context
     */
    public function log(
        string $operation,
        SendResult|Throwable $result,
        string|array|null $mobiles = null,
        ?string $content = null,
        array $context = [],
    ): void {
        if (! ConfigBoolean::get(config('smsapi.logging.enabled', false))) {
            return;
        }

        $mobile = is_array($mobiles)
            ? implode(',', array_slice($mobiles, 0, 5)).(count($mobiles) > 5 ? '…' : '')
            : $mobiles;

        $content = $this->redactContent($content);
        $context['parameters'] = $this->redactParameters(
            isset($context['parameters']) && is_array($context['parameters'])
                ? $context['parameters']
                : null
        );

        if ($result instanceof Throwable) {
            $exceptionResponse = $result instanceof SmsApiException ? $result->response : null;

            SmsLog::query()->create([
                'driver' => $context['driver'] ?? null,
                'operation' => $operation,
                'mobile' => $mobile,
                'content' => $content,
                'template_id' => isset($context['template_id']) ? (string) $context['template_id'] : null,
                'parameters' => $context['parameters'] ?? null,
                'status' => 'failed',
                'delivery_status' => null,
                'message' => $result->getMessage(),
                'response' => is_array($exceptionResponse) ? $this->redactParameters($exceptionResponse) : $exceptionResponse,
                'attempts' => $result instanceof FailoverException
                    ? $result->attempts
                    : null,
                'cost' => null,
            ]);

            return;
        }

        $data = is_array($result->data) ? $result->data : [];
        $response = $this->redactParameters($result->toArray());

        SmsLog::query()->create([
            'driver' => $result->driver !== '' ? $result->driver : ($context['driver'] ?? null),
            'operation' => $operation,
            'mobile' => $mobile,
            'content' => $content,
            'template_id' => isset($context['template_id']) ? (string) $context['template_id'] : null,
            'parameters' => $context['parameters'] ?? null,
            'status' => $result->success ? 'sent' : 'failed',
            'message_id' => $this->extractMessageId($data),
            'pack_id' => $this->extractPackId($data),
            'delivery_status' => $result->success ? DeliveryStatus::PENDING : null,
            'message' => $result->message,
            'response' => $response,
            'attempts' => $result->attempts !== [] ? $result->attempts : null,
            'cost' => $this->extractCost($result->data),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractMessageId(array $data): ?string
    {
        if (isset($data['messageId']) && $data['messageId'] !== '' && $data['messageId'] !== null) {
            return (string) $data['messageId'];
        }

        if (isset($data['messageid']) && $data['messageid'] !== '' && $data['messageid'] !== null) {
            return (string) $data['messageid'];
        }

        $ids = $data['messageIds'] ?? null;

        if (is_array($ids) && isset($ids[0]) && $ids[0] !== null && $ids[0] !== '') {
            return (string) $ids[0];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function extractPackId(array $data): ?string
    {
        if (isset($data['packId']) && $data['packId'] !== '' && $data['packId'] !== null) {
            return (string) $data['packId'];
        }

        return null;
    }

    protected function extractCost(mixed $data): ?int
    {
        if (! is_array($data)) {
            return null;
        }

        $cost = Arr::get($data, 'cost');

        return is_numeric($cost) ? (int) $cost : null;
    }

    /**
     * @param  array<string, mixed>|list<mixed>|null  $parameters
     * @return array<string, mixed>|list<mixed>|null
     */
    protected function redactParameters(?array $parameters): ?array
    {
        if (! ConfigBoolean::get(config('smsapi.otp.redact.enabled', true), true)) {
            return $parameters;
        }

        return SensitiveData::redactParameters(
            $parameters,
            (array) config('smsapi.otp.redact.keys', SensitiveData::defaultKeys()),
            (string) config('smsapi.otp.redact.replacement', '[redacted]'),
        );
    }

    protected function redactContent(?string $content): ?string
    {
        if (! ConfigBoolean::get(config('smsapi.otp.redact.enabled', true), true)) {
            return $content;
        }

        return SensitiveData::redactContent(
            $content,
            (array) config('smsapi.otp.redact.keys', SensitiveData::defaultKeys()),
            (string) config('smsapi.otp.redact.replacement', '[redacted]'),
        );
    }
}
