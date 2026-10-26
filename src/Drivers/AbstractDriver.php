<?php

namespace Alizeinodin\SmsApi\Drivers;

use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\InvalidPhoneNumberException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Support\PhoneNumber;
use GuzzleHttp\Client;

/**
 * Easy base for SMS providers.
 *
 * Extend this class and override only what you support (usually send + sendTemplate).
 * Everything else throws UnsupportedDriverMethodException automatically.
 *
 * @example
 * class KavenegarDriver extends AbstractDriver {
 *     public function getName(): string { return $this->config['name'] ?? 'kavenegar'; }
 *     public function send(...) { ... }
 *     public function sendTemplate(...) { ... }
 * }
 */
abstract class AbstractDriver extends AbstractHttpDriver implements SmsDriver
{
    protected ?int $lineNumber = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [], ?Client $client = null)
    {
        $lineNumber = $config['line_number'] ?? null;
        $this->lineNumber = $lineNumber !== null && $lineNumber !== ''
            ? (int) $lineNumber
            : null;

        parent::__construct($config, $client);
    }

    public function getChannel(): string
    {
        $channel = $this->config['channel'] ?? null;

        return is_string($channel) && $channel !== '' ? $channel : 'sms';
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function bulk(string $message, array $recipients, array $options = []): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function sendMapped(array $messages, array $mobiles, array $options = []): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function cancelScheduled(string $packId): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getMessage(int $messageId): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getPack(string $packId): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getPacks(int $pageNumber = 1, int $pageSize = 100): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    public function getLiveSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getArchivedSends(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getLatestReceived(int $count = 100): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getLiveReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        bool $sortByNewest = false,
    ): SendResult {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getArchivedReceived(
        int $pageNumber = 1,
        int $pageSize = 100,
        ?string $mobile = null,
        ?int $fromDate = null,
        ?int $toDate = null,
    ): SendResult {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getCredit(): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    public function getLines(): SendResult
    {
        return $this->unsupported(__FUNCTION__);
    }

    /**
     * Mutates this instance's default line number.
     *
     * Prefer named connections (separate api_key / line_number in config) over
     * setLineNumber on a cached driver — Manager caches one instance per name,
     * so mutating the line affects later callers of the same connection.
     */
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
     * @param  string|array<int, string>  $mobiles
     * @return array<int, string>
     *
     * @throws InvalidPhoneNumberException
     */
    protected function normalizeMobiles(string|array $mobiles): array
    {
        return PhoneNumber::normalizeMany($mobiles, $this->phoneConfig());
    }

    /**
     * @return array{enabled?: bool, format?: string, country_code?: string, strict?: bool}
     */
    protected function phoneConfig(): array
    {
        if (isset($this->config['phone']) && is_array($this->config['phone'])) {
            return $this->config['phone'];
        }

        if (function_exists('config')) {
            try {
                return (array) config('smsapi.phone', PhoneNumber::defaultConfig());
            } catch (\Throwable) {
                // Outside Laravel container (e.g. isolated unit tests).
            }
        }

        return PhoneNumber::defaultConfig();
    }

    /**
     * @throws UnsupportedDriverMethodException
     */
    protected function unsupported(string $method): never
    {
        throw new UnsupportedDriverMethodException($this->getName(), $method);
    }
}
