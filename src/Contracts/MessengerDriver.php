<?php

namespace Alizeinodin\SmsApi\Contracts;

/**
 * Messenger bots / chat apps (Telegram, WhatsApp, Bale, …).
 *
 * Only the outbound MessageDriver surface is required — no wallet, inbox, templates, or line APIs.
 */
interface MessengerDriver extends MessageDriver
{
}
