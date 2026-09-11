<?php

namespace Alizeinodin\SmsApi\Mail;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Templates\TemplateDefinition;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Mail;

/**
 * Sends template content over Laravel Mail.
 */
class TemplateMailer
{
    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function send(
        string $email,
        string $templateName,
        array $parameters = [],
        ?TemplateDefinition $definition = null,
        bool $queue = false,
    ): SendResult {
        $email = trim($email);

        if ($email === '') {
            throw new SmsApiException('Template mail recipient email is empty.');
        }

        $normalized = $this->normalizeParameters($parameters);
        $mailConfig = $this->resolveMailConfig($definition);

        $mailable = new TemplateMail($templateName, $normalized, $mailConfig);

        if ($queue) {
            Mail::to($email)->queue($mailable);

            return new SendResult(
                success: true,
                message: 'Queued',
                data: [
                    'email' => $email,
                    'template' => $templateName,
                    'queued' => true,
                ],
                driver: 'mail',
            );
        }

        Mail::to($email)->send($mailable);

        return new SendResult(
            success: true,
            message: 'Mail sent',
            data: [
                'email' => $email,
                'template' => $templateName,
            ],
            driver: 'mail',
        );
    }

    /**
     * Build a Laravel MailMessage for notification toMail().
     *
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    public function toMailMessage(
        string $templateName,
        array $parameters = [],
        ?TemplateDefinition $definition = null,
    ): MailMessage {
        $normalized = $this->normalizeParameters($parameters);
        $mailConfig = $this->resolveMailConfig($definition);

        $subject = (string) ($mailConfig['subject'] ?? config('smsapi.otp.mail.subject', 'کد تایید شما'));
        $text = (string) ($mailConfig['text'] ?? config('smsapi.otp.mail.text', 'کد تایید شما: :CODE'));
        $body = $this->interpolate($text, $normalized);

        $message = (new MailMessage)
            ->subject($subject)
            ->line($body);

        $code = $normalized['CODE'] ?? $normalized['code'] ?? $normalized['Code'] ?? null;

        if ($code !== null && $code !== '') {
            $message->line("**{$code}**");
        }

        $fromAddress = $mailConfig['from_address'] ?? config('smsapi.otp.mail.from.address');
        $fromName = $mailConfig['from_name'] ?? config('smsapi.otp.mail.from.name');

        if (is_string($fromAddress) && $fromAddress !== '') {
            $message->from(
                $fromAddress,
                is_string($fromName) && $fromName !== '' ? $fromName : null,
            );
        }

        return $message;
    }

    /**
     * @return array{subject?: string, markdown?: string, text?: string, from_address?: string|null, from_name?: string|null}
     */
    private function resolveMailConfig(?TemplateDefinition $definition): array
    {
        $defaults = [
            'subject' => config('smsapi.otp.mail.subject'),
            'markdown' => config('smsapi.otp.mail.markdown'),
            'text' => config('smsapi.otp.mail.text'),
            'from_address' => config('smsapi.otp.mail.from.address'),
            'from_name' => config('smsapi.otp.mail.from.name'),
        ];

        $override = $definition?->mail ?? [];

        if (! is_array($override)) {
            return $defaults;
        }

        return array_merge($defaults, array_filter([
            'subject' => $override['subject'] ?? null,
            'markdown' => $override['markdown'] ?? null,
            'text' => $override['text'] ?? null,
            'from_address' => $override['from']['address'] ?? $override['from_address'] ?? null,
            'from_name' => $override['from']['name'] ?? $override['from_name'] ?? null,
        ], static fn ($v) => $v !== null && $v !== ''));
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return array<string, string>
     */
    public function normalizeParameters(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters)) {
            $normalized = [];

            foreach ($parameters as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $name = $item['name'] ?? null;

                if (! is_string($name) || $name === '') {
                    continue;
                }

                $normalized[$name] = isset($item['value']) && is_scalar($item['value'])
                    ? (string) $item['value']
                    : '';
            }

            return $normalized;
        }

        $normalized = [];

        foreach ($parameters as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $normalized[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        return $normalized;
    }

    /**
     * @param  array<string, string>  $parameters
     */
    private function interpolate(string $text, array $parameters): string
    {
        $replacements = [];

        foreach ($parameters as $key => $value) {
            $replacements[':'.$key] = $value;
            $replacements[':'.strtoupper($key)] = $value;
            $replacements[':'.strtolower($key)] = $value;
        }

        if (! isset($replacements[':CODE'])) {
            $code = $parameters['CODE'] ?? $parameters['code'] ?? $parameters['Code'] ?? '';
            $replacements[':CODE'] = $code;
            $replacements[':code'] = $code;
        }

        return strtr($text, $replacements);
    }
}
