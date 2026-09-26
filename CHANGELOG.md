# Changelog

## Unreleased (v2.0.0)

### Breaking
- Package rename: `alizne/smsapi` → `alizeinodin/smsapi`
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
