<?php

namespace Alizeinodin\SmsApi;

use Alizeinodin\SmsApi\Contracts\MessageDriver;
use Alizeinodin\SmsApi\Contracts\SmsDriver;
use Alizeinodin\SmsApi\DTOs\SendResult;
use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Alizeinodin\SmsApi\Registry\DriverRegistry;
use Illuminate\Contracts\Container\Container;

class SmsManager
{
    public function __construct(
        protected Container $app,
    ) {}

    public function driver(?string $name = null): MessageDriver
    {
        $name ??= (string) $this->app->make('config')->get('smsapi.default', 'smsir');
        $config = (array) $this->app->make('config')->get("smsapi.drivers.{$name}", []);

        return DriverRegistry::make($name, $config);
    }

    /**
     * @param  string|array<int, string>  $recipients
     * @param  array<string, mixed>  $options
     */
    public function send(string|array $recipients, string $message, array $options = []): SendResult
    {
        return $this->driver()->send($recipients, $message, $options);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $options
     */
    public function sendTemplate(string $mobile, int|string $templateId, array $parameters = [], array $options = []): SendResult
    {
        $driver = $this->driver();

        if (! $driver instanceof SmsDriver) {
            throw new SmsApiException(sprintf('[%s] does not support sendTemplate().', $driver->getName()));
        }

        return $driver->sendTemplate($mobile, $templateId, $parameters, $options);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->{$method}(...$parameters);
    }
}
