<?php

namespace Alizeinodin\SmsApi\Contracts;

/**
 * Full SMS driver surface returned for SMS transports.
 *
 * Messengers implement MessengerDriver instead — do not type-hint SmsDriver for chat bots.
 *
 * Prefer narrow interfaces in application code:
 *   MessageDriver   — any sendable channel
 *   SmsSender       — SMS outbound (send / verify / …)
 *   MessengerDriver — chat bots
 *   SmsWallet       — balance / lines
 *   SmsReports      — delivery reports
 *   SmsInbox        — inbound
 */
interface SmsDriver extends SmsInbox, SmsLineAware, SmsReports, SmsScheduler, SmsSender, SmsWallet {}
