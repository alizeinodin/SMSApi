<?php

namespace Alizeinodin\SmsApi\Tests\Unit\Otp;

use Alizeinodin\SmsApi\Exceptions\InvalidOtpException;
use Alizeinodin\SmsApi\Exceptions\OtpAttemptsExceededException;
use Alizeinodin\SmsApi\Exceptions\OtpException;
use Alizeinodin\SmsApi\Exceptions\OtpExpiredException;
use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Otp\OtpChallenge;
use Alizeinodin\SmsApi\Otp\OtpStore;
use Alizeinodin\SmsApi\Otp\OtpVault;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OtpVaultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createOtpTable();
        $this->app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $this->app['config']->set('smsapi.otp.vault', [
            'enabled' => true,
            'store' => 'database',
            'length' => 5,
            'ttl' => 300,
            'max_attempts' => 3,
            'pepper' => 'test-pepper',
            'code_parameter' => 'CODE',
            'reveal_code' => true,
            'throttle' => [
                'enabled' => false,
                'max' => 3,
                'per_seconds' => 60,
                'per_purpose' => true,
            ],
        ]);
        $this->app['config']->set('smsapi.default', 'array');
    }

    public function test_challenge_issue_and_check_succeeds(): void
    {
        Sms::fake();

        $issued = Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('12345')
            ->sync()
            ->send();

        $this->assertTrue($issued->successful());
        $this->assertSame('12345', $issued->code);
        $this->assertSame(1, OtpChallenge::query()->count());
        $this->assertNotSame('12345', OtpChallenge::query()->first()->code_hash);

        Sms::assertTemplateSent('09121234567');

        $result = Sms::challenge('login')->check('09121234567', '12345');
        $this->assertTrue($result->ok());
        $this->assertNotNull(OtpChallenge::query()->first()->consumed_at);
    }

    public function test_check_rejects_wrong_code_and_tracks_attempts(): void
    {
        Sms::fake();

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('12345')
            ->sync()
            ->send();

        try {
            Sms::challenge('login')->check('09121234567', '00000');
            $this->fail('Expected InvalidOtpException');
        } catch (InvalidOtpException $e) {
            $this->assertSame(2, $e->attemptsRemaining);
        }

        $this->assertSame(1, OtpChallenge::query()->first()->attempts);

        $soft = Sms::challenge('login')->check('09121234567', '00000', throw: false);
        $this->assertFalse($soft->ok());
        $this->assertSame('mismatch', $soft->reason);
        $this->assertSame(1, $soft->attemptsRemaining);
    }

    public function test_check_locks_after_max_attempts(): void
    {
        Sms::fake();

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('12345')
            ->maxAttempts(2)
            ->sync()
            ->send();

        Sms::challenge('login')->check('09121234567', '00000', throw: false);
        $this->expectException(OtpAttemptsExceededException::class);
        Sms::challenge('login')->check('09121234567', '00000');
    }

    public function test_expired_challenge_fails(): void
    {
        Sms::fake();

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('12345')
            ->ttl(1)
            ->sync()
            ->send();

        OtpChallenge::query()->update(['expires_at' => now()->subMinute()]);

        $this->expectException(OtpExpiredException::class);
        Sms::challenge('login')->check('09121234567', '12345');
    }

    public function test_new_issue_invalidates_previous_challenge(): void
    {
        Sms::fake();

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('11111')
            ->sync()
            ->send();

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('22222')
            ->sync()
            ->send();

        $this->assertSame(2, OtpChallenge::query()->count());
        $this->assertSame(1, OtpChallenge::query()->whereNull('consumed_at')->count());

        $this->expectException(InvalidOtpException::class);
        Sms::challenge('login')->check('09121234567', '11111');
    }

    public function test_vault_disabled_throws(): void
    {
        $this->app['config']->set('smsapi.otp.vault.enabled', false);

        $this->expectException(OtpException::class);
        Sms::challenge('login')->template('otp-login')->to('09121234567')->send();
    }

    public function test_template_is_required(): void
    {
        $this->expectException(OtpException::class);
        Sms::challenge('login')->to('09121234567')->send();
    }

    public function test_throttle_per_recipient(): void
    {
        Sms::fake();
        $this->app['config']->set('smsapi.otp.vault.throttle', [
            'enabled' => true,
            'max' => 1,
            'per_seconds' => 60,
            'per_purpose' => true,
        ]);

        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('12345')
            ->sync()
            ->send();

        $this->expectException(RateLimitExceededException::class);
        Sms::challenge('login')
            ->template('otp-login')
            ->to('09121234567')
            ->usingCode('54321')
            ->sync()
            ->send();
    }

    public function test_cache_store_roundtrip(): void
    {
        $this->app['config']->set('smsapi.otp.vault.store', 'cache');
        $this->app->forgetInstance(OtpStore::class);
        $this->app->forgetInstance(OtpVault::class);

        Sms::fake();

        $issued = Sms::challenge('login')
            ->template('otp-login')
            ->to('09129876543')
            ->usingCode('99999')
            ->sync()
            ->send();

        $this->assertTrue($issued->successful());
        $this->assertTrue(Sms::challenge('login')->check('09129876543', '99999')->ok());
    }

    protected function createOtpTable(): void
    {
        Schema::dropIfExists('sms_otp_challenges');
        Schema::create('sms_otp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('recipient', 191);
            $table->string('purpose', 100);
            $table->string('code_hash', 64);
            $table->timestamp('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('consumed_at')->nullable();
            $table->timestamps();
        });
    }
}
