<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Drivers\KavenegarDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

class SecretRedactionTest extends TestCase
{
    public function test_kavenegar_transport_errors_do_not_leak_the_api_key(): void
    {
        $secret = 'super-secret-key';
        $uri = 'https://api.kavenegar.com/v1/'.$secret.'/sms/send.json';
        $mock = new MockHandler([
            new ConnectException(
                'cURL error 7: Failed to connect for '.$uri,
                new Request('GET', $uri),
            ),
        ]);

        $driver = new KavenegarDriver([
            'api_key' => $secret,
            'line_number' => '10004346',
        ], new Client([
            'handler' => HandlerStack::create($mock),
            'http_errors' => false,
        ]));

        try {
            $driver->send('09123456789', 'سلام');
            $this->fail('Expected a transport exception.');
        } catch (SmsApiException $e) {
            $this->assertStringNotContainsString($secret, $e->getMessage());
            $this->assertStringNotContainsString('https://', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }
}
