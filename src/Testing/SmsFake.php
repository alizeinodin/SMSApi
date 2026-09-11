<?php

namespace Alizeinodin\SmsApi\Testing;

use Alizeinodin\SmsApi\Drivers\ArrayDriver;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Fake SMS driver for tests (Mail::fake() style).
 *
 * @example Sms::fake();
 * @example Sms::assertSent(fn (RecordedMessage $sms) => $sms->hasMobile('0912...'));
 */
class SmsFake extends ArrayDriver
{
    private ?SmsFake $root = null;

    public function __construct(?SmsFake $root = null, string $asDriver = 'fake')
    {
        parent::__construct(['name' => $asDriver]);
        $this->root = $root;
        $this->driverName = $asDriver;
    }

    /**
     * Return a proxy that records under a specific logical driver name.
     */
    public function actingAsDriver(string $driver): self
    {
        return new self($this->bag(), $driver);
    }

    public function getName(): string
    {
        return $this->driverName;
    }

    /**
     * @return list<RecordedMessage>
     */
    public function recorded(): array
    {
        return $this->bag()->messages;
    }

    public function flush(): void
    {
        $this->bag()->messages = [];
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     */
    public function assertSent(?callable $callback = null, ?int $times = null): void
    {
        $callback ??= static fn (RecordedMessage $sms): bool => $sms->isSend() || $sms->isTemplate();

        $matches = $this->matching($callback);

        if ($times === null) {
            PHPUnit::assertNotEmpty(
                $matches,
                'An expected SMS was not sent.',
            );

            return;
        }

        PHPUnit::assertCount(
            $times,
            $matches,
            "Expected SMS to be sent {$times} time(s), but was sent " . count($matches) . ' time(s).',
        );
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     */
    public function assertNotSent(?callable $callback = null): void
    {
        $callback ??= static fn (RecordedMessage $sms): bool => $sms->isSend() || $sms->isTemplate();

        PHPUnit::assertEmpty(
            $this->matching($callback),
            'Unexpected SMS was sent.',
        );
    }

    public function assertNothingSent(): void
    {
        $outbound = array_values(array_filter(
            $this->recorded(),
            static fn (RecordedMessage $sms): bool => $sms->isSend() || $sms->isTemplate(),
        ));

        PHPUnit::assertEmpty($outbound, 'SMS were sent unexpectedly.');
    }

    public function assertSentTimes(int $times): void
    {
        $this->assertSent(
            static fn (RecordedMessage $sms): bool => $sms->isSend() || $sms->isTemplate(),
            $times,
        );
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     */
    public function assertSentTo(string $mobile, ?callable $callback = null): void
    {
        $this->assertSent(function (RecordedMessage $sms) use ($mobile, $callback): bool {
            if (! $sms->hasMobile($mobile)) {
                return false;
            }

            return $callback === null ? true : (bool) $callback($sms);
        });
    }

    /**
     * @param  callable(RecordedMessage): bool|null  $callback
     */
    public function assertTemplateSent(
        ?string $mobile = null,
        int|string|null $templateId = null,
        ?callable $callback = null,
    ): void {
        $this->assertSent(function (RecordedMessage $sms) use ($mobile, $templateId, $callback): bool {
            if (! $sms->isTemplate()) {
                return false;
            }

            if ($mobile !== null && ! $sms->hasMobile($mobile)) {
                return false;
            }

            if ($templateId !== null && $sms->templateId() != $templateId) {
                return false;
            }

            return $callback === null ? true : (bool) $callback($sms);
        });
    }

    /**
     * @param  callable(RecordedMessage): bool  $callback
     * @return list<RecordedMessage>
     */
    public function sent(callable $callback): array
    {
        return $this->matching($callback);
    }

    protected function store(RecordedMessage $message): void
    {
        $this->bag()->messages[] = $message;
    }

    /**
     * @return list<RecordedMessage>
     */
    protected function allMessages(): array
    {
        return $this->bag()->messages;
    }

    private function bag(): self
    {
        return $this->root ?? $this;
    }

    /**
     * @param  callable(RecordedMessage): bool  $callback
     * @return list<RecordedMessage>
     */
    private function matching(callable $callback): array
    {
        return array_values(array_filter(
            $this->recorded(),
            static fn (RecordedMessage $sms): bool => (bool) $callback($sms),
        ));
    }
}
