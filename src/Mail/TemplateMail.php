<?php

namespace Alizeinodin\SmsApi\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TemplateMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<string, string>  $parameters
     * @param  array{subject?: string, markdown?: string, from_address?: string|null, from_name?: string|null}  $mail
     */
    public function __construct(
        public string $templateName,
        public array $parameters = [],
        public array $mail = [],
    ) {
    }

    public function build(): self
    {
        $subject = (string) ($this->mail['subject'] ?? config('smsapi.otp.mail.subject', 'کد تایید شما'));
        $markdown = (string) ($this->mail['markdown'] ?? config('smsapi.otp.mail.markdown', 'smsapi::mail.template'));

        $mailable = $this
            ->subject($subject)
            ->markdown($markdown, [
                'template' => $this->templateName,
                'parameters' => $this->parameters,
                'code' => $this->parameters['CODE']
                    ?? $this->parameters['code']
                    ?? $this->parameters['Code']
                    ?? null,
            ]);

        $fromAddress = $this->mail['from_address'] ?? config('smsapi.otp.mail.from.address');
        $fromName = $this->mail['from_name'] ?? config('smsapi.otp.mail.from.name');

        if (is_string($fromAddress) && $fromAddress !== '') {
            $mailable->from(
                $fromAddress,
                is_string($fromName) && $fromName !== '' ? $fromName : null,
            );
        }

        return $mailable;
    }
}
