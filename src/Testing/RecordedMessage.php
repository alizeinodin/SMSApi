<?php

namespace Alizeinodin\SmsApi\Testing;

/**
 * A single outbound (or wallet) SMS operation captured by ArrayDriver / SmsFake.
 */
final class RecordedMessage
{
    /**
     * @param  array<int, mixed>  $arguments
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $driver,
        public readonly array $arguments,
    ) {}

    /**
     * @return array<int, string>
     */
    public function mobiles(): array
    {
        return match ($this->operation) {
            'send' => $this->normalizeMobiles($this->arguments[0] ?? null),
            'bulk', 'sendMapped' => $this->normalizeMobiles($this->arguments[1] ?? null),
            'sendTemplate' => $this->normalizeMobiles($this->arguments[0] ?? null),
            default => [],
        };
    }

    public function mobile(): ?string
    {
        $mobiles = $this->mobiles();

        return $mobiles[0] ?? null;
    }

    public function hasMobile(string $mobile): bool
    {
        $needle = trim($mobile);

        foreach ($this->mobiles() as $candidate) {
            if ($candidate === $needle) {
                return true;
            }
        }

        return false;
    }

    public function content(): ?string
    {
        return match ($this->operation) {
            'send' => isset($this->arguments[1]) && is_string($this->arguments[1])
                ? $this->arguments[1]
                : null,
            'bulk' => isset($this->arguments[0]) && is_string($this->arguments[0])
                ? $this->arguments[0]
                : null,
            'sendMapped' => isset($this->arguments[0]) && is_array($this->arguments[0])
                ? implode(' | ', array_map('strval', $this->arguments[0]))
                : null,
            default => null,
        };
    }

    public function templateId(): int|string|null
    {
        if ($this->operation !== 'sendTemplate') {
            return null;
        }

        $id = $this->arguments[1] ?? null;

        return is_int($id) || is_string($id) ? $id : null;
    }

    /**
     * @return array<string, string>|array<int, array{name: string, value: string}>
     */
    public function parameters(): array
    {
        if ($this->operation !== 'sendTemplate') {
            return [];
        }

        $parameters = $this->arguments[2] ?? [];

        return is_array($parameters) ? $parameters : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        $index = match ($this->operation) {
            'send', 'bulk', 'sendMapped' => 2,
            'sendTemplate' => 3,
            default => null,
        };

        if ($index === null) {
            return [];
        }

        $options = $this->arguments[$index] ?? [];

        return is_array($options) ? $options : [];
    }

    public function isTemplate(): bool
    {
        return $this->operation === 'sendTemplate';
    }

    public function isSend(): bool
    {
        return in_array($this->operation, ['send', 'bulk', 'sendMapped'], true);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeMobiles(mixed $mobiles): array
    {
        if (is_string($mobiles) && trim($mobiles) !== '') {
            return [trim($mobiles)];
        }

        if (! is_array($mobiles)) {
            return [];
        }

        $normalized = [];

        foreach ($mobiles as $mobile) {
            if (is_scalar($mobile) && trim((string) $mobile) !== '') {
                $normalized[] = trim((string) $mobile);
            }
        }

        return $normalized;
    }
}
