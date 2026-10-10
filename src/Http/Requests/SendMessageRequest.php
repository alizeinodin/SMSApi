<?php

namespace Alizeinodin\SmsApi\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) config('smsapi.api.enabled', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['send', 'sendTemplate'])],
            'to' => ['required', 'string', 'max:128'],
            'message' => ['required_if:type,send', 'nullable', 'string', 'max:4096'],
            'template_id' => ['required_if:type,sendTemplate', 'nullable', 'string', 'max:191'],
            'parameters' => ['sometimes', 'array'],
            'driver' => ['sometimes', 'string', 'max:64'],
            'failover' => ['sometimes', 'array'],
            'failover.*' => ['string', 'max:64'],
            'options' => ['sometimes', 'array'],
            'options.chat_id' => ['sometimes', 'string', 'max:128'],
        ];
    }

    protected function failedAuthorization(): void
    {
        abort(403, 'SMS API HTTP endpoint is disabled. Run: php artisan smsapi:install api');
    }
}
