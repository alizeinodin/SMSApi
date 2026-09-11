<?php

namespace Alizeinodin\SmsApi;

use Alizeinodin\SmsApi\Concerns\CreatesFluentSenders;
use Alizeinodin\SmsApi\Concerns\DecoratesDrivers;
use Alizeinodin\SmsApi\Concerns\InteractsWithSmsFake;
use Alizeinodin\SmsApi\Concerns\ResolvesDriverConfiguration;
use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\Drivers\FailoverDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Otp\OtpIssue;
use Alizeinodin\SmsApi\Otp\OtpVault;
use Alizeinodin\SmsApi\Policies\MessagePolicyRegistry;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Alizeinodin\SmsApi\Resilience\CircuitBreaker;
use Alizeinodin\SmsApi\Templates\TemplateRegistry;
use Illuminate\Support\Manager;

/**
 * Factory / Manager for SMS drivers (Strategy + Factory Method).
 *
 * High-level fluent APIs, fake/assertions, config resolution, and the decorator
 * stack live in dedicated concerns — this class owns driver creation & failover.
 *
 * @method SendResult send(string|array $mobiles, string $message, array $options = [])
 * @method SendResult bulk(string $message, array $mobiles, array $options = [])
 * @method SendResult sendMapped(array $messages, array $mobiles, array $options = [])
 * @method SendResult sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = [])
 * @method \Alizeinodin\SmsApi\Pending\TemplateSend template(string $name)
 * @method \Alizeinodin\SmsApi\Pending\TemplateSend otp(string $name)
 * @method OtpIssue challenge(string $purpose)
 * @method OtpVault otpVault()
 * @method array otpChannels()
 * @method TemplateRegistry templates()
 * @method \Alizeinodin\SmsApi\Pending\PolicySend policy(string $type)
 * @method MessagePolicyRegistry policies()
 * @method MessageDriver connection(string|null $name = null)
 * @method SmsDriver smsDriver(string|null $name = null)
 * @method SendResult cancelScheduled(string $packId)
 * @method SendResult getMessage(int $messageId)
 * @method SendResult getPack(string $packId)
 * @method SendResult getPacks(int $pageNumber = 1, int $pageSize = 100)
 * @method SendResult getLiveSends(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, bool $sortByNewest = false)
 * @method SendResult getArchivedSends(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, ?int $fromDate = null, ?int $toDate = null, bool $sortByNewest = false)
 * @method SendResult getLatestReceived(int $count = 100)
 * @method SendResult getLiveReceived(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, bool $sortByNewest = false)
 * @method SendResult getArchivedReceived(int $pageNumber = 1, int $pageSize = 100, ?string $mobile = null, ?int $fromDate = null, ?int $toDate = null)
 * @method SendResult getCredit()
 * @method SendResult getLines()
 * @method SmsDriver setLineNumber(int|string $lineNumber)
 * @method int|null getLineNumber()
 *
 * @extends Manager<MessageDriver>
 */
class SmsManager extends Manager
{
    use CreatesFluentSenders;
    use DecoratesDrivers;
    use InteractsWithSmsFake;
    use ResolvesDriverConfiguration;

    public function getDefaultDriver(): string
    {
        return (string) $this->config()->get('smsapi.default', 'smsir');
    }

    public function driver($driver = null): MessageDriver
    {
        if ($this->fake !== null) {
            return $this->observe($this->wrapFake($this->fake->actingAsDriver($driver ?? $this->getDefaultDriver())));
        }

        /** @var MessageDriver $instance */
        $instance = parent::driver($driver);

        return $this->wrap($instance);
    }

    /**
     * Alias for driver() — named connection / account (like DB::connection()).
     *
     * @example Sms::connection('smsir-otp')->sendTemplate(...);
     */
    public function connection(?string $name = null): MessageDriver
    {
        return $this->driver($name);
    }

