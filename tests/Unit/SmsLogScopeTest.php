<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Models\SmsLog;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SmsLogScopeTest extends TestCase
{
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

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver')->nullable();
            $table->string('operation')->nullable();
            $table->string('mobile')->nullable();
            $table->string('status')->nullable();
            $table->string('delivery_status')->nullable();
            $table->timestamps();
        });

        SmsLog::query()->create([
            'driver' => 'array',
            'operation' => 'send',
            'mobile' => '09120000001',
            'status' => 'sent',
            'delivery_status' => 'pending',
        ]);
        SmsLog::query()->create([
            'driver' => 'kavenegar',
            'operation' => 'send',
            'mobile' => '09120000002',
            'status' => 'failed',
            'delivery_status' => null,
        ]);
        SmsLog::query()->create([
            'driver' => 'array',
            'operation' => 'send',
            'mobile' => '09120000001',
            'status' => 'sent',
            'delivery_status' => 'delivered',
        ]);
    }

    public function test_scopes_filter_rows(): void
    {
        $this->assertSame(2, SmsLog::query()->successful()->count());
        $this->assertSame(1, SmsLog::query()->failed()->count());
        $this->assertSame(1, SmsLog::query()->delivered()->count());
        $this->assertSame(1, SmsLog::query()->pendingDelivery()->count());
        $this->assertSame(2, SmsLog::query()->forMobile('09120000001')->count());
        $this->assertSame(1, SmsLog::query()->forDriver('kavenegar')->count());
    }
}
