<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Logging\SmsLogger;
use Alizeinodin\SmsApi\Models\SmsLog;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SmsLoggerTest extends TestCase
{
    public function test_logger_is_noop_when_enabled_key_is_missing(): void
    {
        // Fresh install without published/merged logging.enabled must not hit the DB.
        $this->app['config']->set('smsapi.logging', []);

        (new SmsLogger)->log(
            'send',
            new SendResult(true, 'ok', null, 'array'),
            '09123456789',
            'hello',
        );

        $this->assertFalse(Schema::hasTable('sms_logs'));
    }

    public function test_logger_is_noop_when_disabled(): void
    {
        $this->createSmsLogsTable();
        $this->app['config']->set('smsapi.logging.enabled', false);

        (new SmsLogger)->log(
            'send',
            new SendResult(true, 'ok', null, 'array'),
            '09123456789',
            'hello',
        );

        $this->assertSame(0, SmsLog::query()->count());
    }

    public function test_logger_persists_when_enabled(): void
    {
        $this->createSmsLogsTable();
        $this->app['config']->set('smsapi.logging.enabled', true);

        (new SmsLogger)->log(
            'send',
            new SendResult(true, 'ok', ['messageId' => '42'], 'array'),
            '09123456789',
            'hello',
        );

        $this->assertSame(1, SmsLog::query()->count());
        $this->assertSame('sent', SmsLog::query()->first()->status);
        $this->assertSame('42', SmsLog::query()->first()->message_id);
    }

    public function test_logger_strips_secrets_from_exception_text(): void
    {
        $this->createSmsLogsTable();
        $this->app['config']->set('smsapi.logging.enabled', true);

        $secret = 'super-secret-key';

        (new SmsLogger)->log(
            'send',
            new SmsApiException('failed for https://api.example.com/v1/'.$secret.'/sms/send.json'),
            '09123456789',
            'hello',
        );

        $message = (string) SmsLog::query()->first()->message;

        $this->assertStringNotContainsString($secret, $message);
        $this->assertStringNotContainsString('https://', $message);
    }

    protected function createSmsLogsTable(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->nullable();
            $table->string('operation');
            $table->string('mobile')->nullable();
            $table->text('content')->nullable();
            $table->string('template_id')->nullable();
            $table->json('parameters')->nullable();
            $table->string('status')->default('pending');
            $table->string('message_id')->nullable();
            $table->string('pack_id')->nullable();
            $table->string('delivery_status')->nullable();
            $table->unsignedSmallInteger('delivery_state')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('message')->nullable();
            $table->json('response')->nullable();
            $table->json('attempts')->nullable();
            $table->unsignedInteger('cost')->nullable();
            $table->timestamps();
        });
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
