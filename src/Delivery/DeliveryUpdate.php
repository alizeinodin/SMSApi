<?php

namespace Alizeinodin\SmsApi\Delivery;

use Carbon\CarbonInterface;

final class DeliveryUpdate
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $messageId,
        public readonly string $status,
        public readonly ?int $state = null,
        public readonly ?string $mobile = null,
        public readonly ?string $driver = null,
        public readonly ?CarbonInterface $deliveredAt = null,
        public readonly array $raw = [],
    ) {}
}
