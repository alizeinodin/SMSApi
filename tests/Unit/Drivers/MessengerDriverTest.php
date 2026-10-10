<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\Messengers\BaleDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\DiscordDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\EitaaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\FacebookMessengerDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\GapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\IgapDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\LineDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\RubikaDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\SlackDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\TelegramDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\ViberDriver;
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

    public function test_discord_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['id' => 'discord-1'], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new DiscordDriver([
            'bot_token' => 'discord-token',
        ], $this->client($mock, 'https://discord.com/api/v10/'));

        $response = $driver->send('channel', 'hello', ['channel_id' => '999']);

        $this->assertTrue($response->success);
        $this->assertSame('discord-1', $response->data['message_id']);
    }

    public function test_gap_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['success' => true, 'message_id' => 5], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new GapDriver([
            'token' => 'gap-token',
        ], $this->client($mock, 'https://api.gap.im/'));

        $response = $driver->send('chat', 'hello', ['chat_id' => '1']);

        $this->assertTrue($response->success);
        $this->assertSame('gap', $response->driver);
    }

    public function test_igap_accepts_token_alias(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['ok' => true, 'result' => ['message_id' => 3]], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new IgapDriver([
            'token' => 'igap-token',
        ], $this->client($mock, 'https://bot.igap.net/botigap-token/'));

        $response = $driver->send('chat', 'hello', ['chat_id' => '7']);

        $this->assertTrue($response->success);
        $this->assertSame(3, $response->data['message_id']);
    }

    public function test_line_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);

        $driver = new LineDriver([
            'channel_access_token' => 'line-token',
        ], $this->client($mock, 'https://api.line.me/v2/bot/'));

        $response = $driver->send('U123', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame('line', $response->driver);
    }

    public function test_viber_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['status' => 0, 'message_token' => 88], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new ViberDriver([
            'auth_token' => 'viber-token',
            'sender_name' => 'Bot',
        ], $this->client($mock, 'https://chatapi.viber.com/pa/'));

        $response = $driver->send('viber-user', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame(88, $response->data['message_id']);
    }

    public function test_facebook_messenger_send_message(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['message_id' => 'mid.1'], JSON_THROW_ON_ERROR)),
        ]);

        $driver = new FacebookMessengerDriver([
            'page_access_token' => 'page-token',
            'page_id' => 'me',
        ], $this->client($mock, 'https://graph.facebook.com/v21.0/'));

        $response = $driver->send('PSID', 'hello');

        $this->assertTrue($response->success);
        $this->assertSame('mid.1', $response->data['message_id']);
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
