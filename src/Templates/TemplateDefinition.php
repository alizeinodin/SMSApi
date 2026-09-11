<?php

namespace Alizeinodin\SmsApi\Templates;

use Alizeinodin\SmsApi\Exceptions\TemplateNotFoundException;

/**
 * Named template with per-driver provider IDs and optional defaults.
 */
final class TemplateDefinition
{
    /**
     * @param  array<string, int|string>  $drivers  driver name => provider template id
     * @param  array<int, string>|null  $failover
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>|null  $mail  subject / markdown / text / from overrides
     */
    public function __construct(
        public readonly string $name,
        public readonly array $drivers,
        public readonly ?string $queue = null,
        public readonly ?array $failover = null,
        public readonly array $options = [],
        public readonly ?array $mail = null,
    ) {
    }

    /**
     * @throws TemplateNotFoundException
     */
    public function idFor(string $driver): int|string
    {
        if (! array_key_exists($driver, $this->drivers)) {
            throw new TemplateNotFoundException($this->name, $driver);
        }

        return $this->drivers[$driver];
    }

    public function hasDriver(string $driver): bool
    {
        return array_key_exists($driver, $this->drivers);
    }
}
