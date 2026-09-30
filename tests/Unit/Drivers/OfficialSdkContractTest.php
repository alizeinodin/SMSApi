<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\GhasedakDriver;
use Alizeinodin\SmsApi\Drivers\IppanelDriver;
use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Drivers\LimosmsDriver;
use Alizeinodin\SmsApi\Drivers\MedianaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\GapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Drivers\SmsIrDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Assert outbound HTTP matches official SDK contracts (without live credentials).
 */
class OfficialSdkContractTest extends TestCase
{
    public function test_ghasedak_send_matches_official_sdk(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            GhasedakDriver::class,
            ['api_key' => 'test-key'],
            'https://api.ghasedak.me/v2/',
            [['result' => ['code' => 200, 'message' => 'OK']]],
            $history,
            ['apikey' => 'test-key'],
        );

        $driver->send('09121234567', 'Hello', ['lineNumber' => '3000']);

        $this->assertCount(1, $history);
        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('sms/send/simple', (string) $request->getUri());
        $this->assertStringContainsString('agent=', (string) $request->getUri());
        $this->assertSame('test-key', $request->getHeaderLine('apikey'));
        parse_str((string) $request->getBody(), $form);
        $this->assertSame('09121234567', $form['receptor']);
        $this->assertSame('Hello', $form['message']);
        $this->assertSame('3000', $form['linenumber']);
    }

    public function test_ghasedak_allows_null_linenumber_like_official_sdk(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            GhasedakDriver::class,
            ['api_key' => 'test-key'],
            'https://api.ghasedak.me/v2/',
            [['result' => ['code' => 200, 'message' => 'OK']]],
            $history,
            ['apikey' => 'test-key'],
        );

        $driver->send('09121234567', 'Hello');

        parse_str((string) $history[0]['request']->getBody(), $form);
        $this->assertArrayNotHasKey('linenumber', $form);
    }

    public function test_ippanel_uses_apikey_header_and_webservice_single(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            IppanelDriver::class,
            ['api_key' => 'ip-key', 'line_number' => '+9810001'],
            'https://api2.ippanel.com/api/v1/',
            [['status' => 'OK', 'data' => ['message_id' => 12]]],
            $history,
            ['apikey' => 'ip-key'],
        );

        $driver->send('989121234567', 'hi');

        $request = $history[0]['request'];
        $this->assertSame('ip-key', $request->getHeaderLine('apikey'));
        $this->assertStringContainsString('sms/send/webservice/single', (string) $request->getUri());
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('+9810001', $body['sender']);
        // Package normalizes Iranian mobiles to local 09… by default.
        $this->assertSame(['09121234567'], $body['recipient']);
        $this->assertArrayHasKey('description', $body);
    }

    public function test_smsir_verify_contract(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            SmsIrDriver::class,
            ['api_key' => 'k'],
            'https://api.sms.ir/v1/',
            [['status' => 1, 'message' => 'موفق', 'data' => ['messageId' => 1]]],
            $history,
            ['x-api-key' => 'k'],
        );

        $driver->sendSandboxVerify('09121234567', '12345');

        $request = $history[0]['request'];
        $this->assertSame('k', $request->getHeaderLine('x-api-key'));
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(123456, $body['templateId']);
        $this->assertSame([['name' => 'Code', 'value' => '12345']], $body['parameters']);
    }

    public function test_kavenegar_send_puts_key_in_path(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            KavenegarDriver::class,
            ['api_key' => 'abc', 'line_number' => '1000'],
            'https://api.kavenegar.com/v1/abc/',
            [['return' => ['status' => 200, 'message' => 'OK'], 'entries' => [['messageid' => 9]]]],
            $history,
        );

        $driver->send('09121234567', 'سلام');

        $request = $history[0]['request'];
        $this->assertStringContainsString('sms/send.json', (string) $request->getUri());
        parse_str((string) $request->getBody(), $form);
        $this->assertSame('09121234567', $form['receptor']);
        $this->assertSame('1000', $form['sender']);
    }

    public function test_limosms_sendsms_contract(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            LimosmsDriver::class,
            ['api_key' => 'k', 'line_number' => '3000'],
            'https://api.limosms.com/',
            [['Success' => true, 'Message' => 'OK', 'MessageId' => [11]]],
            $history,
            ['ApiKey' => 'k'],
        );

        $result = $driver->send('09121234567', 'hi');

        $this->assertTrue($result->success);
        $request = $history[0]['request'];
        $this->assertStringContainsString('api/sendsms', (string) $request->getUri());
        $this->assertSame('k', $request->getHeaderLine('ApiKey'));
        $body = json_decode((string) $request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(['09121234567'], $body['MobileNumber']);
        $this->assertSame('3000', $body['SenderNumber']);
    }

    public function test_mediana_access_key_and_messages_path(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            MedianaDriver::class,
            ['api_key' => 'mk', 'line_number' => '1000'],
            'https://api.mediana.ir/',
            [['status' => 'OK', 'data' => ['bulk_id' => 55]]],
            $history,
            ['Authorization' => 'AccessKey mk'],
        );

        $driver->send('09121234567', 'hi');

        $request = $history[0]['request'];
        $this->assertSame('AccessKey mk', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('v1/messages', (string) $request->getUri());
    }

    public function test_telegram_send_message_contract(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            TelegramDriver::class,
            ['bot_token' => 'TOKEN'],
            'https://api.telegram.org/botTOKEN/',
            [['ok' => true, 'result' => ['message_id' => 7]]],
            $history,
        );

        $result = $driver->send('123', 'hello');

        $this->assertTrue($result->success);
        $this->assertSame('7', $result->messageId);
        $this->assertStringContainsString('sendMessage', (string) $history[0]['request']->getUri());
    }

    public function test_gap_send_includes_required_type_field(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            GapDriver::class,
            ['token' => 'gap-token'],
            'https://api.gap.im/',
            [['id' => 1]],
            $history,
            ['token' => 'gap-token'],
        );

        $driver->send('991234567', 'hello');

        $request = $history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('sendMessage', (string) $request->getUri());
        parse_str((string) $request->getBody(), $form);
        $this->assertSame('991234567', $form['chat_id']);
        $this->assertSame('text', $form['type']);
        $this->assertSame('hello', $form['data']);
    }

    public function test_api_error_prefers_description_and_error_fields(): void
    {
        $history = [];
        $driver = $this->driverWithHistory(
            TelegramDriver::class,
            ['bot_token' => 'TOKEN'],
            'https://api.telegram.org/botTOKEN/',
            [['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized']],
            $history,
        );

        try {
            $driver->send('1', 'x');
            $this->fail('Expected SmsApiException');
        } catch (SmsApiException $e) {
            $this->assertSame('Unauthorized', $e->getMessage());
            $this->assertSame(401, $e->getCode());
        }
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $config
     * @param  array<int, array<string, mixed>>  $responses
     * @param  array<int, mixed>  $history
     * @param  array<string, string>  $headers
     */
    private function driverWithHistory(
        string $class,
        array $config,
        string $baseUri,
        array $responses,
        array &$history,
        array $headers = [],
    ): object {
        $mock = new MockHandler(array_map(
            fn (array $body) => new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR)),
            $responses,
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => $baseUri,
            'http_errors' => false,
            'headers' => $headers,
        ]);

        return new $class($config, $client);
    }
}
