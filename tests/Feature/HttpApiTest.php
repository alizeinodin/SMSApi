<?php

namespace Alizeinodin\SmsApi\Tests\Feature;

use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\SmsManager;
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

    public function test_send_endpoint_rejects_query_string_tokens(): void
    {
        $response = $this->postJson('/smsapi/messages?token=secret-token', [
            'type' => 'send',
            'to' => '09123456789',
            'message' => 'hello',
        ]);

        $response->assertUnauthorized();
    }

    public function test_send_endpoint_hides_exception_text(): void
    {
        $secret = 'super-secret-key';

        $this->app->make(SmsManager::class)->extend('leaky', function () use ($secret) {
            return new class($secret) extends ArrayDriver
            {
                public function __construct(private string $secret)
                {
                    parent::__construct(['name' => 'leaky']);
                }

                public function send(string|array $mobiles, string $message, array $options = []): SendResult
                {
                    throw new SmsApiException(
                        'HTTP failed for https://api.example.com/v1/'.$this->secret.'/send.json'
                    );
                }
            };
        });

        $response = $this->postJson('/smsapi/messages', [
            'type' => 'send',
            'to' => '09123456789',
            'message' => 'hello',
            'driver' => 'leaky',
        ], [
            'X-SMSAPI-Token' => 'secret-token',
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'The message could not be sent.');
        $this->assertStringNotContainsString($secret, $response->getContent());
    }

    public function test_send_route_is_throttled_by_default(): void
    {
        $route = app('router')->getRoutes()->getByName('smsapi.messages.send');

        $this->assertNotNull($route);
        $this->assertTrue(collect($route->middleware())->contains(
            fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:')
        ));
    }
}
