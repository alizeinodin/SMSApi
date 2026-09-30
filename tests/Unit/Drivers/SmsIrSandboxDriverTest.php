<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class SmsIrSandboxDriverTest extends TestCase
{
    public function test_sandbox_verify_posts_template_123456_with_code(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 1,
                'message' => 'موفق',
                'data' => ['messageId' => 99, 'cost' => 0],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.sms.ir/v1/',
            'http_errors' => false,
        ]);

        $driver = new SmsIrDriver([
            'api_key' => 'sandbox-key',
            'line_number' => '3000',
        ], $client);

        $result = $driver->sendSandboxVerify('09121234567', '12345');

        $this->assertTrue($result->success);
        $this->assertSame('99', $result->messageId);
        $this->assertCount(1, $history);

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('send/verify', (string) $request->getUri());

        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(SmsIrDriver::SANDBOX_TEMPLATE_ID, $body['templateId']);
        $this->assertSame('09121234567', $body['mobile']);
        $this->assertSame([['name' => 'Code', 'value' => '12345']], $body['parameters']);
    }
}
