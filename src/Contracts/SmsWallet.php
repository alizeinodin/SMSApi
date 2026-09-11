<?php

namespace Alizeinodin\SmsApi\Contracts;

use Alizeinodin\SmsApi\DTOs\SendResult;

interface SmsWallet
{
    public function getName(): string;

    public function getCredit(): SendResult;

    public function getLines(): SendResult;
}
