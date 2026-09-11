<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\Concerns\DelegatesSmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Support\PhoneNumber;

/**
 * Normalize Iranian mobiles on outbound (and filter) calls for SMS channels.
 */
class NormalizingDriver implements SmsDriver
{
    use DelegatesSmsDriver;

    /**
     * @param  array{enabled?: bool, format?: string, country_code?: string, strict?: bool}  $config
     */
    public function __construct(
        private MessageDriver $driver,
        private array $config = [],
    ) {}

    protected function delegate(): MessageDriver
    {
        return $this->driver;
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        if (! $this->shouldNormalizePhones()) {
            return $this->driver->send($recipients, $message, $options);
        }

        return $this->driver->send($this->phones($recipients), $message, $options);
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        if (! $this->shouldNormalizePhones()) {
            return $this->driver->bulk($message, $recipients, $options);
        }

        return $this->driver->bulk($message, $this->phones($recipients), $options);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        if (! $this->shouldNormalizePhones()) {
            return $this->smsDriver('sendMapped')->sendMapped($messages, $mobiles, $options);
        }

        return $this->smsDriver('sendMapped')->sendMapped($messages, $this->phones($mobiles), $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        if (! $this->shouldNormalizePhones()) {
            return $this->smsDriver('sendTemplate')->sendTemplate($mobile, $templateId, $parameters, $options);
        }

        return $this->smsDriver('sendTemplate')->sendTemplate($this->phone($mobile), $templateId, $parameters, $options);
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getLiveSends(
            $pageNumber,
            $pageSize,
            $this->optionalPhone($mobile),
            $sortByNewest,
        );
    }

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getArchivedSends(
            $pageNumber,
            $pageSize,
            $this->optionalPhone($mobile),
            $fromDate,
            $toDate,
            $sortByNewest,
        );
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getLiveReceived(
            $pageNumber,
            $pageSize,
            $this->optionalPhone($mobile),
            $sortByNewest,
        );
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getArchivedReceived(
            $pageNumber,
            $pageSize,
            $this->optionalPhone($mobile),
            $fromDate,
            $toDate,
        );
    }

    private function phone(string $mobile): string
    {
        return PhoneNumber::normalize($mobile, $this->config);
    }

    /**
     * @param  string|array<int, string>  $mobiles
     * @return array<int, string>
     */
    private function phones(string|array $mobiles): array
    {
        return PhoneNumber::normalizeMany($mobiles, $this->config);
    }

    private function optionalPhone(?string $mobile): ?string
    {
        if ($mobile === null || trim($mobile) === '') {
            return $mobile;
        }

        return $this->phone($mobile);
    }

    private function shouldNormalizePhones(): bool
    {
        return $this->driver->getChannel() === 'sms';
    }
}
