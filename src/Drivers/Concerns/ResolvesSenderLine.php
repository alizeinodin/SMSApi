<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

use Alizeinodin\SmsApi\Exceptions\SmsApiException;

trait ResolvesSenderLine
{
    /**
     * @param  array<string, mixed>  $options
     */
    protected function resolveSenderLine(array $options, string $label = 'sender/line_number'): string
    {
        $sender = $options['sender']
            ?? $options['from']
            ?? $options['lineNumber']
            ?? $this->lineNumber;

        if ($sender === null || $sender === '') {
            throw new SmsApiException(
                sprintf('[%s] %s is required in config or options.', $this->getName(), $label)
            );
        }

        return (string) $sender;
    }
}
