<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Logging\SmsLogger;
use Alizeinodin\SmsApi\Models\SmsLog;
use Alizeinodin\SmsApi\Support\SensitiveData;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SensitiveDataTest extends TestCase
{
    public function test_redacts_associative_parameters(): void
    {
        $redacted = SensitiveData::redactParameters([
            'CODE' => '12345',
            'name' => 'Ali',
        ]);

        $this->assertSame('[redacted]', $redacted['CODE']);
        $this->assertSame('Ali', $redacted['name']);
    }

    public function test_logger_redacts_template_parameters(): void
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

        $this->app['config']->set('smsapi.logging.enabled', true);
        $this->app['config']->set('smsapi.otp.redact.enabled', true);

        (new SmsLogger)->log(
            'sendTemplate',
            new SendResult(true, 'ok', ['messageId' => '1'], 'array'),
            '09121234567',
            null,
            ['parameters' => ['CODE' => '12345', 'name' => 'Ali']],
        );

        $parameters = SmsLog::query()->first()->parameters;
        $this->assertSame('[redacted]', $parameters['CODE']);
        $this->assertSame('Ali', $parameters['name']);
    }
}
