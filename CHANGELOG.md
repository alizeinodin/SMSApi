# Changelog

## v2.1.0 - 2026-10-10

### Breaking
- Packagist / Composer package rename: `alizne/smsapi` → `alizeinodin/smsapi`
- `replace` includes `alizne/smsapi` so existing installs can migrate cleanly

## v2.0.1 - 2026-10-10

### Fixed
- Interim: kept Packagist name `alizne/smsapi` until rename in v2.1.0

## v2.0.0 - 2026-10-10

### Breaking
- Composer package historically published as `alizne/smsapi` (renamed in v2.1.0)
- Namespace: `Alizne\SmsApi` → `Alizeinodin\SmsApi`
- Replaced legacy RestfulSms.com client with multi-driver architecture (sms.ir API v1 + Iranian panels + messengers)
- Config file: `config/SMSApi.php` → `config/smsapi.php`
- Removed `SMSAPI_SECRET_KEY` / token exchange flow for sms.ir

### Added
- Drivers: sms.ir, Kavenegar, Ghasedak, Farazsms/IPPanel, Magfa, Niksms, Mediana, LimoSMS, Melipayamak family, sms-webservice V3 family, Avanak
- Messengers: Telegram, WhatsApp, Bale, Eitaa, Rubika, Gap, iGap, Messenger, Viber, LINE, Discord, Slack
- `Sms` facade + `SmsManager`
- Sandbox integration tests (`@group sandbox`) and official SDK contract tests

### Fixed
- LICENSE aligned with MIT (was GPL file vs MIT in composer.json)
- WhatsApp recipient formatting uses international digits (98…)
- Ghasedak / IPPanel / Mediana / LimoSMS request contracts matched to official SDKs
- Gap `sendMessage` now sends required `type=text` (official Gap bot API)
- API error messages now read `description` / `error` / `status_message` (not only `message`)
- Documented iGap default host `bot.igap.net` as currently unresolved (experimental driver)
- Empty HTTP 200 bodies (e.g. LINE) no longer throw Invalid JSON

### Changed
- WhatsApp: real Cloud API `sendTemplate()` (name + language + body components)
- Facebook Messenger: configurable `messaging_type` / `tag` / `notification_type`
- Slack / Discord / LINE: optional richer payload fields via `$options`
