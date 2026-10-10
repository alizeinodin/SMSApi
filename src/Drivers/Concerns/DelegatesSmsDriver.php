<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;

/**
 * Forward the MessageDriver + SmsDriver surface to an inner driver.
 * Decorators override only the methods they care about.
 */
trait DelegatesSmsDriver
{
    use RequiresSmsDriver;

    abstract protected function delegate(): MessageDriver;

    public function getName(): string
    {
        return $this->delegate()->getName();
    }

    public function getProvider(): string
    {
        return $this->delegate()->getProvider();
    }

    public function getChannel(): string
    {
        return $this->delegate()->getChannel();
    }

    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        return $this->delegate()->send($recipients, $message, $options);
    }

    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->delegate()->bulk($message, $recipients, $options);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->sendMapped($messages, $mobiles, $options);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->sendTemplate($mobile, $templateId, $parameters, $options);
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->cancelScheduled($packId);
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getMessage($messageId);
    }

    public function getPack(string $packId): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getPack($packId);
    }

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getPacks($pageNumber, $pageSize);
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getLiveSends($pageNumber, $pageSize, $mobile, $sortByNewest);
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
            $mobile,
            $fromDate,
            $toDate,
            $sortByNewest,
        );
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getLatestReceived($count);
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->smsDriver(__FUNCTION__)->getLiveReceived($pageNumber, $pageSize, $mobile, $sortByNewest);
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
            $mobile,
            $fromDate,
            $toDate,
        );
    }

    public function getCredit(): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getCredit();
    }

    public function getLines(): SendResult
    {
        return $this->smsDriver(__FUNCTION__)->getLines();
    }

    public function setLineNumber(int|string $lineNumber): static
    {
        $this->smsDriver(__FUNCTION__)->setLineNumber($lineNumber);

        return $this;
    }

    public function getLineNumber(): ?int
    {
        return $this->smsDriver(__FUNCTION__)->getLineNumber();
    }
}
