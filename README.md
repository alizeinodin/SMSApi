# پکیج لاراول SMS

با استفاده از این پکیج می توانید به پنل خود در وب سایت [sms.ir](https://sms.ir) متصل شوید و یک سیستم ارسال و دریافت SMS ایرانی در لاراول داشته باشید.

> به خاطر تحریم هایی که علیه ایران وجود دارد، اتصال به پنل های SMS که فریمورک لاراول به صورت پیش فرض قرار داده است، وجود ندارد.
وب سایت sms.ir یکی از وب سایت های معروف ارائه دهنده خدمات SMS در کشور است. با استفاده از این پکیج می توانید از وب سرویس های Restful این شرکت در لارول استفاده کنید.

## ویژگی ها
- استفاده از کتابخانه CURL
- ارسال و دریافت SMS
- کد فعالسازی
- ارسال پیامک به صورت انبوه
- ارسال پیامک با template
- باشگاه مشتریان
- و...

## نصب پکیج
نصب با استفاده از کامپوزر:
```
composer require alizne/smsapi
```

```
php artisan vendor:publish --provider="Alizne\SmsApi\SmsApiServiceProvider"
```

سپس در فایل env لاراول این موارد را اضافه کنید:
```
SMSAPI_API_KEY="Your Api Key"
SMSAPI_SECRET_KEY="Your Secret Key"
SMSAPI_LINE_NUMBER="Your Line Number"
```
مقادیر Api Key ، Secret Key و Line Number در پروفایل کاربری شما در [sms.ir](https://sms.ir) موجود است.


### پیش نیاز

| پیش نیاز  | حداقل نسخه |
| ------------- | ------------- |
|  PHP | 8.X  |
| ext-curl | * |

## بیشتر
اگر برای اجرای این پکیج روی local با مشکل SSL مواجه شدید. می توانید در فایل SMSApi.php این موارد را ویرایش کنید و به false تغییر دهید. 
```
CURLOPT_SSL_VERIFYHOST => false,
CURLOPT_SSL_VERIFYPEER => false,
```

