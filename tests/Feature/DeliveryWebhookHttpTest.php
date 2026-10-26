<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Tests\TestCase;

class DeliveryWebhookHttpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('smsapi.webhook.enabled', true);
        $this->app['config']->set('smsapi.webhook.token', 'webhook-secret');
        $this->app['config']->set('smsapi.webhook.path', 'smsapi/webhooks/delivery');
    }

    public function test_delivery_webhook_requires_token(): void
    {
        $response = $this->postJson('/smsapi/webhooks/delivery', [
            'message_id' => 'abc',
            'status' => 'delivered',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delivery_webhook_rejects_query_string_tokens(): void
    {
        $response = $this->postJson('/smsapi/webhooks/delivery?token=webhook-secret', [
            'message_id' => 'abc',
            'status' => 'delivered',
        ]);

        $response->assertUnauthorized();
    }

    public function test_delivery_webhook_validates_optional_fields(): void
    {
        $response = $this->postJson('/smsapi/webhooks/delivery', [
            'items' => 'not-an-array',
        ], [
            'X-SMSAPI-Webhook-Token' => 'webhook-secret',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['items']);
    }

    public function test_delivery_webhook_returns_hint_when_payload_is_empty(): void
    {
        $response = $this->postJson('/smsapi/webhooks/delivery', [], [
            'X-SMSAPI-Webhook-Token' => 'webhook-secret',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('message', 'No usable delivery payload found.');
    }
}
