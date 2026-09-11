<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

interface SmsScheduler
{
    public function getName(): string;

    public function cancelScheduled(string $packId): SendResult;
}
