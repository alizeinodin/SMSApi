<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Illuminate\Support\Facades\Log;

/**
 * Local/dev driver that writes SMS operations to the Laravel log (no HTTP).
 */
class LogTransport implements SmsDriver
{
    protected ?int $lineNumber = null;

    protected ?string $channel;

    protected string $level;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(protected array $config = [])
    {
        $line = $config['line_number'] ?? null;
        $this->lineNumber = $line !== null && $line !== '' ? (int) $line : null;
        $channel = $config['channel'] ?? null;
        $this->channel = is_string($channel) && $channel !== '' ? $channel : null;
        $this->level = is_string($config['level'] ?? null) && $config['level'] !== ''
            ? (string) $config['level']
            : 'debug';
    }

    public function getName(): string
    {
        $name = $this->config['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : 'log';
    }

    public function getProvider(): string
    {
        $type = $this->config['driver'] ?? null;

        return is_string($type) && $type !== '' ? $type : 'log';
    }

    public function getChannel(): string
    {
        $channel = $this->config['channel'] ?? null;

        return is_string($channel) && $channel !== '' ? $channel : 'sms';
    }

    public function send(string|array $mobiles, string $message, array $options = []): SendResult
    {
        return $this->write(__FUNCTION__, [
            'mobiles' => $mobiles,
            'message' => $message,
            'options' => $options,
        ]);
    }

    public function bulk(string $message, array $mobiles, array $options = []): SendResult
    {
        return $this->write(__FUNCTION__, [
            'message' => $message,
            'mobiles' => $mobiles,
            'options' => $options,
        ]);
    }

    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->write(__FUNCTION__, [
            'messages' => $messages,
            'mobiles' => $mobiles,
            'options' => $options,
        ]);
    }

    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->write(__FUNCTION__, [
            'mobile' => $mobile,
            'templateId' => $templateId,
            'parameters' => $parameters,
            'options' => $options,
        ]);
    }

    public function cancelScheduled(string $packId): SendResult
    {
        return $this->write(__FUNCTION__, ['packId' => $packId]);
    }

    public function getMessage(int $messageId): SendResult
    {
        return $this->write(__FUNCTION__, ['messageId' => $messageId], [
            'messageId' => $messageId,
            'status' => 'Delivered',
        ]);
    }

    public function getPack(string $packId): SendResult
    {
        return $this->write(__FUNCTION__, ['packId' => $packId], ['packId' => $packId]);
    }

    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->write(__FUNCTION__, compact('pageNumber', 'pageSize'), []);
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->write(__FUNCTION__, compact('pageNumber', 'pageSize', 'mobile', 'sortByNewest'), []);
    }

    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->write(
            __FUNCTION__,
            compact('pageNumber', 'pageSize', 'mobile', 'fromDate', 'toDate', 'sortByNewest'),
            [],
        );
    }

    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->write(__FUNCTION__, compact('count'), []);
    }

    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->write(__FUNCTION__, compact('pageNumber', 'pageSize', 'mobile', 'sortByNewest'), []);
    }

    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->write(
            __FUNCTION__,
            compact('pageNumber', 'pageSize', 'mobile', 'fromDate', 'toDate'),
            [],
        );
    }

    public function getCredit(): SendResult
    {
        return $this->write(__FUNCTION__, [], [
            'credit' => (float) ($this->config['credit'] ?? 999999),
        ]);
    }

    public function getLines(): SendResult
    {
        return $this->write(__FUNCTION__, [], [
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
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $data
     */
    private function write(string $operation, array $context, ?array $data = null): SendResult
    {
        $payload = array_merge(['operation' => $operation, 'driver' => 'log'], $context);

        if ($this->channel !== null) {
            Log::channel($this->channel)->log($this->level, '[smsapi:log] '.$operation, $payload);
        } else {
            Log::log($this->level, '[smsapi:log] '.$operation, $payload);
        }

        return new SendResult(
            success: true,
            message: 'Logged by log driver.',
            data: $data ?? ['operation' => $operation],
            driver: 'log',
            raw: $payload,
            statusCode: 200,
        );
    }
}
