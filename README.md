# SMSApi

[![Tests](https://github.com/alizeinodin/SMSApi/actions/workflows/tests.yml/badge.svg)](https://github.com/alizeinodin/SMSApi/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/alizeinodin/smsapi.svg?style=flat-square)](https://packagist.org/packages/alizeinodin/smsapi)
[![Total Downloads](https://img.shields.io/packagist/dt/alizeinodin/smsapi.svg?style=flat-square)](https://packagist.org/packages/alizeinodin/smsapi)
[![License](https://img.shields.io/packagist/l/alizeinodin/smsapi.svg?style=flat-square)](LICENSE)

‫پکیج لاراول ارسال پیامک برای پنل‌های پیامکی ایرانی. با این پکیج می‌توانید از **sms.ir**، **کاوه‌نگار**، **قاصدک**، **ملی‌پیامک**، **فراز اس‌ام‌اس**، **مگفا**، **آی‌پی‌پنل**، **نیک اس‌ام‌اس**، **مدیانا**، **آوانک**، **لیمو اس‌ام‌اس**، **آموت**، **فراپیامک**، **پیامیتو**، **پیامک‌نوین**، **بهمن‌پیام**، **پیام‌رسان**، **بهین‌پیام**، **راستین اس‌ام‌اس** و سایر پنل‌های پیامکی ایرانی، با یک API یکسان پیامک ارسال کنید.‬
‫‬

‫علاوه بر پیامک، پیام‌رسان‌هایی مثل **تلگرام**، **واتساپ**، **بله**، **ایتا**، **روبیکا**، **گپ** و **آی‌گپ** هم پشتیبانی می‌شوند. 

‫‬
‫با استفاده از این پکیج می‌توانید توسعه اپلیکیشن خودتان را با امکاناتی مثل ارسال otp به صورت اتوماتیک، یا استفاده از چندین پنل‌پیامکی با قابلیت failover  و... تسریع کنید.‬

```bash
composer require alizeinodin/smsapi
```
---

## نصب

```bash
composer require alizeinodin/smsapi
php artisan smsapi:install
```

`.env`:

```env
SMSAPI_DRIVER=smsir
SMSAPI_API_KEY=your-api-key
SMSAPI_LINE_NUMBER=30007
```

برای توسعهٔ لوکال بدون پنل واقعی:

```env
SMSAPI_DRIVER=array
```

‫قابلیت‌های اختیاری (Logger، OTP Vault، Rate Limiter، Webhook) پیش‌فرض خاموش‌اند:‬
‫‬
```bash
php artisan smsapi:install logging
php artisan smsapi:install otp
php artisan smsapi:install resilience
php artisan smsapi:install all
```

---

## استفاده

```php
use Alizeinodin\SmsApi\Facades\Sms;

Sms::send('09121234567', 'سفارش شما ثبت شد.');
```

### چند ارائه‌دهنده (Failover)

```php
Sms::failover(['smsir', 'kavenegar'])->send('09121234567', 'سلام');
```

‫یا در `.env`:‬
‫‬
```env
SMSAPI_DRIVER=failover
SMSAPI_FAILOVER_DRIVERS=smsir,kavenegar
```

### استفاده از 

‫ابتدا در `config/smsapi.php` قالب را تعریف کنید، بعد با نام منطقی ارسال کنید:‬
‫‬
```php
Sms::template('otp-login')->to($user)->send(['CODE' => '12345']);

// ایمیل به‌جای پیامک (یا هر دو)
Sms::otp('otp-login')->to($user)->mail()->send(['CODE' => '12345']);
Sms::otp('otp-login')->to($user)->both()->send(['CODE' => '12345']);
```

> ‫`Sms::otp()` فقط **ارسال** می‌کند. کد OTP را خودتان باید بسازید.‬

### OTP Vault — ساخت و بررسی کد

‫به جای اینکه منطق OTP را خودتان پیاده‌سازی کنید، می‌توانید از دستور زیر استفاده کنید، با این کار اپلیکیشن خودش کد را می‌سازد، به صورت امن نگهداری می‌کند و چک می‌کند:‬
‫‬
```bash
php artisan smsapi:install otp
```

```php
Sms::challenge('login')->template('otp-login')->to($user)->send();

Sms::challenge('login')->check($user, $request->input('code'));
```

‫کد به‌صورت HMAC ذخیره می‌شود (نه plaintext)، با انقضا و سقف تعداد تلاش.‬
‫‬
### صف

```php
Sms::queue('otp')->sendTemplate('09121234567', 'otp-login', ['CODE' => '12345']);

Sms::template('otp-login')->to($user)->queue('otp')->send(['CODE' => '12345']);
Sms::template('otp-login')->to($user)->sync()->send(['CODE' => '12345']);
```

### نوتیفیکیشن لاراول

```php
use Alizeinodin\SmsApi\Notifications\NotificationMessage;

public function via($notifiable): array
{
    return ['sms'];
}

public function toSms($notifiable): NotificationMessage
{
    return NotificationMessage::create()
        ->template('otp-login', ['CODE' => $this->code]);
}
```

### تست

```php
Sms::fake();

Sms::send('09121234567', 'تست');

Sms::assertSentTo('09121234567');
Sms::assertNothingSent();
```

---

## درایورهای پشتیبانی‌شده

‫کلید ستون «config» همان مقداری است که در `SMSAPI_DRIVER` یا `Sms::driver('…')` می‌گذارید.‬
‫‬
### پیامک (ایران)

| کانفیگ | ارائه‌دهنده |
|--------|-------------|
| `smsir` | sms.ir |
| `kavenegar` | کاوه‌نگار (Kavenegar) |
| `ghasedak` | قاصدک (Ghasedak) |
| `melipayamak` | ملی‌پیامک (Melipayamak) |
| `farazsms` | فراز اس‌ام‌اس (Farazsms) |
| `magfa` | مگفا (Magfa) |
| `ippanel` | آی‌پی‌پنل (IPPanel) |
| `niksms` | نیک اس‌ام‌اس (Niksms) |
| `mediana` | مدیانا (Mediana) |
| `limosms` | لیمو اس‌ام‌اس (Limosms) |
| `avanak` | آوانک — پیامک صوتی (Avanak) |
| `amoot` | آموت (Amoot) |
| `farapayamak` | فراپیامک (Farapayamak) |
| `payamito` | پیامیتو (Payamito) |
| `payamaknovin` | پیامک‌نوین (Payamaknovin) |
| `bahmanpayam` | بهمن‌پیام (Bahmanpayam) |
| `payamresan` | پیام‌رسان (Payamresan) |
| `behinpayam` | بهین‌پیام (Behinpayam) |
| `rastinsms` | راستین اس‌ام‌اس (Rastinsms) |

### پیام‌رسان‌ها

| کانفیگ | سرویس |
|--------|--------|
| `telegram` | تلگرام (Telegram) |
| `whatsapp` | واتساپ (WhatsApp) |
| `bale` | بله (Bale) |
| `eitaa` | ایتا (Eitaa) |
| `rubika` | روبیکا (Rubika) |
| `gap` | گپ (Gap) |
| `igap` | آی‌گپ (iGap) |
| `messenger` | فیسبوک مسنجر (Facebook Messenger) |
| `viber` | وایبر (Viber) |
| `line` | لاین (LINE) |
| `discord` | دیسکورد (Discord) |
| `slack` | اسلک (Slack) |

### ابزار توسعه

| کانفیگ | کاربرد |
|--------|--------|
| `array` | حافظه — مناسب تست و لوکال |
| `log` | نوشتن در لاگ لاراول |
| `failover` | زنجیرهٔ چند درایور (تعویض خودکار هنگام خطا) |

‫تنظیمات هر درایور در `config/smsapi.php` و مستندات Wiki آمده است.‬
‫‬
---

## چه کارهایی پوشش داده می‌شود؟

| نیاز                           | پشتیبانی |
|--------------------------------|----------|
| ارسال ساده / گروهی             | ✓ |
| قالب به ازای هر پنل            | ✓ |
| تعویض خودکار پنل هنگام خطا     | ✓ |
| چند حساب / چند خط روی یک پنل   | ✓ |
| صف و تلاش مجدد                 | ✓ |
| ‫ارسال OTP با ایمیل‬           | ✓ |
| ‫ساخت و بررسی OTP (Vault)‬     | ✓ اختیاری |
| ‫کانال Notification لاراول‬    | ✓ |
| نرمال‌سازی شماره ایران         | ✓ |
| لاگ ارسال‌ها                   | ✓ اختیاری |
| وضعیت تحویل (Webhook)          | ✓ اختیاری |
| Rate Limiter و Circuit Breaker | ✓ اختیاری |
| HTTP API برای ارسال            | ✓ اختیاری |
| پیام‌رسان‌ها                   | ✓ |
| `Sms::fake()` در تست           | ✓ |

---

## تنظیمات پرکاربرد

```env
SMSAPI_DRIVER=smsir
SMSAPI_API_KEY=
SMSAPI_LINE_NUMBER=

SMSAPI_FAILOVER_DRIVERS=smsir,kavenegar

SMSAPI_PHONE_FORMAT=local          # local | e164 | digits
SMSAPI_OTP_CHANNEL=sms             # sms | mail | both

SMSAPI_OTP_VAULT_ENABLED=false
SMSAPI_LOGGING_ENABLED=false
```

---

## مستندات

‫مستندات کامل به **انگلیسی** در GitHub Wiki:‬
‫‬
**https://github.com/alizeinodin/SMSApi/wiki**

- [Home](https://github.com/alizeinodin/SMSApi/wiki)
- [Installation](https://github.com/alizeinodin/SMSApi/wiki/installation)
- [Configuration](https://github.com/alizeinodin/SMSApi/wiki/configuration)
- [Sending messages](https://github.com/alizeinodin/SMSApi/wiki/sending)
- [Templates](https://github.com/alizeinodin/SMSApi/wiki/templates)
- [OTP vault](https://github.com/alizeinodin/SMSApi/wiki/otp-vault)
- [Queues & failover](https://github.com/alizeinodin/SMSApi/wiki/queues-failover)
- [Notifications](https://github.com/alizeinodin/SMSApi/wiki/notifications)
- [HTTP API & webhooks](https://github.com/alizeinodin/SMSApi/wiki/http-api)
- [Drivers](https://github.com/alizeinodin/SMSApi/wiki/drivers)
- [Adding a provider](https://github.com/alizeinodin/SMSApi/wiki/adding-a-provider)
- [Testing](https://github.com/alizeinodin/SMSApi/wiki/testing)
- [Security](https://github.com/alizeinodin/SMSApi/wiki/security)

---

## تغییرات و مجوز

- تغییرات نسخه‌ها: [CHANGELOG.md](CHANGELOG.md)
- مجوز: [MIT](LICENSE)
