<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\AvanakDriver;
use Alizeinodin\SmsApi\Drivers\IppanelDriver;
use Alizeinodin\SmsApi\Drivers\NiksmsDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

class RemainingDriversMethodTest extends TestCase
{
    public function test_ippanel_send_credit_message_and_inbox(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 'OK', 'data' => ['message_id' => 9]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['status' => 'OK', 'data' => ['credit' => 12.5]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['status' => 'OK', 'data' => [['id' => 9]]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['status' => 'OK', 'data' => [['body' => 'hi']]], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new IppanelDriver([
            'api_key' => 'key',
            'line_number' => '3000',
        ], $this->client($mock, $history, 'https://api2.ippanel.com/api/v1/'));

        $this->assertTrue($driver->send('09123456789', 'سلام')->success);
        $this->assertSame(12.5, $driver->getCredit()->data['credit'] ?? null);
        $this->assertTrue($driver->getMessage(9)->success);
        $this->assertTrue($driver->getLatestReceived()->success);

        $paths = array_map(
            static fn (array $item): string => (string) $item['request']->getUri()->getPath(),
            $history,
        );

        $this->assertStringContainsString('sms/send/webservice/single', $paths[0]);
        $this->assertStringContainsString('sms/accounting/credit/show', $paths[1]);
        $this->assertStringContainsString('sms/message/all', $paths[2]);
        $this->assertStringContainsString('inbox', $paths[3]);
    }

    public function test_niksms_credit_status_and_ptp(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['Status' => 1, 'Data' => 100], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['Status' => 1, 'Data' => [['Id' => 7]]], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['Status' => 1, 'Data' => ['ok' => true]], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new NiksmsDriver([
            'username' => 'u',
            'password' => 'p',
            'line_number' => '3000',
        ], $this->client($mock, $history, 'https://niksms.com/api/v2/'));

        $this->assertTrue($driver->getCredit()->success);
        $this->assertTrue($driver->getMessage(7)->success);
        $this->assertTrue($driver->sendMapped(['a', 'b'], ['09121111111', '09122222222'])->success);

        /** @var RequestInterface $ptp */
        $ptp = $history[2]['request'];
        $this->assertStringContainsString('send/ptp', (string) $ptp->getUri());
    }

    public function test_avanak_account_status_and_quick_send(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode(['Credit' => 50, 'ErrorCode' => 0], JSON_THROW_ON_ERROR)),
            new Response(200, [], json_encode(['QuickSendID' => 33, 'ErrorCode' => 0], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new AvanakDriver([
            'api_token' => 'tok',
        ], $this->client($mock, $history, 'https://portal.avanak.ir/rest/'));

        $this->assertTrue($driver->getCredit()->success);
        $this->assertSame(33, $driver->getMessage(33)->data['quick_send_id'] ?? null);

        $this->assertStringContainsString('AccountStatus', (string) $history[0]['request']->getUri());
        $this->assertStringContainsString('GetQuickSend', (string) $history[1]['request']->getUri());
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
