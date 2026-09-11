<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Testing\RecordedMessage;

/**
 * In-memory SMS driver for local / array transport (no HTTP).
 */
class ArrayDriver implements SmsDriver
{
    /**
     * @var list<RecordedMessage>
     */
    protected array $messages = [];

    protected string $driverName = 'array';

    protected ?int $lineNumber = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config = [])
    {
        $line = $config['line_number'] ?? null;
        $this->lineNumber = $line !== null && $line !== '' ? (int) $line : null;

        if (isset($config['name']) && is_string($config['name']) && $config['name'] !== '') {
            $this->driverName = $config['name'];
        }
    }

    public function getName(): string
    {
        return $this->driverName;
    }

    public function getProvider(): string
    {
        $type = $this->config['driver'] ?? null;

        return is_string($type) && $type !== '' ? $type : $this->driverName;
    }

    public function getChannel(): string
    {
        $channel = $this->config['channel'] ?? null;

        return is_string($channel) && $channel !== '' ? $channel : 'sms';
    }

    /**
     * @return list<RecordedMessage>
     */
    public function recorded(): array
    {
        return $this->messages;
    }

    public function flush(): void
    {
        $this->messages = [];
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args());
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args(), [
            'messageId' => $messageId,
            'status' => 'Delivered',
        ]);
    }

    public function getPack(string $packId): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args(), [
            'packId' => $packId,
        ]);
    }

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args(), []);
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->record(__FUNCTION__, func_get_args(), []);
    }

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->record(__FUNCTION__, func_get_args(), []);
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        $samples = [
            [
                'mobile' => '09121234567',
                'message' => 'سلام، وضعیت سفارش من چطور است؟',
                'received_at' => now()->subMinutes(12)->toIso8601String(),
            ],
            [
                'mobile' => '09351234567',
                'message' => 'کد تایید: ۴۸۲۹',
                'received_at' => now()->subHour()->toIso8601String(),
            ],
            [
                'mobile' => '09191234567',
                'message' => 'لطفاً با پشتیبانی تماس بگیرید.',
                'received_at' => now()->subHours(3)->toIso8601String(),
            ],
        ];

        return $this->record(__FUNCTION__, func_get_args(), array_slice($samples, 0, min($count, count($samples))));
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->record(__FUNCTION__, func_get_args(), []);
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->record(__FUNCTION__, func_get_args(), []);
    }

    public function getCredit(): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args(), [
            'credit' => (float) ($this->config['credit'] ?? 999999),
        ]);
    }

    public function getLines(): SendResult
    {
        return $this->record(__FUNCTION__, func_get_args(), [
            'lines' => (array) ($this->config['lines'] ?? []),
        ]);
    }

    public function setLineNumber(int|string $lineNumber): static
    {
        $this->lineNumber = (int) $lineNumber;

        return $this;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    /**
     * @param  array<int, mixed>  $arguments
     * @param  array<string, mixed>|null  $data
     */
    protected function record(string $operation, array $arguments, ?array $data = null): SendResult
    {
        $this->store(new RecordedMessage($operation, $this->getName(), $arguments));

        return new SendResult(
            success: true,
            message: 'Recorded by array/fake driver.',
            data: $data ?? [
                'messageId' => 'fake-' . (count($this->allMessages())),
                'operation' => $operation,
            ],
            driver: $this->getName(),
            raw: ['fake' => true],
            statusCode: 200,
        );
    }

    protected function store(RecordedMessage $message): void
    {
        $this->messages[] = $message;
    }

    /**
     * @return list<RecordedMessage>
     */
    protected function allMessages(): array
    {
        return $this->messages;
    }
}
