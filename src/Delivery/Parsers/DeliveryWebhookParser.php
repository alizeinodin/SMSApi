<?php

namespace Alizeinodin\SmsApi\Delivery\Parsers;

use Alizeinodin\SmsApi\Delivery\DeliveryUpdate;
use Illuminate\Http\Request;

interface DeliveryWebhookParser
{
    public function supports(?string $driver, Request $request): bool;

    /**
     * @return array<int, DeliveryUpdate>
     */
    public function parse(Request $request, ?string $driver = null): array;
}
