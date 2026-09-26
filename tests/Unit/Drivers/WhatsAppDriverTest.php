<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Drivers;

use Alizeinodin\SmsApi\Drivers\Messengers\WhatsAppDriver;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class WhatsAppDriverTest extends TestCase
{
    public function test_local_iranian_number_is_sent_as_international_digits(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'messages' => [['id' => 'wamid.1']],
            ], JSON_THROW_ON_ERROR)),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $client = new Client([
            'handler' => $stack,
            'base_uri' => 'https://graph.facebook.com/v21.0/',
            'http_errors' => false,
        ]);

        $driver = new WhatsAppDriver([
            'access_token' => 'token',
            'phone_number_id' => '100',
        ], $client);

        $result = $driver->send('09121234567', 'hello');

        $this->assertTrue($result->success);
        $body = json_decode((string) $history[0]['request']->getBody(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('989121234567', $body['to']);
    }
}
