<?php

namespace Alizeinodin\SmsApi\Events;

use Alizeinodin\SmsApi\Jobs\SendMessageJob;
use Throwable;

class SmsQueuedSendFailed
{
    public function __construct(
        public SendMessageJob $job,
        public Throwable $exception,
    ) {}
}
