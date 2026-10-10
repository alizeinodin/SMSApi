<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Tests\TestCase;

class HttpApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['config']->set('smsapi.api.enabled', true);
        $this->app['config']->set('smsapi.api.token', 'secret-token');
        $this->app['config']->set('smsapi.api.require_token', true);
        $this->app['config']->set('smsapi.api.prefix', 'smsapi');
        $this->app['config']->set('smsapi.default', 'array');
    }

    public function test_send_endpoint_requires_valid_token(): void
    {
        $response = $this->postJson('/smsapi/messages', [
            'type' => 'send',
            'to' => '09123456789',
            'message' => 'hello',
        ]);

        $response->assertUnauthorized();
    }

    public function test_send_endpoint_validates_payload(): void
    {
        $response = $this->postJson('/smsapi/messages', [
            'type' => 'send',
        ], [
            'X-SMSAPI-Token' => 'secret-token',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['to', 'message']);
    }

    public function test_send_endpoint_accepts_message_with_array_driver(): void
    {
        $response = $this->postJson('/smsapi/messages', [
            'type' => 'send',
            'to' => '09123456789',
            'message' => 'hello from api',
            'driver' => 'array',
        ], [
            'X-SMSAPI-Token' => 'secret-token',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('driver', 'array');
    }

    public function test_send_endpoint_returns_not_found_when_disabled(): void
    {
        $this->app['config']->set('smsapi.api.enabled', false);

        $response = $this->postJson('/smsapi/messages', [
            'type' => 'send',
            'to' => '09123456789',
            'message' => 'hello',
        ], [
            'X-SMSAPI-Token' => 'secret-token',
        ]);

        $response->assertNotFound();
    }
}
