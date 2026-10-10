# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `SmsIrDriver::sendSandboxVerify()` helper for the official sms.ir Sandbox verify template
- Optional live panel sandbox tests (`composer test:sandbox`, `.env.sandbox.example`)
- Composer `replace` for legacy Packagist name `alizne/smsapi`

### Fixed

- WhatsApp Cloud API recipients: local Iranian numbers (`09…`) are sent as international digits (`98…`)
- CI matrix: allow `laravel/pint` on PHP 8.1/8.2 and do not block installs of older Laravel majors on Packagist advisories
- PHPUnit 12 readiness: replace `@dataProvider` doc-comments with attributes
- Tests force SQLite `:memory:` so Laravel 10 CI cells do not attempt MySQL

## [2.0.0] - 2026-09-12

Major rewrite (`alizeinodin/smsapi`, namespace `Alizeinodin\SmsApi`).

Legacy tags `v1.0.0` / `v1.0.1` belong to the previous package lineage and are **not** API-compatible with this release.

### Added

- Multi-driver manager with named connections, failover, and decorator stack (normalize, templates, resilience, logging, events)
- Contract split: `MessageDriver`, `MessengerDriver`, `SmsSender` / `SmsDriver`, plus wallet / reports / inbox interfaces
- Iranian SMS drivers: sms.ir, Kavenegar, Ghasedak, Melipayamak, Farazsms, Magfa, IPPanel, Niksms, Mediana, Limosms, Avanak, Amoot, Farapayamak, Payamito, Payamaknovin, Bahmanpayam, Payamresan, Behinpayam, Rastinsms
- Messenger drivers: Telegram, WhatsApp, Bale, Eitaa, Rubika, Gap, iGap, Facebook Messenger, Viber, LINE, Discord, Slack
- Template registry and message-type policies (`Sms::policy()`)
- OTP delivery via SMS and/or mail (`Sms::template()` / `Sms::otp()`)
- Optional OTP vault: `Sms::challenge()` (HMAC store, TTL, attempts, throttle)
- Queue helpers (`Sms::queue()`, retries / backoff)
- Laravel `sms` notification channel and `Sms::fake()` testing helpers
- Optional HTTP send API, delivery webhook, balance alerts
- Modular install: `php artisan smsapi:install {logging|delivery|api|messaging|balance|resilience|otp|all}`
- GitHub Actions CI (PHP 8.1–8.3 × Laravel 10–12) and tag-based releases
- English documentation on the [GitHub Wiki](https://github.com/alizeinodin/SMSApi/wiki)

### Changed

- Public API redesigned around `Sms` facade + `SmsManager`
- Fluent send builders live under `Alizeinodin\SmsApi\Pending\` (`TemplateSend`, `QueuedSend`, `PolicySend`)
- `SmsManager` responsibilities split into concerns (fluent senders, fake/assertions, decoration, config)
- Supported Laravel versions: **10 / 11 / 12** (PHP **8.1+**)
- License clarified as **MIT**

### Breaking

- Facade `SMSApi` → `Sms`
- Config key / file `SMSApi` → `smsapi` (`config/smsapi.php`)
- `verify()` → `sendTemplate()` (no alias)
- `likeToLike()` → `sendMapped()`
- `message($type)` / `messagePolicies()` → `policy($type)` / `policies()`
- Manager `sms()` → `smsDriver()`
- `assertVerified` → `assertTemplateSent`
- Class renames: `SmsResponse` → `SendResult`, `PendingSms` → `QueuedSend`, `PendingMessage` → `PolicySend`, `PendingTemplate` → `TemplateSend`, `SmsMessage` → `NotificationMessage`, `LogDriver` → `LogTransport`, `LoggingDriver` → `PersistingLogDriver`, Facebook `MessengerDriver` → `FacebookMessengerDriver`, `OtpMail` / `OtpMailer` → `TemplateMail` / `TemplateMailer`
- Mail view: `smsapi::mail.template`
- `otp()` remains only as a thin alias of `template()` (delivery, not the vault)

### Fixed

- Logging off by default when `smsapi.logging.enabled` is missing
- Queue defaults read from `smsapi.queue.default`
- `SmsRateLimiter` injects `Illuminate\Cache\RateLimiter` (no static facade)
- `getProvider()` available on sender contracts
- Event listener failures are reported without breaking outbound sends
- OTP vault: atomic consume, shared recipient resolution, sync-by-default (queue opt-in), redact of sensitive params in logs

[2.0.0]: https://github.com/alizeinodin/SMSApi/releases/tag/v2.0.0
