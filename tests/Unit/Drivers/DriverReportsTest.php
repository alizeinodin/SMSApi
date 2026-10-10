<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\FarazsmsDriver;
use Alizeinodin\SmsApi\Drivers\MelipayamakDriver;
use Alizeinodin\SmsApi\Drivers\PayamresanDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Reporting, inbox and line endpoints of the providers that share a panel API.
 */
class DriverReportsTest extends TestCase
{
    public function test_payamak_get_message_accepts_an_array_of_delivery_statuses(): void
    {
        $history = [];
        $driver = $this->payamak(new Response(200, [], json_encode([
            'RetStatus' => 1,
            'StrRetStatus' => 'Ok',
            'Value' => [['recId' => 700, 'status' => -1], ['recId' => 700, 'status' => 1]],
        ], JSON_THROW_ON_ERROR)), $history);

        $response = $driver->getMessage(700);

        $this->assertTrue($response->success);
        $this->assertStringContainsString('SendSMS/GetDeliveries2', $this->uri($history));
        $this->assertSame('700', $this->form($history)['recId'] ?? null);
    }

    public function test_payamak_send_mapped_posts_a_json_body(): void
    {
        $history = [];
        $driver = $this->payamak($this->payamakOk(), $history);

        $driver->sendMapped(['سلام', 'خداحافظ'], ['09121111111', '09122222222']);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);

        $this->assertStringContainsString('SendSMS/SendMultipleSMS', $this->uri($history));
        $this->assertStringContainsString('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame(['09121111111', '09122222222'], $payload['to'] ?? null);
        $this->assertSame('5000', $payload['from'] ?? null);
        $this->assertSame(['سلام', 'خداحافظ'], $payload['text'] ?? null);
    }

    public function test_payamak_live_sends_page_the_sent_box(): void
    {
        $history = [];
        $driver = $this->payamak($this->payamakOk(), $history);

        $driver->getLiveSends(pageNumber: 3, pageSize: 20);

        $form = $this->form($history);

        $this->assertStringContainsString('SendSMS/GetMessages', $this->uri($history));
        $this->assertSame('2', $form['location'] ?? null);
        $this->assertSame('40', $form['index'] ?? null);
        $this->assertSame('20', $form['count'] ?? null);
        $this->assertSame('5000', $form['from'] ?? null);
    }

    public function test_payamak_received_reads_the_inbox_location(): void
    {
        $history = [];
        $driver = $this->payamak($this->payamakOk(), $history);

        $driver->getLatestReceived(15);

        $form = $this->form($history);

        $this->assertSame('1', $form['location'] ?? null);
        $this->assertSame('0', $form['index'] ?? null);
        $this->assertSame('15', $form['count'] ?? null);
    }

    public function test_payamak_get_lines_uses_user_numbers(): void
    {
        $history = [];
        $driver = $this->payamak($this->payamakOk(), $history);

        $driver->getLines();

        $this->assertStringContainsString('SendSMS/GetUserNumbers', $this->uri($history));
        $this->assertSame('user', $this->form($history)['username'] ?? null);
    }

    public function test_payamak_still_rejects_a_negative_scalar_value(): void
    {
        $history = [];
        $driver = $this->payamak(new Response(200, [], json_encode([
            'RetStatus' => 1,
            'StrRetStatus' => 'Invalid recId',
            'Value' => -10,
        ], JSON_THROW_ON_ERROR)), $history);

        $this->expectException(SmsApiException::class);
        $this->expectExceptionMessage('Invalid recId');

        $driver->getMessage(1);
    }

    public function test_farazsms_credit_reads_the_account_balance(): void
    {
        $history = [];
        $driver = $this->faraz(new Response(200, [], json_encode([
            'status' => 'success',
            'data' => ['balance' => 120000],
        ], JSON_THROW_ON_ERROR)), $history);

        $response = $driver->getCredit();

        $this->assertSame(120000, $response->data['balance'] ?? null);
        $this->assertStringEndsWith('ws/v1/account/balance', $this->uri($history));
    }

    public function test_farazsms_lists_accessible_lines(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk(['lines' => ['90008361']]), $history);

        $driver->getLines();

