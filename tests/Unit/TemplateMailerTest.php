<?php

namespace Alizeinodin\SmsApi\Tests\Unit;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Facades\Sms;
use Alizeinodin\SmsApi\Mail\TemplateMail;
use Alizeinodin\SmsApi\Mail\TemplateMailer;
use Alizeinodin\SmsApi\Templates\TemplateDefinition;
use Alizeinodin\SmsApi\Tests\TestCase;
use Illuminate\Support\Facades\Mail;

class TemplateMailerTest extends TestCase
{
    public function test_it_sends_template_mail(): void
    {
        Mail::fake();

        $mailer = new TemplateMailer;
        $result = $mailer->send('user@example.com', 'otp-login', ['CODE' => '4321']);

        $this->assertTrue($result->success);
        $this->assertSame('mail', $result->driver);
        $this->assertSame('user@example.com', $result->data['email'] ?? null);

        Mail::assertSent(TemplateMail::class, function (TemplateMail $mail): bool {
            return $mail->hasTo('user@example.com')
                && $mail->templateName === 'otp-login'
                && ($mail->parameters['CODE'] ?? null) === '4321';
        });
    }

    public function test_it_queues_template_mail(): void
    {
        Mail::fake();

        $result = (new TemplateMailer)->send(
            'user@example.com',
            'otp-login',
            ['CODE' => '1111'],
            queue: true,
        );

        $this->assertTrue($result->success);
        $this->assertTrue($result->data['queued'] ?? false);
        Mail::assertQueued(TemplateMail::class);
    }

    public function test_it_builds_mail_message_with_interpolated_code(): void
    {
        $message = (new TemplateMailer)->toMailMessage('otp-login', ['CODE' => '9999']);

        $this->assertSame('کد تایید شما', $message->subject);
        $this->assertNotEmpty($message->introLines);
    }

    public function test_it_rejects_empty_email(): void
    {
        $this->expectException(SmsApiException::class);
        (new TemplateMailer)->send('  ', 'otp-login', ['CODE' => '1']);
    }

    public function test_template_send_via_mail_facade(): void
    {
        Mail::fake();

        $this->app['config']->set('smsapi.templates.otp-login', [
            'array' => 'otp-array',
            'mail' => [
                'subject' => 'Login code',
                'text' => 'Your code is :CODE',
            ],
        ]);

        $result = Sms::template('otp-login')
            ->to('otp@example.com')
            ->via('mail')
            ->send(['CODE' => '5555']);

        $this->assertTrue($result->success);
        Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo('otp@example.com'));
    }

    public function test_definition_mail_overrides_are_applied(): void
    {
        Mail::fake();

        $definition = new TemplateDefinition(
            name: 'otp-login',
            drivers: ['array' => 'x'],
            mail: [
                'subject' => 'Custom subject',
                'from' => ['address' => 'noreply@smsapi.test', 'name' => 'SMSApi'],
            ],
        );

        (new TemplateMailer)->send(
            'user@example.com',
            'otp-login',
            ['CODE' => '1'],
            definition: $definition,
        );

        Mail::assertSent(TemplateMail::class, function (TemplateMail $mail): bool {
            return ($mail->mail['subject'] ?? null) === 'Custom subject'
                && ($mail->mail['from_address'] ?? null) === 'noreply@smsapi.test';
        });
    }
}
