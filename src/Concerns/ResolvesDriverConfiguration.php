<?php

namespace Alizeinodin\SmsApi\Concerns;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Resolve smsapi.connections / smsapi.drivers config for named accounts.
 */
trait ResolvesDriverConfiguration
{
    /**
     * Public config lookup for a connection or legacy driver name.
     *
     * Resolution order: smsapi.connections.{name} → smsapi.drivers.{name}.
     *
     * @return array<string, mixed>
     *
     * @throws SmsApiException
     * @throws BindingResolutionException
     */
    public function configuration(string $name): array
    {
        return $this->getDriverConfig($name);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SmsApiException
     * @throws BindingResolutionException
     */
    protected function getDriverConfig(string $name): array
    {
        $config = $this->findConnectionConfig($name);

        if ($config === null) {
            throw new SmsApiException("SMS driver [{$name}] is not configured.");
        }

        return $this->normalizeConnectionConfig($name, $config);
    }

    /**
     * Raw config from connections.* then drivers.* (no normalization).
     *
     * @return array<string, mixed>|null
     *
     * @throws BindingResolutionException
     */
    protected function findConnectionConfig(string $name): ?array
    {
        $config = $this->config()->get("smsapi.connections.{$name}");

        if (is_array($config)) {
            return $config;
        }

        $config = $this->config()->get("smsapi.drivers.{$name}");

        return is_array($config) ? $config : null;
    }

    /**
     * Ensure every resolved config has logical name + transport type.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function normalizeConnectionConfig(string $name, array $config): array
    {
        $type = isset($config['driver']) && is_string($config['driver']) && $config['driver'] !== ''
            ? $config['driver']
            : $name;

        $config['driver'] = $type;
        $config['name'] = isset($config['name']) && is_string($config['name']) && $config['name'] !== ''
            ? $config['name']
            : $name;

        return $config;
    }

    /**
     * @throws BindingResolutionException
     */
    protected function config(): ConfigRepository
    {
        /** @var ConfigRepository $config */
        $config = $this->container->make('config');

        return $config;
    }
}
