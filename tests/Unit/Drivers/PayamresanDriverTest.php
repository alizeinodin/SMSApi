<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\PayamresanDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class PayamresanDriverTest extends TestCase
{
    public function test_send_bulk_uses_payamresan_api(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'Success' => true,
                'Message' => 'OK',
                'Result' => [['id' => 42, 'userTraceId' => 0]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.sms-webservice.com/api/V3/',
            'http_errors' => false,
        ]);

        $driver = new PayamresanDriver([
            'api_key' => 'test-key',
            'line_number' => '50004001',
        ], $client);

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertSame('payamresan', $response->driver);
    }

    public function test_verify_uses_template_endpoint(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'Success' => true,
                'Result' => ['id' => 99],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://api.sms-webservice.com/api/V3/',
            'http_errors' => false,
        ]);

        $driver = new PayamresanDriver([
            'api_key' => 'test-key',
            'line_number' => '50004001',
        ], $client);

        $response = $driver->sendTemplate('09123456789', 'otp-login', ['CODE' => '1234']);

        $this->assertTrue($response->success);
    }
}
