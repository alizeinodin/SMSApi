<?php

namespace Alizeinodin\SmsApi\Concerns;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use Alizeinodin\SmsApi\Drivers\NormalizingDriver;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Testing\RecordedMessage;
use Alizeinodin\SmsApi\Testing\SmsFake;

/**
 * In-memory fake + assertion surface for SmsManager / Facade.
 */
trait InteractsWithSmsFake
{
    protected ?SmsFake $fake = null;

    /**
     * Swap all SMS traffic for an in-memory fake (tests).
     */
    public function fake(): SmsFake
    {
        $this->fake = new SmsFake;
        $this->clearDriverCache();

        return $this->fake;
    }

    /**
     * Restore real drivers after fake().
     */
    public function unfake(): void
    {
        $this->fake = null;
        $this->clearDriverCache();
    }

    public function isFaking(): bool
    {
        return $this->fake !== null;
    }

    /**
     * @return list<RecordedMessage>
     */
    public function recorded(): array
    {
        if ($this->fake !== null) {
            return $this->fake->recorded();
        }

        if (! is_array($this->config()->get('smsapi.drivers.array'))) {
            return [];
        }

        $driver = parent::driver('array');

        return $driver instanceof ArrayDriver ? $driver->recorded() : [];
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     *
     * @throws SmsApiException
     */
    public function assertSent(?callable $callback = null, ?int $times = null): void
    {
        $this->requireFake()->assertSent($callback, $times);
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     *
     * @throws SmsApiException
     */
    public function assertNotSent(?callable $callback = null): void
    {
        $this->requireFake()->assertNotSent($callback);
    }

    /**
     * @throws SmsApiException
     */
    public function assertNothingSent(): void
    {
        $this->requireFake()->assertNothingSent();
    }

    public function assertSentTimes(int $times): void
    {
        $this->requireFake()->assertSentTimes($times);
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     *
     * @throws SmsApiException
     */
    public function assertSentTo(string $mobile, ?callable $callback = null): void
    {
        $this->requireFake()->assertSentTo($mobile, $callback);
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     *
     * @throws SmsApiException
     */
    public function assertTemplateSent(
        ?string $mobile = null,
        int|string|null $templateId = null,
        ?callable $callback = null,
    ): void {
        $this->requireFake()->assertTemplateSent($mobile, $templateId, $callback);
    }

    /**
     * @throws SmsApiException
     */
    protected function requireFake(): SmsFake
    {
        if ($this->fake === null) {
            throw new SmsApiException('Sms is not faked. Call Sms::fake() in your test.');
        }

        return $this->fake;
    }

    protected function wrapFake(MessageDriver $driver): MessageDriver
    {
        return new NormalizingDriver(
            $driver,
            (array) $this->config()->get('smsapi.phone', []),
        );
    }

    protected function clearDriverCache(): void
    {
        if (method_exists($this, 'forgetDrivers')) {
            $this->forgetDrivers();

            return;
        }

        $this->drivers = [];
    }
}
