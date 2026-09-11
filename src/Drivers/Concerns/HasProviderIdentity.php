<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

/**
 * Named connection + transport type helpers for drivers.
 */
trait HasProviderIdentity
{
    /**
     * @param  array<string, mixed>  $config
     */
    abstract protected function driverConfig(): array;

    public function getName(): string
    {
        $config = $this->driverConfig();
        $name = $config['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : $this->defaultProviderName();
    }

    public function getProvider(): string
    {
        $type = $this->driverConfig()['driver'] ?? null;

        return is_string($type) && $type !== '' ? $type : $this->defaultProviderName();
    }

    abstract protected function defaultProviderName(): string;
}
