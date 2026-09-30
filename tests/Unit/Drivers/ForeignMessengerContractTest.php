<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\Messengers\DiscordDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\FacebookMessengerDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\LineDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\SlackDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\ViberDriver;
use Alizeinodin\SmsApi\Drivers\Messengers\WhatsAppDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ForeignMessengerContractTest extends TestCase
{
    public function test_whatsapp_template_payload(): void
    {
        $history = [];
        $driver = $this->driver(WhatsAppDriver::class, [
            'access_token' => 't',
            'phone_number_id' => 'PN',
        ], 'https://graph.facebook.com/v21.0/', [['messages' => [['id' => 'wamid.t']]]], $history);

        $result = $driver->sendTemplate('09121234567', 'otp_login', ['12345'], ['language' => 'fa']);

        $this->assertTrue($result->success);
        $this->assertSame('wamid.t', $result->messageId);
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('template', $body['type']);
        $this->assertSame('otp_login', $body['template']['name']);
        $this->assertSame('fa', $body['template']['language']['code']);
        $this->assertSame('989121234567', $body['to']);
        $this->assertSame('12345', $body['template']['components'][0]['parameters'][0]['text']);
    }

    public function test_messenger_allows_messaging_type_and_tag(): void
    {
        $history = [];
        $driver = $this->driver(FacebookMessengerDriver::class, [
            'page_access_token' => 'p',
            'page_id' => 'me',
        ], 'https://graph.facebook.com/v21.0/', [['message_id' => 'mid.x']], $history);

        $driver->send('PSID', 'hello', [
            'messaging_type' => 'MESSAGE_TAG',
            'tag' => 'HUMAN_AGENT',
        ]);

        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('MESSAGE_TAG', $body['messaging_type']);
        $this->assertSame('HUMAN_AGENT', $body['tag']);
        $this->assertSame('hello', $body['message']['text']);
    }

    public function test_slack_passes_optional_blocks(): void
    {
        $history = [];
        $driver = $this->driver(SlackDriver::class, ['bot_token' => 'x'], 'https://slack.com/api/', [['ok' => true, 'ts' => '9.9']], $history);

        $driver->send('C1', 'hi', ['blocks' => [['type' => 'section']]]);

        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([['type' => 'section']], $body['blocks']);
    }

    public function test_discord_passes_optional_embeds(): void
    {
        $history = [];
        $driver = $this->driver(DiscordDriver::class, ['bot_token' => 'x'], 'https://discord.com/api/v10/', [['id' => '1']], $history);

        $driver->send('CH', 'hi', ['embeds' => [['title' => 'T']]]);

        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame([['title' => 'T']], $body['embeds']);
    }

    public function test_line_accepts_empty_success_body(): void
    {
        $history = [];
        $mock = new MockHandler([new Response(200, [], '')]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));
        $client = new Client(['handler' => $stack, 'base_uri' => 'https://api.line.me/v2/bot/', 'http_errors' => false]);
        $driver = new LineDriver(['channel_access_token' => 'L'], $client);

        $result = $driver->send('U1', 'hi');

        $this->assertTrue($result->success);
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('text', $body['messages'][0]['type']);
    }

    public function test_viber_success_status_zero(): void
    {
        $history = [];
        $driver = $this->driver(ViberDriver::class, ['auth_token' => 'v'], 'https://chatapi.viber.com/pa/', [['status' => 0, 'message_token' => 42]], $history);

        $result = $driver->send('R1', 'hi');

        $this->assertTrue($result->success);
        $this->assertSame('42', $result->messageId);
    }

    /**
     * @param  class-string  $class
     * @param  array<string, mixed>  $config
     * @param  array<int, array<string, mixed>>  $responses
     * @param  array<int, mixed>  $history
     */
    private function driver(string $class, array $config, string $baseUri, array $responses, array &$history): object
    {
        $mock = new MockHandler(array_map(
            fn (array $body) => new Response(200, [], json_encode($body, JSON_THROW_ON_ERROR)),
            $responses,
        ));
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        return new $class($config, new Client([
            'handler' => $stack,
            'base_uri' => $baseUri,
            'http_errors' => false,
        ]));
    }
}
