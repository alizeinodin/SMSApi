<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\MagfaDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

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

    public function test_bulk_senders_match_recipient_count(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 0,
                'messages' => [['id' => 1], ['id' => 2]],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $driver = new MagfaDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '30001234',
        ], new Client([
            'handler' => $stack,
            'base_uri' => 'https://sms.magfa.com/api/http/sms/v2/',
            'http_errors' => false,
        ]));

        $driver->send(['09121111111', '09122222222'], 'سلام');

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);

        $this->assertCount(2, $payload['senders'] ?? []);
        $this->assertCount(2, $payload['recipients'] ?? []);
        $this->assertCount(2, $payload['messages'] ?? []);
        $this->assertSame(['30001234', '30001234'], $payload['senders']);
    }

    public function test_get_message_reads_the_statuses_endpoint(): void
    {
        $history = [];
        $driver = $this->driver(new Response(200, [], json_encode([
            'status' => 0,
            'dlrs' => [['mid' => 555, 'status' => 1, 'date' => '2024-01-01']],
        ], JSON_THROW_ON_ERROR)), $history);

        $response = $driver->getMessage(555);

        $this->assertTrue($response->success);
        $this->assertSame(1, $response->data['status'] ?? null);
        $this->assertStringEndsWith('statuses/555', $this->uri($history));
    }

    public function test_get_latest_received_pops_the_inbox(): void
    {
        $history = [];
        $driver = $this->driver(new Response(200, [], json_encode([
            'status' => 0,
            'messages' => [['id' => 1, 'body' => 'سلام'], ['id' => 2, 'body' => 'خداحافظ']],
        ], JSON_THROW_ON_ERROR)), $history);

        $response = $driver->getLatestReceived(5);

        $this->assertCount(2, $response->data);
        $this->assertSame('خداحافظ', $response->data[1]['body'] ?? null);
        $this->assertStringEndsWith('messages/5', $this->uri($history));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function driver(Response $response, array &$history): MagfaDriver
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($history));

        return new MagfaDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '30001234',
        ], new Client([
            'handler' => $stack,
            'base_uri' => 'https://sms.magfa.com/api/http/sms/v2/',
            'http_errors' => false,
        ]));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function uri(array $history): string
    {
        /** @var RequestInterface $request */
        $request = $history[0]['request'];

        return (string) $request->getUri();
    }
}