        $this->assertStringEndsWith('ws/v1/lines/accessible', $this->uri($history));
    }

    public function test_farazsms_get_message_reads_a_send_request(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk(['id' => 10, 'state' => 'sent']), $history);

        $response = $driver->getMessage(10);

        $this->assertSame('sent', $response->data['state'] ?? null);
        $this->assertStringEndsWith('ws/v1/send_request/10', $this->uri($history));
    }

    public function test_farazsms_received_pages_the_inbox(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk([]), $history);

        $driver->getLiveReceived(pageNumber: 2, pageSize: 30);

        parse_str((string) parse_url($this->uri($history), PHP_URL_QUERY), $query);

        $this->assertStringContainsString('ws/v1/inbox', $this->uri($history));
        $this->assertSame(['page' => '2', 'per_page' => '30'], $query);
    }

    public function test_farazsms_cancel_scheduled_patches_the_lbs_campaign(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk(['id' => 77]), $history);

        $driver->cancelScheduled('77');

        /** @var RequestInterface $request */
        $request = $history[0]['request'];

        $this->assertSame('PATCH', $request->getMethod());
        $this->assertStringEndsWith('ws/v1/lbs/77/cancel', $this->uri($history));
    }

    public function test_farazsms_pattern_uses_named_attributes(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk(['id' => 1]), $history);

        $driver->sendTemplate('989123456789', 'otp-login', ['code' => '4321', 'name' => 'علی']);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);

        $this->assertSame(['code' => '4321', 'name' => 'علی'], $payload['attributes'] ?? null);
        $this->assertSame('09123456789', $payload['recipient'] ?? null);
    }

    public function test_farazsms_positional_pattern_parameters_fall_back_to_param_keys(): void
    {
        $history = [];
        $driver = $this->faraz($this->farazOk(['id' => 1]), $history);

        $driver->sendTemplate('09123456789', 'otp-login', ['4321', 'علی']);

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);

        $this->assertSame(['param1' => '4321', 'param2' => 'علی'], $payload['attributes'] ?? null);
    }

    public function test_sms_webservice_v3_get_message_uses_status_by_id(): void
    {
        $history = [];
        $stack = HandlerStack::create(new MockHandler([
            new Response(200, [], json_encode([
                'Success' => true,
                'Result' => [['Id' => 55, 'DeliveryStatus' => 'Delivered']],
            ], JSON_THROW_ON_ERROR)),
        ]));
        $stack->push(Middleware::history($history));

        $driver = new PayamresanDriver([
            'api_key' => 'key',
            'line_number' => '50004001',
        ], new Client([
            'handler' => $stack,
            'base_uri' => 'https://api.sms-webservice.com/api/V3/',
            'http_errors' => false,
        ]));

        $response = $driver->getMessage(55);

        $this->assertTrue($response->success);
        $this->assertStringEndsWith('StatusById', $this->uri($history));

        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        $payload = json_decode((string) $request->getBody(), true);

        $this->assertSame('key', $payload['ApiKey'] ?? null);
        $this->assertSame([55], $payload['Ids'] ?? null);
    }

    private function payamakOk(): Response
    {
        return new Response(200, [], json_encode([
            'RetStatus' => 1,
            'StrRetStatus' => 'Ok',
            'Value' => '1',
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function farazOk(array $data): Response
    {
        return new Response(200, [], json_encode([
            'status' => 'success',
            'message' => 'OK',
            'data' => $data,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function payamak(Response $response, ?array &$history = null): MelipayamakDriver
    {
        return new MelipayamakDriver([
            'username' => 'user',
            'password' => 'pass',
            'line_number' => '5000',
        ], $this->client($response, $history, 'https://rest.payamak-panel.com/api/'));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function faraz(Response $response, ?array &$history = null): FarazsmsDriver
    {
        return new FarazsmsDriver([
            'api_key' => 'key',
            'line_number' => '90008361',
        ], $this->client($response, $history, 'https://api.iranpayamak.com/'));
    }

    /**
     * @param  list<array<string, mixed>>  $history
     */
    private function client(Response $response, ?array &$history, string $baseUri): Client
    {
        $history ??= [];
        $stack = HandlerStack::create(new MockHandler([$response]));
        $stack->push(Middleware::history($history));

        return new Client([
            'handler' => $stack,
            'base_uri' => $baseUri,
            'http_errors' => false,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $history
     * @return array<string, mixed>
     */
    private function form(array $history): array
    {
        /** @var RequestInterface $request */
        $request = $history[0]['request'];
        parse_str((string) $request->getBody(), $form);

        return $form;
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
