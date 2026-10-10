<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

/**
 * Driver transport channel: sms, telegram, whatsapp, bale, …
 */
trait HasMessageChannel
{
    public function getChannel(): string
    {
        $channel = $this->driverConfig()['channel'] ?? null;

        if (is_string($channel) && $channel !== '') {
            return $channel;
        }

        return $this->defaultChannel();
    }

    protected function defaultChannel(): string
    {
        return 'sms';
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function driverConfig(): array;
}
