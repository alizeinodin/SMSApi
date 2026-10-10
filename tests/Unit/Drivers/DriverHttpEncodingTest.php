<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\GhasedakDriver;
use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\MelipayamakDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class DriverHttpEncodingTest extends TestCase
{
    public function test_ghasedak_posts_form_urlencoded_not_json(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'result' => ['code' => 200, 'message' => 'success'],
                'items' => [['messageid' => 1]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new GhasedakDriver([
            'api_key' => 'key',
            'line_number' => '3000',
        ], $this->client($mock, $history, 'https://api.ghasedak.me/v2/'));

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertNotEmpty($history);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));
        parse_str((string) $request->getBody(), $body);
        $this->assertSame('09123456789', $body['receptor'] ?? null);
        $this->assertSame('سلام', $body['message'] ?? null);
        $this->assertSame('3000', $body['linenumber'] ?? null);
    }

    public function test_kavenegar_bulk_posts_form_with_json_encoded_arrays(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'return' => ['status' => 200, 'message' => 'تایید شد'],
                'entries' => [['messageid' => 1], ['messageid' => 2]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new KavenegarDriver([
            'api_key' => 'test-key',
            'line_number' => '1000',
        ], $this->client($mock, $history, 'https://api.kavenegar.com/v1/test-key/'));

        $response = $driver->send(['09121111111', '09122222222'], 'سلام');

        $this->assertTrue($response->success);
        $this->assertNotEmpty($history);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('sendarray.json', (string) $request->getUri());
        $this->assertStringContainsString('application/x-www-form-urlencoded', $request->getHeaderLine('Content-Type'));

        parse_str((string) $request->getBody(), $body);
        $this->assertSame('["09121111111","09122222222"]', $body['receptor'] ?? null);
        $this->assertSame('["سلام","سلام"]', $body['message'] ?? null);
        $this->assertSame('["1000","1000"]', $body['sender'] ?? null);
    }

    public function test_payamak_rejects_negative_value_as_failure(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'RetStatus' => 1,
                'StrRetStatus' => 'Invalid username or password',
                'Value' => -1,
            ], JSON_THROW_ON_ERROR)),
        ]);

        $history = [];
        $driver = new MelipayamakDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '5000',
        ], $this->client($mock, $history, 'https://rest.payamak-panel.com/api/'));

        $this->expectException(SmsApiException::class);
        $driver->send('09123456789', 'test');
    }

    public function test_smsir_sends_json_with_api_key_header(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 1,
                'message' => 'موفق',
                'data' => ['messageId' => 42],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new SmsIrDriver([
            'api_key' => 'smsir-key',
            'line_number' => '30007',
        ], $this->client($mock, $history, 'https://api.sms.ir/v1/'));

        $response = $driver->send('09123456789', 'سلام');

        $this->assertTrue($response->success);
        $this->assertNotEmpty($history);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('send/bulk', (string) $request->getUri());
        $json = json_decode((string) $request->getBody(), true);
        $this->assertSame('سلام', $json['messageText'] ?? null);
        $this->assertSame(['09123456789'], $json['mobiles'] ?? null);
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function client(MockHandler $mock, array &$history, string $baseUri): Client
    {
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'base_uri' => $baseUri,
            'http_errors' => false,
        ]);
    }
}