    /**
     * Resolve a driver and assert it supports the full SMS surface.
     *
     * @throws SmsApiException
     */
    public function smsDriver(?string $name = null): SmsDriver
    {
        $driver = $this->driver($name);

        if ($driver->getChannel() !== 'sms' || ! $driver instanceof SmsDriver) {
            throw new SmsApiException(sprintf(
                'Driver [%s] is a messenger channel (%s), not an SMS driver. Use send() or type-hint MessengerDriver.',
                $driver->getName(),
                $driver->getChannel(),
            ));
        }

        return $driver;
    }

    /**
     * Build a failover chain at call time.
     *
     * @param  array<int, string>|null  $drivers  Ordered provider names; null = config default list
     */
    public function failover(?array $drivers = null): MessageDriver
    {
        if ($this->fake !== null) {
            return $this->observe($this->wrapFake($this->fake->actingAsDriver('failover')));
        }

        if ($drivers === null) {
            return $this->driver('failover');
        }

        return $this->wrap($this->makeFailoverDriver($drivers), wrapResilience: false);
    }

    /**
     * Create a driver instance by connection / account name.
     *
     * Supports named accounts that share a transport type, e.g. connection
     * "smsir-otp" with config driver=smsir and its own api_key / line_number.
     *
     * @param  string  $driver
     */
    protected function createDriver($driver)
    {
        $name = (string) $driver;

        if (isset($this->customCreators[$name])) {
            return $this->invokeCustomCreator($name, $name);
        }

        $config = $this->findConnectionConfig($name);

        if ($config === null) {
            throw new SmsApiException("SMS driver [{$name}] is not configured.");
        }

        $config = $this->normalizeConnectionConfig($name, $config);
        $type = (string) $config['driver'];

        if (isset($this->customCreators[$type])) {
            return $this->invokeCustomCreator($type, $name);
        }

        if ($type === 'failover') {
            return $this->createFailoverDriver($config);
        }

        if (! DriverRegistry::has($type)) {
            throw new SmsApiException(
                "Driver [{$name}] uses unsupported type [{$type}]."
            );
        }

        return DriverRegistry::make($type, $config);
    }

    /**
     * Register a custom transport driver class.
     *
     * @param  class-string<MessageDriver>  $driverClass
     */
    public function registerDriver(string $type, string $driverClass): void
    {
        DriverRegistry::register($type, $driverClass);
    }

    /**
     * @return mixed
     */
    protected function invokeCustomCreator(string $type, string $connectionName)
    {
        $creator = $this->customCreators[$type];

        // Prefer Laravel Manager signature ($app, $name). One-arg closures stay BC.
        if ($creator instanceof \Closure) {
            $params = (new \ReflectionFunction($creator))->getNumberOfParameters();

            if ($params <= 1) {
                return $creator($this->container);
            }
        }

        return $creator($this->container, $connectionName);
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    protected function createFailoverDriver(?array $config = null): MessageDriver
    {
        $config ??= $this->getDriverConfig('failover');
        $drivers = $config['drivers'] ?? [];

        if (is_string($drivers)) {
            $drivers = array_map('trim', explode(',', $drivers));
        }

        return $this->makeFailoverDriver(array_values((array) $drivers), $config);
    }

    /**
     * @param  array<int, string>  $drivers
     * @param  array<string, mixed>  $config
     */
    protected function makeFailoverDriver(array $drivers, array $config = []): MessageDriver
    {
        return new FailoverDriver(
            array_values($drivers),
            function (string $name): MessageDriver {
                if ($name === 'failover') {
                    throw new SmsApiException('Failover driver cannot nest itself.');
                }

                // Raw + resilience (no logging / observing) so failover emits events once as a whole.
                /** @var MessageDriver $child */
                $child = parent::driver($name);

                return $this->wrap($child, wrapLogging: false, wrapObserving: false);
            },
            $config,
            $this->container->make(CircuitBreaker::class),
        );
    }
}
