<?php

namespace Alizeinodin\SmsApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['sometimes', 'string', 'max:191'],
            'messageId' => ['sometimes', 'string', 'max:191'],
            'mobile' => ['sometimes', 'string', 'max:32'],
            'status' => ['sometimes', 'string', 'max:64'],
            'deliveryState' => ['sometimes', 'string', 'max:64'],
            'delivery_status' => ['sometimes', 'string', 'max:64'],
            'items' => ['sometimes', 'array'],
            'items.*.message_id' => ['sometimes', 'string', 'max:191'],
            'items.*.status' => ['sometimes', 'string', 'max:64'],
            'driver' => ['sometimes', 'string', Rule::in(array_keys((array) config('smsapi.drivers', [])))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.array' => 'Bulk delivery payload must be an array of items.',
        ];
    }
}
