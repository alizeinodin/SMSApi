<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\BehinpayamDriver;
use Alizeinodin\SmsApi\Drivers\FarazsmsDriver;
use Alizeinodin\SmsApi\Drivers\NiksmsDriver;
use Alizeinodin\SmsApi\Drivers\PayamitoDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ProviderHttpDriverTest extends TestCase
{
    public function test_farazsms_pattern_verify(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 'success',
                'message' => 'OK',
                'data' => ['id' => 10],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new FarazsmsDriver([
            'api_key' => 'key',
            'line_number' => '90008361',
        ], $this->client($mock, 'https://api.iranpayamak.com/'));

        $response = $driver->sendTemplate('09123456789', 'otp-login', ['CODE' => '1234']);

        $this->assertTrue($response->success);
        $this->assertSame('farazsms', $response->driver);
    }

    public function test_niksms_send_one(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'Status' => 1,
                'Message' => 'OK',
                'Data' => ['id' => 7],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new NiksmsDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '3000',
        ], $this->client($mock, 'https://niksms.com/api/v2/'));

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
    }

    public function test_payamito_uses_payamak_panel_api(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'RetStatus' => 1,
                'StrRetStatus' => 'OK',
                'Value' => '123',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new PayamitoDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '5000',
        ], $this->client($mock, 'https://rest.payamak-panel.com/api/'));

        $response = $driver->send('09123456789', 'test');

        $this->assertTrue($response->success);
        $this->assertSame('payamito', $response->driver);
    }

    public function test_behinpayam_uses_v3_api(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'Success' => true,
                'Result' => [['id' => 55]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new BehinpayamDriver([
            'api_key' => 'key',
            'line_number' => '50004001',
        ], $this->client($mock, 'https://api.sms-webservice.com/api/V3/'));

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertSame('behinpayam', $response->driver);
    }

    private function client(MockHandler $mock, string $baseUri): Client
    {
        return new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => $baseUri,
            'http_errors' => false,
        ]);
    }
}
