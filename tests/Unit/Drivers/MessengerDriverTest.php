<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\Messengers\BaleDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\EitaaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\RubikaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\SlackDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\WhatsAppDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class MessengerDriverTest extends TestCase
{
    public function test_telegram_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'ok' => true,
                'result' => ['message_id' => 42],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new TelegramDriver([
            'bot_token' => '123:abc',
        ], $this->client($mock, 'https://api.telegram.org/bot123%3Aabc/'));

        $response = $driver->send('ignored', 'سلام', ['chat_id' => '999']);

        $this->assertTrue($response->success);
        $this->assertSame('telegram', $response->driver);
        $this->assertSame(42, $response->data['message_id']);
        $this->assertSame('telegram', $driver->getChannel());
    }

    public function test_bale_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'ok' => true,
                'result' => ['message_id' => 7],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new BaleDriver([
            'bot_token' => 'bale-token',
        ], $this->client($mock, 'https://tapi.bale.ai/bot/bale-token/'));

        $response = $driver->send('chat', 'hello', ['chat_id' => '555']);

        $this->assertTrue($response->success);
        $this->assertSame('bale', $response->driver);
        $this->assertSame('bale', $driver->getChannel());
    }

    public function test_whatsapp_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['id' => 'wamid.123']],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new WhatsAppDriver([
            'access_token' => 'meta-token',
            'phone_number_id' => '100',
        ], $this->client($mock, 'https://graph.facebook.com/v21.0/'));

        $response = $driver->send('09123456789', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame('whatsapp', $response->driver);
        $this->assertSame('wamid.123', $response->data['message_id']);
    }

    public function test_rubika_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['message_id' => 'rub-1'], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new RubikaDriver([
            'bot_token' => 'rub-token',
        ], $this->client($mock, 'https://botapi.rubika.ir/v3/rub-token/'));

        $response = $driver->send('chat', 'hello', ['chat_id' => '555']);

        $this->assertTrue($response->success);
        $this->assertSame('rubika', $response->driver);
    }

    public function test_eitaa_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true, 'message_id' => 9], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new EitaaDriver([
            'token' => 'eitaa-token',
        ], $this->client($mock, 'https://eitaayar.ir/'));

        $response = $driver->send('chat', 'hello', ['chat_id' => '111']);

        $this->assertTrue($response->success);
        $this->assertSame('eitaa', $response->driver);
    }

    public function test_slack_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true, 'ts' => '123.456'], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new SlackDriver([
            'bot_token' => 'xoxb-token',
        ], $this->client($mock, 'https://slack.com/api/'));

        $response = $driver->send('C123', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame('slack', $response->driver);
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
