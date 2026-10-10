<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

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

    public function test_get_message_selects_a_single_entry(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 123, 'status' => 10]]), $history);

        $response = $driver->getMessage(123);

        $this->assertTrue($response->success);
        $this->assertSame(10, $response->data['status'] ?? null);
        $this->assertStringContainsString('sms/select.json?messageid=123', $this->uri($history));
    }

    public function test_get_live_sends_returns_the_whole_entries_list(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 1], ['messageid' => 2]]), $history);

        $response = $driver->getLiveSends(pageSize: 25);

        $this->assertCount(2, $response->data);
        $this->assertSame(2, $response->data[1]['messageid'] ?? null);
        $this->assertStringContainsString('sms/latestoutbox.json?pagesize=25', $this->uri($history));
    }

    public function test_get_archived_sends_defaults_to_the_last_day(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 3]]), $history);

        $driver->getArchivedSends();

        parse_str((string) parse_url($this->uri($history), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('sms/selectoutbox.json', $this->uri($history));
        $this->assertArrayNotHasKey('enddate', $query);
        $this->assertEqualsWithDelta(time() - 86400, (int) $query['startdate'], 5);
    }

    public function test_received_endpoints_require_a_line_number(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 4, 'message' => 'سلام']]), $history);

        $response = $driver->getLatestReceived();

        $this->assertCount(1, $response->data);
        $this->assertStringContainsString('sms/receive.json?linenumber=10004346&isread=0', $this->uri($history));
    }

    public function test_live_received_pages_the_inbox(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 5]]), $history);

        $driver->getLiveReceived(pageNumber: 2, pageSize: 10);

        parse_str((string) parse_url($this->uri($history), PHP_URL_QUERY), $query);
        $this->assertStringContainsString('sms/inboxpaged.json', $this->uri($history));
        $this->assertSame(['linenumber' => '10004346', 'isread' => '0', 'pagenumber' => '2', 'pagesize' => '10'], $query);
    }

    public function test_cancel_scheduled_calls_the_cancel_endpoint(): void
    {
        $history = [];
        $driver = $this->driver($this->entries([['messageid' => 987, 'status' => 13]]), $history);

        $response = $driver->cancelScheduled('987');

        $this->assertTrue($response->success);
        $this->assertStringContainsString('sms/cancel.json?messageid=987', $this->uri($history));
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     */
    private function entries(array $entries): Response
    {
        return new Response(200, [], json_encode([
            'return' => ['status' => 200, 'message' => 'تایید شد'],
            'entries' => $entries,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function driver(Response $response, array &$history): KavenegarDriver
    {
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($history));

        return new KavenegarDriver([
            'api_key' => 'test-key',
            'line_number' => '10004346',
        ], new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.kavenegar.com/v1/test-key/',
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
