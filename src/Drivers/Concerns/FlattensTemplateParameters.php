<?php

namespace Alizeinodin\SmsApi\Drivers\Concerns;

trait FlattensTemplateParameters
{
    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     * @return list<string>
     */
    protected function flattenTemplateParameters(array $parameters): array
    {
        if ($parameters === []) {
            return [];
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            return array_map(static fn (array $item): string => (string) ($item['value'] ?? ''), $parameters);
        }

        return array_map('strval', array_values($parameters));
    }

    /**
     * @param  array<string, string>|array<int, array{name: string, value: string}>  $parameters
     */
    protected function extractTemplateCode(array $parameters, string ...$keys): string
    {
        if ($parameters === []) {
            return '';
        }

        if (array_is_list($parameters) && isset($parameters[0]['name'])) {
            foreach ($parameters as $item) {
                $name = strtolower((string) ($item['name'] ?? ''));

                if (in_array($name, $keys, true)) {
                    return (string) ($item['value'] ?? '');
                }
            }

            return (string) ($parameters[0]['value'] ?? '');
        }

        foreach ($keys as $key) {
            if (isset($parameters[$key])) {
                return (string) $parameters[$key];
            }
        }

        return (string) reset($parameters);
    }
}
