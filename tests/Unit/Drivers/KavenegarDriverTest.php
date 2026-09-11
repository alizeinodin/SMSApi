<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class KavenegarDriverTest extends TestCase
{
    public function test_send_uses_kavenegar_api(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'return' => [
                    'status' => 200,
                    'message' => 'تایید شد',
                    'entries' => [['messageid' => 123, 'cost' => 1]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.kavenegar.com/v1/test-key/',
            'http_errors' => false,
        ]);

        $driver = new KavenegarDriver([
            'api_key' => 'test-key',
            'line_number' => '10004346',
        ], $client);

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertSame('kavenegar', $response->driver);
        $this->assertSame(123, $response->data['messageid'] ?? null);
    }

    public function test_verify_maps_template_parameters_to_tokens(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'return' => [
                    'status' => 200,
                    'message' => 'تایید شد',
                    'entries' => [['messageid' => 99]],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.kavenegar.com/v1/test-key/',
            'http_errors' => false,
        ]);

        $driver = new KavenegarDriver([
            'api_key' => 'test-key',
            'line_number' => '10004346',
        ], $client);

        $response = $driver->sendTemplate('09123456789', 'otp-login', ['CODE' => '4321']);

        $this->assertTrue($response->success);
    }
}
