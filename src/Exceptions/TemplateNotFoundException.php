<?php

namespace Alizeinodin\SmsApi\Exceptions;

class TemplateNotFoundException extends SmsApiException
{
    public function __construct(string $template, ?string $driver = null)
    {
        $message = $driver === null
            ? "SMS template [{$template}] is not registered."
            : "SMS template [{$template}] has no mapping for driver [{$driver}].";

        parent::__construct($message);
    }
}
