<?php

namespace Alizeinodin\SmsApi\Events;

use Alizeinodin\SmsApi\Delivery\DeliveryUpdate;
use Alizeinodin\SmsApi\Models\SmsLog;

class SmsDeliveryUpdated
{
    public function __construct(
        public SmsLog $log,
        public DeliveryUpdate $update,
    ) {
    }
}
