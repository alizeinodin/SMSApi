# SMSApi

Laravel package for Iranian SMS panels (sms.ir, Kavenegar, Ghasedak, Farazsms, IPPanel, Magfa, Niksms, …).

```bash
composer require alizeinodin/smsapi
php artisan vendor:publish --tag=smsapi-config
```

`.env`:

```env
SMSAPI_DRIVER=smsir
SMSAPI_API_KEY=your-api-key
SMSAPI_LINE_NUMBER=30007
```

```php
use Alizeinodin\SmsApi\Facades\Sms;

Sms::send('09121234567', 'سلام');
Sms::driver('kavenegar')->send('09121234567', 'سلام');
Sms::sendTemplate('09121234567', 123456, ['Code' => '12345']);
```

## Sandbox / live panel tests

Only **sms.ir** documents an official Sandbox API key. Other panels use your test account credentials against the real API.

1. Copy `.env.sandbox.example` → `.env.sandbox` and fill credentials.
2. Unit tests (default, no network):

```bash
composer test
# or: vendor/bin/phpunit --exclude-group sandbox
```

3. Live sandbox tests:

```bash
composer test:sandbox
# or: vendor/bin/phpunit --group sandbox
```

Missing credentials → tests are **skipped**, not failed.

### sms.ir Sandbox

Create an API key with type **Sandbox** in the panel, then:

```env
SMSAPI_API_KEY=your-sandbox-key
SMSAPI_SANDBOX_MOBILE=0912xxxxxxx
```

The verify call uses template `123456` and parameter `Code` (no real SMS / no credit).

```php
use Alizeinodin\SmsApi\Registry\DriverRegistry;

$driver = DriverRegistry::make('smsir', [
    'api_key' => env('SMSAPI_API_KEY'),
]);

$driver->sendSandboxVerify('09121234567', '12345');
```

## Drivers

| Config key | Provider |
|------------|----------|
| `smsir` | sms.ir |
| `kavenegar` | Kavenegar |
| `ghasedak` | Ghasedak |
| `farazsms` | Farazsms |
| `ippanel` | IPPanel |
| `magfa` | Magfa |
| `niksms` | Niksms |
| `mediana` | Mediana |
| `limosms` | Limosms |
| `melipayamak` / `farapayamak` / `payamito` / `payamaknovin` / `bahmanpayam` / `amoot` | Payamak-panel family |
| `payamresan` / `behinpayam` / `rastinsms` | sms-webservice V3 |
| `avanak` | Avanak (voice) |

See `.env.sandbox.example` for every credential key.


## Verification without panel accounts

Unit tests compare request shapes with official SDKs (Ghasedak, IPPanel, sms.ir, Kavenegar, LimoSMS, Mediana, Telegram, …) and check that public API hosts are reachable.

```bash
vendor/bin/phpunit --exclude-group sandbox
```

For Ghasedak without registration, calling `accountInfo()` with any key returns a structured API error (e.g. invalid apikey) — that confirms the HTTP contract is correct.
