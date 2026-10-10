<?php

namespace Alizeinodin\SmsApi\Jobs;

use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Events\SmsQueuedSendFailed;
use Alizeinodin\SmsApi\Exceptions\CircuitOpenException;
use Alizeinodin\SmsApi\Exceptions\OutsideAllowedHoursException;
use Alizeinodin\SmsApi\Exceptions\RateLimitExceededException;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Exceptions\UnsupportedDriverMethodException;
use Alizeinodin\SmsApi\Otp\OtpVault;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\SmsManager;
use Alizeinodin\SmsApi\Support\ConfigBoolean;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * Queued SMS send with dedicated-queue retry / backoff.
 *
 * - Rate limit / circuit open → release back onto the queue after delay
 * - Provider failure → throw (Laravel retries with backoff)
 * - Permanent errors → fail immediately (no more retries)
 */
class SendMessageJob implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries;

    public int $timeout;

    public int $maxExceptions;

    /**
     * @param  array<int, mixed>  $arguments
     * @param  array<int, string>|null  $failoverDrivers
     * @param  array<int, int>  $backoff
     */
    public function __construct(
        public string $operation,
        public array $arguments,
        public ?string $driver = null,
        public ?array $failoverDrivers = null,
        public ?string $channel = null,
        ?int $tries = null,
        public array $backoff = [],
        ?int $timeout = null,
    ) {
        $retry = $this->retryConfig($channel);

        $this->tries = $tries ?? (int) ($retry['tries'] ?? config('smsapi.queue.tries', 3));
        $this->timeout = $timeout ?? (int) ($retry['timeout'] ?? config('smsapi.queue.timeout', 60));
        $this->maxExceptions = $this->tries;

        $this->backoff = $backoff !== []
            ? array_values(array_map('intval', $backoff))
            : array_values(array_map(
                'intval',
                (array) ($retry['backoff'] ?? config('smsapi.queue.backoff', [10, 30, 60]))
            ));
    }

    /**
     * @throws SmsApiException
     */
    public function handle(SmsManager $manager): void
    {
        $client = $this->failoverDrivers !== null
            ? $manager->failover($this->failoverDrivers)
            : $manager->driver($this->driver);

        if (! method_exists($client, $this->operation)) {
            $this->fail(new UnsupportedDriverMethodException(
                $client->getName(),
                $this->operation,
            ));

            return;
        }

        try {
            $this->assertMessagePolicyWindow();

            /** @var SendResult $response */
            $response = $client->{$this->operation}(...$this->arguments);
        } catch (UnsupportedDriverMethodException|OutsideAllowedHoursException $e) {
            $this->fail($e);

            return;
        } catch (RateLimitExceededException $e) {
            $this->release(max(1, $e->retryAfterSeconds ?: $this->nextBackoff()));

            return;
        } catch (CircuitOpenException $e) {
            $this->release(max(1, $e->retryAfterSeconds ?: $this->nextBackoff()));

            return;
        }

        if (! $response->success) {
            throw new SmsApiException(
                $response->message !== '' ? $response->message : 'SMS provider returned unsuccessful response.',
                $response->statusCode ?? 0,
                $response->toArray(),
            );
        }
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return $this->backoff !== [] ? $this->backoff : [10, 30, 60];
    }

    public function failed(Throwable $exception): void
    {
        $this->invalidateOtpChallengeOnFailure();

        Event::dispatch(new SmsQueuedSendFailed($this, $exception));
    }

    private function invalidateOtpChallengeOnFailure(): void
    {
        $options = $this->extractOptions();
        $meta = $options['otp_vault'] ?? null;

        if (! is_array($meta)) {
            return;
        }

        $recipient = $meta['recipient'] ?? null;
        $purpose = $meta['purpose'] ?? null;

        if (! is_string($recipient) || $recipient === '' || ! is_string($purpose) || $purpose === '') {
            return;
        }

        try {
            if (! ConfigBoolean::get(config('smsapi.otp.vault.enabled', false))) {
                return;
            }

            app(OtpVault::class)->invalidate($recipient, $purpose);
        } catch (Throwable) {
            // Never mask the original queue failure.
        }
    }

    private function nextBackoff(): int
    {
        $backoff = $this->backoff();
        $attempt = max(1, $this->attempts());
        $index = min($attempt - 1, count($backoff) - 1);

        return max(1, (int) ($backoff[$index] ?? 10));
    }

    /**
     * @return array<string, mixed>
     */
    private function retryConfig(?string $channel): array
    {
        $all = (array) config('smsapi.queue.retry', []);

        if ($channel !== null && isset($all[$channel]) && is_array($all[$channel])) {
            return $all[$channel];
        }

        if (isset($all['default']) && is_array($all['default'])) {
            return $all['default'];
        }

        return [];
    }

    /**
     * Re-check send windows for delayed / queued policy sends.
     */
    private function assertMessagePolicyWindow(): void
    {
        $options = $this->extractOptions();
        $type = $options['message_type'] ?? null;

        if (! is_string($type) || $type === '') {
            return;
        }

        /** @var MessagePolicyRegistry $registry */
        $registry = app(MessagePolicyRegistry::class);
        $registry->get($type)->assertWithinAllowedWindow();
    }

    /**
     * @return array<string, mixed>
     */
    private function extractOptions(): array
    {
        if ($this->arguments === []) {
            return [];
        }

        $last = $this->arguments[array_key_last($this->arguments)];

        return is_array($last) ? $last : [];
    }
}
