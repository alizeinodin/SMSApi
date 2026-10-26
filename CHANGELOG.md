# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.0] - 2026-10-26

Everything on `master` since `v2.1.0`, plus the release fixes below. Tagged `v2.0.0`, `v2.0.1`, and `v2.1.0` did not include this stack.

### Added

- Multi-driver manager with named connections, failover, and a decorator stack (normalize, templates, resilience, logging, events)
- Contract split: `MessageDriver`, `MessengerDriver`, `SmsSender` / `SmsDriver`, plus wallet, reports, and inbox interfaces
- Template registry, message-type policies (`Sms::policy()`), and fluent senders (`TemplateSend`, `QueuedSend`, `PolicySend`)
- OTP delivery via SMS and/or mail, and an optional OTP vault (`Sms::challenge()`)
- Queue helpers, Laravel `sms` notification channel, and `Sms::fake()`
- Optional HTTP send API, delivery webhook, balance alerts, and `smsapi:install`
- Provider report methods added on master after 2.1.0 (`sendMapped`, inbox/outbox, lines, credit)
- `SmsIrDriver::sendSandboxVerify()` and optional live panel sandbox tests
- HTTP `connect_timeout` (default 5s) alongside the request timeout

### Changed

- Minimum Laravel is **10** (PHP **8.1+**). Laravel 9 is no longer supported.
- Default HTTP request timeout is **10s** (was 30s). Override with `SMSAPI_TIMEOUT` or a per-driver timeout.
- Public API, relative to the legacy `SMSApi` client: `verify()` → `sendTemplate()` (no alias), `likeToLike()` → `sendMapped()`, `SmsResponse` → `SendResult`, facade `SMSApi` → `Sms`, config `SMSApi` → `smsapi`
- Further renames on this line: `message()` / `messagePolicies()` → `policy()` / `policies()`, `sms()` → `smsDriver()`, `assertVerified` → `assertTemplateSent`, `PendingSms` → `QueuedSend`, `PendingMessage` → `PolicySend`, `PendingTemplate` → `TemplateSend`, `SmsMessage` → `NotificationMessage`, `LogDriver` → `LogTransport`, `LoggingDriver` → `PersistingLogDriver`, `OtpMail` / `OtpMailer` → `TemplateMail` / `TemplateMailer`
- Composer `replace` for the legacy Packagist name `alizne/smsapi`
- Circuit breaker failure/success updates run inside a cache lock
- CI runs Pint (`pint --test`) and includes PHP 8.4

### Security

- Drivers that put an API key or bot token in the URL (Kavenegar, Telegram, Bale, Rubika, iGap, and the shared HTTP client) no longer copy Guzzle's URL into exception text, and the original Guzzle exception is not chained
- `sms_logs.message` strips URLs and credential query values
- `POST /smsapi/messages` returns `The message could not be sent.` and does not echo exception text
- Delivery webhooks require a token by default. Set `SMSAPI_WEBHOOK_REQUIRE_TOKEN=false` only if you intentionally want the old open webhook
- API and webhook tokens are read from headers, not `?token=`. Set `SMSAPI_API_ALLOW_QUERY_TOKEN` or `SMSAPI_WEBHOOK_ALLOW_QUERY_TOKEN=true` to keep the old query parameter
- The send API uses `throttle:60,1` unless `SMSAPI_API_THROTTLE` is empty

### Fixed

- WhatsApp recipients: local Iranian numbers (`09…`) are sent as international digits (`98…`)
- CI installs for PHP 8.1/8.2 and Laravel 10/11, SQLite `:memory:` in tests, and PHPUnit / Testbench floors for prefer-lowest
- Broken README heading for template usage
- English `README.md`, with the Persian guide at `README.fa.md`

A read timeout is not proof the provider rejected the message. Failing over, or retrying the queue job, after a read timeout can send the same SMS twice. Keep the job timeout greater than `timeout × failover drivers`. Connection failures are safe to retry; read timeouts are not.

## [2.1.0] - 2026-10-10

### Breaking

- Packagist / Composer package rename: `alizne/smsapi` → `alizeinodin/smsapi`
- `replace` includes `alizne/smsapi` so existing installs can migrate

## [2.0.1] - 2026-10-10

### Fixed

- Interim release that kept the Packagist name `alizne/smsapi` until the rename in v2.1.0

## [2.0.0] - 2026-10-10

### Breaking

- Composer package published as `alizne/smsapi` (renamed in v2.1.0)
- Namespace: `Alizne\SmsApi` → `Alizeinodin\SmsApi`
- Replaced the legacy RestfulSms.com client with a multi-driver architecture (sms.ir API v1, Iranian panels, messengers)
- Config file: `config/SMSApi.php` → `config/smsapi.php`
- Removed the `SMSAPI_SECRET_KEY` / token exchange flow for sms.ir

### Added

- Drivers: sms.ir, Kavenegar, Ghasedak, Farazsms/IPPanel, Magfa, Niksms, Mediana, LimoSMS, Melipayamak family, sms-webservice V3 family, Avanak
- Messengers: Telegram, WhatsApp, Bale, Eitaa, Rubika, Gap, iGap, Messenger, Viber, LINE, Discord, Slack
- `Sms` facade and `SmsManager`
- Sandbox integration tests (`@group sandbox`) and official SDK contract tests

### Fixed

- LICENSE aligned with MIT
- WhatsApp recipient formatting uses international digits (`98…`)
- Ghasedak, IPPanel, Mediana, and LimoSMS request contracts matched to official SDKs
- Gap `sendMessage` sends required `type=text`
- API error messages read `description`, `error`, and `status_message`
- Empty HTTP 200 bodies (for example LINE) no longer throw invalid JSON

### Changed

- WhatsApp Cloud API `sendTemplate()` (name, language, body components)
- Facebook Messenger: configurable `messaging_type`, `tag`, and `notification_type`
- Slack, Discord, and LINE: optional richer payload fields via `$options`

[2.2.0]: https://github.com/alizeinodin/SMSApi/releases/tag/v2.2.0
[2.1.0]: https://github.com/alizeinodin/SMSApi/releases/tag/v2.1.0
[2.0.1]: https://github.com/alizeinodin/SMSApi/releases/tag/v2.0.1
[2.0.0]: https://github.com/alizeinodin/SMSApi/releases/tag/v2.0.0
