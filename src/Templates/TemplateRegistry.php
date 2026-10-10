<?php

namespace Alizeinodin\SmsApi\Templates;

use Alizeinodin\SmsApi\Exceptions\TemplateNotFoundException;

/**
 * Resolves named templates (e.g. otp-login) to per-provider template IDs.
 */
class TemplateRegistry
{
    private const META_KEYS = ['drivers', 'queue', 'failover', 'options', 'description', 'mail'];

    /**
     * @var array<string, TemplateDefinition>
     */
    private array $templates = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        foreach ($config as $name => $definition) {
            if (! is_string($name) || $name === '' || ! is_array($definition)) {
                continue;
            }

            $this->templates[$name] = $this->parse($name, $definition);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->templates[$name]);
    }

    /**
     * @throws TemplateNotFoundException
     */
    public function get(string $name): TemplateDefinition
    {
        if (! isset($this->templates[$name])) {
            throw new TemplateNotFoundException($name);
        }

        return $this->templates[$name];
    }

    /**
     * Resolve provider template id for a driver.
     *
     * @throws TemplateNotFoundException
     */
    public function id(string $name, string $driver): int|string
    {
        return $this->get($name)->idFor($driver);
    }

    /**
     * If $templateId is a registered name, resolve it; otherwise return as-is.
     */
    public function resolve(int|string $templateId, string $driver): int|string
    {
        if (! is_string($templateId) || ! $this->has($templateId)) {
            return $templateId;
        }

        return $this->id($templateId, $driver);
    }

    /**
     * @return array<string, TemplateDefinition>
     */
    public function all(): array
    {
        return $this->templates;
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function parse(string $name, array $definition): TemplateDefinition
    {
        $drivers = [];

        if (isset($definition['drivers']) && is_array($definition['drivers'])) {
            foreach ($definition['drivers'] as $driver => $id) {
                if (is_string($driver) && (is_int($id) || is_string($id))) {
                    $drivers[$driver] = $id;
                }
            }
        } else {
            foreach ($definition as $key => $value) {
                if (! is_string($key) || in_array($key, self::META_KEYS, true)) {
                    continue;
                }

                if (is_int($value) || is_string($value)) {
                    $drivers[$key] = $value;
                }
            }
        }

        $queue = isset($definition['queue']) && is_string($definition['queue']) && $definition['queue'] !== ''
            ? $definition['queue']
            : null;

        $failover = null;
        if (isset($definition['failover']) && is_array($definition['failover'])) {
            $failover = array_values(array_filter(
                $definition['failover'],
                static fn ($d) => is_string($d) && $d !== ''
            ));
            $failover = $failover === [] ? null : $failover;
        }

        $options = isset($definition['options']) && is_array($definition['options'])
            ? $definition['options']
            : [];

        $mail = isset($definition['mail']) && is_array($definition['mail'])
            ? $definition['mail']
            : null;

        return new TemplateDefinition($name, $drivers, $queue, $failover, $options, $mail);
    }
}
