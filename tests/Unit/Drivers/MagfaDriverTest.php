<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\MagfaDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MagfaDriverTest extends TestCase
{
    public function test_send_uses_magfa_http_api(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 0,
                'messages' => [['id' => 555, 'status' => 1]],
                'count' => ['success' => 1, 'fail' => 0],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $client = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'https://sms.magfa.com/api/http/sms/v2/',
            'http_errors' => false,
        ]);

        $driver = new MagfaDriver([
            'username' => 'user',
            'password' => 'pass',
            'domain' => 'magfa',
            'line_number' => '30001234',
        ], $client);

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertSame('magfa', $response->driver);
        $this->assertSame(555, $response->data['id'] ?? null);
    }
}
