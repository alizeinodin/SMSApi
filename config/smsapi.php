<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Driver
    |--------------------------------------------------------------------------
    |
    | Used by Sms::send(), Sms::sendTemplate(), etc. when you do not call
    | driver(...) or failover(...) explicitly.
    |
    */
    'default' => env('SMSAPI_DRIVER', 'smsir'),

    /*
    |--------------------------------------------------------------------------
    | HTTP API (optional — php artisan smsapi:install api)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'enabled' => env('SMSAPI_API_ENABLED', false),
        'prefix' => env('SMSAPI_API_PREFIX', 'smsapi'),
        'middleware' => ['api'],
        'route_middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SMSAPI_API_ROUTE_MIDDLEWARE', ''))
        ))),
        'token' => env('SMSAPI_API_TOKEN'),
        'require_token' => env('SMSAPI_API_REQUIRE_TOKEN', true),
        // Query-string tokens show up in access logs. Headers only, unless opted in.
        'allow_query_token' => env('SMSAPI_API_ALLOW_QUERY_TOKEN', false),
        // Empty string disables the default limiter. Format: "max,minutes".
        'throttle' => env('SMSAPI_API_THROTTLE', '60,1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Message channels
    |--------------------------------------------------------------------------
    |
    | sms | telegram | whatsapp | bale — set per driver via `channel` key or
    | use built-in messenger drivers (telegram, whatsapp, bale).
    |
    */
    'channels' => [
        'sms',
        'telegram', 'whatsapp', 'bale',
        'eitaa', 'rubika', 'gap', 'igap',
        'messenger', 'viber', 'line', 'discord', 'slack',
    ],

    /*
    |--------------------------------------------------------------------------
    | Phone number normalization
    |--------------------------------------------------------------------------
    |
    | Iranian mobiles are normalized before every outbound send:
    |   0912… / 912… / +98912… / 0098912… / 98912…  →  format below
    |
    | format: local (09…) | e164 (+98…) | digits (98…)
    |
    */
    'phone' => [
        'enabled' => env('SMSAPI_PHONE_NORMALIZE', true),
        'format' => env('SMSAPI_PHONE_FORMAT', 'local'),
        'country_code' => env('SMSAPI_PHONE_COUNTRY', '98'),
        // true → throw InvalidPhoneNumberException; false → leave unparseable as-is
        'strict' => env('SMSAPI_PHONE_STRICT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queues
    |--------------------------------------------------------------------------
    |
    | Dedicated queues keep OTP ahead of bulk/marketing traffic.
    |
    |   Sms::queue('otp')->sendTemplate(...);
    |   php artisan queue:work --queue=sms-otp,sms,default
    |
    */
    'queue' => [
        'connection' => env('SMSAPI_QUEUE_CONNECTION'),
        'default' => env('SMSAPI_QUEUE_DEFAULT', 'default'),
        // Job timeout must exceed http.timeout × failover length. A read timeout
        // can mean the provider already accepted the message; retrying the job
        // or failing over to the next driver may send a duplicate.
        'tries' => env('SMSAPI_QUEUE_TRIES', 3),
        'timeout' => env('SMSAPI_QUEUE_TIMEOUT', 60),
        'backoff' => [10, 30, 60],

        // alias => real queue name
        'queues' => [
            'default' => env('SMSAPI_QUEUE', 'sms'),
            'otp' => env('SMSAPI_QUEUE_OTP', 'sms-otp'),
            'marketing' => env('SMSAPI_QUEUE_MARKETING', 'sms-marketing'),
        ],

        /*
        | Per-channel retry policy for SendMessageJob.
        | OTP retries faster; marketing is more patient / fewer tries.
        */
        'retry' => [
            'default' => [
                'tries' => env('SMSAPI_QUEUE_TRIES', 3),
                'timeout' => env('SMSAPI_QUEUE_TIMEOUT', 60),
                'backoff' => [10, 30, 60],
            ],
            'otp' => [
                'tries' => env('SMSAPI_QUEUE_OTP_TRIES', 5),
                'timeout' => env('SMSAPI_QUEUE_OTP_TIMEOUT', 30),
                'backoff' => [5, 15, 30, 60, 120],
            ],
            'marketing' => [
                'tries' => env('SMSAPI_QUEUE_MARKETING_TRIES', 2),
                'timeout' => env('SMSAPI_QUEUE_MARKETING_TIMEOUT', 90),
                'backoff' => [60, 300],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resilience (circuit breaker + rate limit)
    |--------------------------------------------------------------------------
    |
    | Enable with: php artisan smsapi:install resilience
    |
    */
    'resilience' => [
        'circuit_breaker' => [
            'enabled' => env('SMSAPI_CIRCUIT_BREAKER_ENABLED', false),
            // Open circuit after N consecutive failures.
            'failure_threshold' => env('SMSAPI_CIRCUIT_FAILURE_THRESHOLD', 5),
            // Keep circuit open for this many seconds (then half-open probe).
            'open_seconds' => env('SMSAPI_CIRCUIT_OPEN_SECONDS', 60),
        ],

        'rate_limit' => [
            'enabled' => env('SMSAPI_RATE_LIMIT_ENABLED', false),
            // Per provider driver (smsir, …)
            'driver' => [
                'max' => env('SMSAPI_RATE_LIMIT_DRIVER_MAX', 60),
                'per_seconds' => env('SMSAPI_RATE_LIMIT_DRIVER_SECONDS', 60),
            ],
            // Across all drivers
            'global' => [
                'max' => env('SMSAPI_RATE_LIMIT_GLOBAL_MAX', 120),
                'per_seconds' => env('SMSAPI_RATE_LIMIT_GLOBAL_SECONDS', 60),
            ],
            // Per destination phone / chat id (important for OTP abuse)
            'recipient' => [
                'max' => env('SMSAPI_RATE_LIMIT_RECIPIENT_MAX', 5),
                'per_seconds' => env('SMSAPI_RATE_LIMIT_RECIPIENT_SECONDS', 60),
            ],
            // By channel/queue alias (otp, marketing, …)
            'channels' => [
                'otp' => [
                    'max' => env('SMSAPI_RATE_LIMIT_OTP_MAX', 30),
                    'per_seconds' => env('SMSAPI_RATE_LIMIT_OTP_SECONDS', 60),
                ],
                'marketing' => [
                    'max' => env('SMSAPI_RATE_LIMIT_MARKETING_MAX', 20),
                    'per_seconds' => env('SMSAPI_RATE_LIMIT_MARKETING_SECONDS', 60),
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP delivery + optional vault
    |--------------------------------------------------------------------------
    |
    | Delivery (always available):
    |   Sms::template(...)->send(['CODE' => $code]) / Sms::otp(...)
    |   Channel: sms | mail | both — override with ->via() / ->mail() / ->both().
    |
    | Vault (optional — php artisan smsapi:install otp):
    |   Sms::challenge('login')->template('otp-login')->to($user)->send();
    |   Sms::challenge('login')->check($user, $request->code);
    |
    | Vault generates a code, stores HMAC-SHA256 (never plaintext), sends via
    | templates, then verifies with TTL + attempt limits. This is NOT required
    | for plain template delivery — apps may keep generating codes themselves.
    |
    */
    'otp' => [
        'channel' => env('SMSAPI_OTP_CHANNEL', 'sms'), // sms | mail | both

        'mail' => [
            'from' => [
                'address' => env('SMSAPI_OTP_MAIL_FROM', env('MAIL_FROM_ADDRESS')),
                'name' => env('SMSAPI_OTP_MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'OTP')),
            ],
            'subject' => env('SMSAPI_OTP_MAIL_SUBJECT', 'کد تایید شما'),
            // Package markdown view; publish with: --tag=smsapi-mail
            'markdown' => env('SMSAPI_OTP_MAIL_MARKDOWN', 'smsapi::mail.template'),
            // Used when building MailMessage / plain fallback (:CODE, :PARAM placeholders)
            'text' => env('SMSAPI_OTP_MAIL_TEXT', 'کد تایید شما: :CODE'),
        ],

        'vault' => [
            'enabled' => filter_var(env('SMSAPI_OTP_VAULT_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
            // database (sms_otp_challenges) | cache
            'store' => env('SMSAPI_OTP_VAULT_STORE', 'database'),
            'length' => (int) env('SMSAPI_OTP_LENGTH', 5),
            'ttl' => (int) env('SMSAPI_OTP_TTL', 300),
            'max_attempts' => (int) env('SMSAPI_OTP_MAX_ATTEMPTS', 5),
            // null → APP_KEY
            'pepper' => env('SMSAPI_OTP_PEPPER'),
            'code_parameter' => env('SMSAPI_OTP_CODE_PARAMETER', 'CODE'),
            // Never enable in production — exposes plaintext in OtpIssueResult
            'reveal_code' => filter_var(env('SMSAPI_OTP_REVEAL_CODE', false), FILTER_VALIDATE_BOOLEAN),
            // Queued vault sends put CODE in the job payload — off by default
            'allow_queue' => filter_var(env('SMSAPI_OTP_VAULT_ALLOW_QUEUE', false), FILTER_VALIDATE_BOOLEAN),
            'throttle' => [
                'enabled' => filter_var(env('SMSAPI_OTP_THROTTLE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'max' => (int) env('SMSAPI_OTP_THROTTLE_MAX', 3),
                'per_seconds' => (int) env('SMSAPI_OTP_THROTTLE_SECONDS', 60),
                'per_purpose' => filter_var(env('SMSAPI_OTP_THROTTLE_PER_PURPOSE', true), FILTER_VALIDATE_BOOLEAN),
            ],
        ],

        /*
        | Redact sensitive template params (CODE, otp, …) in sms_logs.
        | Recommended whenever you send OTP codes through this package.
        */
        'redact' => [
            'enabled' => filter_var(env('SMSAPI_OTP_REDACT', true), FILTER_VALIDATE_BOOLEAN),
            'replacement' => env('SMSAPI_OTP_REDACT_REPLACEMENT', '[redacted]'),
            'keys' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env(
                    'SMSAPI_OTP_REDACT_KEYS',
                    'CODE,code,otp,OTP,token,Token,token2,token3,password,pin,PIN'
                ))
            ))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Message-type send policies
    |--------------------------------------------------------------------------
    |
    | Named traffic classes with defaults for driver / failover, queue,
    | rate-limit channel, and optional send windows (marketing quiet hours).
    |
    |   Sms::policy('otp')->failover(['smsir', 'kavenegar'])->sendTemplate(...);
    |   Sms::policy('marketing')->driver('smsir')->send(...);
    |
    | If `queue` is set, send/verify are queued unless you call ->sync().
    | `rate_limit` maps to options['channel'] for ResilienceDriver channels.
    |
    */
    'messages' => [

        'otp' => [
            // 'driver' => 'smsir-otp', // named connection
            'failover' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('SMSAPI_OTP_FAILOVER', env('SMSAPI_FAILOVER_DRIVERS', 'smsir')))
            ))),
            'queue' => env('SMSAPI_OTP_QUEUE', 'otp'),
            'rate_limit' => 'otp',
        ],

        'transactional' => [
            'driver' => env('SMSAPI_TRANSACTIONAL_DRIVER'),
            'queue' => env('SMSAPI_TRANSACTIONAL_QUEUE', 'default'),
            'rate_limit' => 'default',
        ],

        'marketing' => [
            // Named connection or legacy transport, e.g. smsir-marketing / smsir
            'driver' => env('SMSAPI_MARKETING_DRIVER', env('SMSAPI_DRIVER', 'smsir')),
            'queue' => env('SMSAPI_MARKETING_QUEUE', 'marketing'),
            'rate_limit' => 'marketing',
            // Allowed wall-clock window (timezone-aware). Outside → OutsideAllowedHoursException.
            // Hours as int (9) or "09:00". Range is [start, end). Overnight start>end is supported.
            'allowed_hours' => [
                'start' => env('SMSAPI_MARKETING_ALLOWED_START', 9),
                'end' => env('SMSAPI_MARKETING_ALLOWED_END', 21),
            ],
            // Alternative to allowed_hours (blocked range). Prefer one or the other.
            // 'quiet_hours' => ['start' => 21, 'end' => 9],
            'timezone' => env('SMSAPI_MARKETING_TIMEZONE', env('APP_TIMEZONE', 'UTC')),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Template Registry
    |--------------------------------------------------------------------------
    |
    | named templates mapped to per-provider template IDs.
    |
    |   Sms::template('otp-login')->to($user)->send(['CODE' => '1234']);
    |   Sms::template('otp-login')->to($user)->via('mail')->send(['CODE' => '1234']);
    |   Sms::sendTemplate('0912...', 'otp-login', ['CODE' => '1234']);
    |
    | Flat map (provider or connection name → template id):
    |   'otp-login' => ['smsir' => 100, 'smsir-otp' => 101, 'kavenegar' => 'otp-login'],
    |
    | Named connections fall back to the provider key (smsir) when no
    | connection-specific id is defined.
    | Or with defaults:
    |   'otp-login' => [
    |       'drivers' => ['smsir' => 100, 'kavenegar' => 'otp-login'],
    |       'queue' => 'otp',
    |       'failover' => ['smsir', 'kavenegar'],
    |       'mail' => [
    |           'subject' => 'کد ورود',
    |           // 'markdown' => 'emails.otp-login',
    |       ],
    |   ],
    |
    */
    'templates' => [
        // 'otp-login' => [
        //     'smsir' => (int) env('SMSAPI_TEMPLATE_OTP_LOGIN', 100),
        //     // 'kavenegar' => 'otp-login',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | HTTP client defaults
    |--------------------------------------------------------------------------
    |
    | Per-driver `timeout` / `connect_timeout` override these. A read timeout
    | is ambiguous: the provider may already have accepted the message, so
    | failover or a queue retry can double-send. Connection timeouts are safe
    | to retry. Size queue job timeouts above timeout × failover drivers.
    |
    */
    'http' => [
        'timeout' => env('SMSAPI_TIMEOUT', 10),
        'connect_timeout' => env('SMSAPI_CONNECT_TIMEOUT', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Outbound logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        // Enable with: php artisan smsapi:install logging
        'enabled' => env('SMSAPI_LOGGING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery webhook + sync
    |--------------------------------------------------------------------------
    |
    | Enable with: php artisan smsapi:install delivery
    | Endpoint: POST /smsapi/webhooks/delivery/{driver?}
    |
    */
    'webhook' => [
        'enabled' => env('SMSAPI_WEBHOOK_ENABLED', false),
        'path' => env('SMSAPI_WEBHOOK_PATH', 'smsapi/webhooks/delivery'),
        'middleware' => ['api'],
        'token' => env('SMSAPI_WEBHOOK_TOKEN'),
        // Closed unless a token is set. Set SMSAPI_WEBHOOK_REQUIRE_TOKEN=false to restore an open webhook.
        'require_token' => env('SMSAPI_WEBHOOK_REQUIRE_TOKEN', true),
        'allow_query_token' => env('SMSAPI_WEBHOOK_ALLOW_QUERY_TOKEN', false),

        'sync' => [
            'enabled' => env('SMSAPI_DELIVERY_SYNC_ENABLED', false),
            'schedule' => env('SMSAPI_DELIVERY_SYNC_SCHEDULE', '*/10 * * * *'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Low balance alert
    |--------------------------------------------------------------------------
    |
    | Enable with: php artisan smsapi:install balance
    |
    */
    'balance_alert' => [

        'enabled' => env('SMSAPI_BALANCE_ALERT_ENABLED', false),

        // Minimum credit before alerting (same unit as the provider wallet).
        'threshold' => env('SMSAPI_BALANCE_THRESHOLD', 10000),

        // Provider to check; null/empty => default driver.
        'driver' => env('SMSAPI_BALANCE_ALERT_DRIVER'),

        // mail, sms, database, log
        'channels' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SMSAPI_BALANCE_ALERT_CHANNELS', 'mail,sms,database,log'))
        ))),

        // Do not re-send while still low, until this many minutes pass
        // (or until balance recovers above the threshold).
        'cooldown_minutes' => env('SMSAPI_BALANCE_ALERT_COOLDOWN', 360),

        // Cron expression used when auto-scheduling is enabled.
        'schedule' => env('SMSAPI_BALANCE_ALERT_SCHEDULE', '0 * * * *'),

        'mail' => [
            'to' => env('SMSAPI_BALANCE_ALERT_MAIL'),
        ],

        'sms' => [
            'to' => env('SMSAPI_BALANCE_ALERT_MOBILE'),
        ],

        'database' => [
            'model' => env('SMSAPI_BALANCE_ALERT_USER_MODEL', 'App\\Models\\User'),
            'ids' => env('SMSAPI_BALANCE_ALERT_USER_IDS'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Named connections / accounts
    |--------------------------------------------------------------------------
    |
    | Multiple API keys or line numbers on the same provider (مثل چند خط sms.ir).
    | Same idea as database.connections: each entry has a transport `driver`
    | plus credentials. Lookup order for Sms::driver('name'):
    |   1) connections.name
    |   2) drivers.name  (legacy single-account entries stay here)
    |
    |   Sms::connection('smsir-otp')->sendTemplate(...);
    |   Sms::driver('smsir-marketing')->send(...);
    |   Sms::failover(['smsir-otp', 'smsir-marketing'])->sendTemplate(...);
    |
    | Prefer this over setLineNumber() on a cached driver instance.
    |
    */
    'connections' => [

        // 'smsir-otp' => [
        //     'driver' => 'smsir',
        //     'api_key' => env('SMSAPI_OTP_API_KEY'),
        //     'line_number' => env('SMSAPI_OTP_LINE'),
        //     'base_url' => env('SMSAPI_BASE_URL', 'https://api.sms.ir/v1'),
        //     'timeout' => env('SMSAPI_TIMEOUT', 10),
        // ],
        //
        // 'smsir-marketing' => [
        //     'driver' => 'smsir',
        //     'api_key' => env('SMSAPI_MARKETING_API_KEY'),
        //     'line_number' => env('SMSAPI_MARKETING_LINE'),
        //     'base_url' => env('SMSAPI_BASE_URL', 'https://api.sms.ir/v1'),
        //     'timeout' => env('SMSAPI_TIMEOUT', 10),
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Drivers
    |--------------------------------------------------------------------------
    |
    | Built-in transports and the default single-account config (BC).
    | You may also put named accounts here with a `driver` key instead of
    | (or in addition to) the connections array above.
    |
    */
    'drivers' => [

        'failover' => [
            /*
            | Default chain when you call Sms::failover() without arguments.
            | Entries may be transport names or named connections:
            |   Sms::failover(['smsir-otp', 'smsir-marketing'])->sendTemplate(...);
            */
            'drivers' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('SMSAPI_FAILOVER_DRIVERS', 'smsir'))
            ))),
        ],

        'smsir' => [
            'api_key' => env('SMSAPI_API_KEY'),
            'line_number' => env('SMSAPI_LINE_NUMBER'),
            'base_url' => env('SMSAPI_BASE_URL', 'https://api.sms.ir/v1'),
            'timeout' => env('SMSAPI_TIMEOUT', 10),
        ],

        'kavenegar' => [
            'api_key' => env('KAVENEGAR_API_KEY'),
            'line_number' => env('KAVENEGAR_LINE_NUMBER'),
            'base_url' => env('KAVENEGAR_BASE_URL', 'https://api.kavenegar.com/v1'),
            'timeout' => env('KAVENEGAR_TIMEOUT', 10),
        ],

        'ghasedak' => [
            'api_key' => env('GHASEDAK_API_KEY'),
            'line_number' => env('GHASEDAK_LINE_NUMBER'),
            'base_url' => env('GHASEDAK_BASE_URL', 'https://api.ghasedak.me/v2'),
            'timeout' => env('GHASEDAK_TIMEOUT', 10),
        ],

        'melipayamak' => [
            'username' => env('MELIPAYAMAK_USERNAME'),
            'password' => env('MELIPAYAMAK_PASSWORD'),
            'line_number' => env('MELIPAYAMAK_LINE_NUMBER'),
            'base_url' => env('MELIPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('MELIPAYAMAK_TIMEOUT', 10),
        ],

        'farapayamak' => [
            'username' => env('FARAPAYAMAK_USERNAME'),
            'password' => env('FARAPAYAMAK_PASSWORD'),
            'line_number' => env('FARAPAYAMAK_LINE_NUMBER'),
            'base_url' => env('FARAPAYAMAK_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('FARAPAYAMAK_TIMEOUT', 10),
        ],

        'payamresan' => [
            'api_key' => env('PAYAMRESAN_API_KEY'),
            'line_number' => env('PAYAMRESAN_LINE_NUMBER'),
            'base_url' => env('PAYAMRESAN_BASE_URL', 'https://api.sms-webservice.com/api/V3'),
            'timeout' => env('PAYAMRESAN_TIMEOUT', 10),
        ],

        'magfa' => [
            'username' => env('MAGFA_USERNAME'),
            'password' => env('MAGFA_PASSWORD'),
            'domain' => env('MAGFA_DOMAIN', 'magfa'),
            'line_number' => env('MAGFA_LINE_NUMBER'),
            'base_url' => env('MAGFA_BASE_URL', 'https://sms.magfa.com/api/http/sms/v2'),
            'timeout' => env('MAGFA_TIMEOUT', 10),
        ],

        'avanak' => [
            'api_token' => env('AVANAK_API_TOKEN'),
            'otp_length' => env('AVANAK_OTP_LENGTH', 5),
            'server_id' => env('AVANAK_SERVER_ID', 0),
            'base_url' => env('AVANAK_BASE_URL', 'https://portal.avanak.ir/rest'),
            'timeout' => env('AVANAK_TIMEOUT', 10),
        ],

        'amoot' => [
            'username' => env('AMOOT_USERNAME'),
            'password' => env('AMOOT_PASSWORD'),
            'line_number' => env('AMOOT_LINE_NUMBER'),
            'base_url' => env('AMOOT_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('AMOOT_TIMEOUT', 10),
        ],

        'payamito' => [
            'username' => env('PAYAMITO_USERNAME'),
            'password' => env('PAYAMITO_PASSWORD'),
            'line_number' => env('PAYAMITO_LINE_NUMBER'),
            'base_url' => env('PAYAMITO_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('PAYAMITO_TIMEOUT', 10),
        ],

        'payamaknovin' => [
            'username' => env('PAYAMAKNOVIN_USERNAME'),
            'password' => env('PAYAMAKNOVIN_PASSWORD'),
            'line_number' => env('PAYAMAKNOVIN_LINE_NUMBER'),
            'base_url' => env('PAYAMAKNOVIN_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('PAYAMAKNOVIN_TIMEOUT', 10),
        ],

        'bahmanpayam' => [
            'username' => env('BAHMANPAYAM_USERNAME'),
            'password' => env('BAHMANPAYAM_PASSWORD'),
            'line_number' => env('BAHMANPAYAM_LINE_NUMBER'),
            'base_url' => env('BAHMANPAYAM_BASE_URL', 'https://rest.payamak-panel.com/api'),
            'timeout' => env('BAHMANPAYAM_TIMEOUT', 10),
        ],

        'behinpayam' => [
            'api_key' => env('BEHINPAYAM_API_KEY'),
            'line_number' => env('BEHINPAYAM_LINE_NUMBER'),
            'base_url' => env('BEHINPAYAM_BASE_URL', 'https://api.sms-webservice.com/api/V3'),
            'timeout' => env('BEHINPAYAM_TIMEOUT', 10),
        ],

        'rastinsms' => [
            'api_key' => env('RASTINSMS_API_KEY'),
            'line_number' => env('RASTINSMS_LINE_NUMBER'),
            'base_url' => env('RASTINSMS_BASE_URL', 'https://api.sms-webservice.com/api/V3'),
            'timeout' => env('RASTINSMS_TIMEOUT', 10),
        ],

        'farazsms' => [
            'api_key' => env('FARAZSMS_API_KEY'),
            'line_number' => env('FARAZSMS_LINE_NUMBER'),
            'base_url' => env('FARAZSMS_BASE_URL', 'https://api.iranpayamak.com'),
            'timeout' => env('FARAZSMS_TIMEOUT', 10),
        ],

        'niksms' => [
            'username' => env('NIKSMS_USERNAME'),
            'password' => env('NIKSMS_PASSWORD'),
            'line_number' => env('NIKSMS_LINE_NUMBER'),
            'base_url' => env('NIKSMS_BASE_URL', 'https://niksms.com/api/v2'),
            'timeout' => env('NIKSMS_TIMEOUT', 10),
        ],

        'limosms' => [
            'api_key' => env('LIMOSMS_API_KEY'),
            'line_number' => env('LIMOSMS_LINE_NUMBER'),
            'base_url' => env('LIMOSMS_BASE_URL', 'https://api.limosms.com'),
            'timeout' => env('LIMOSMS_TIMEOUT', 10),
        ],

        'mediana' => [
            'api_key' => env('MEDIANA_API_KEY'),
            'line_number' => env('MEDIANA_LINE_NUMBER'),
            'base_url' => env('MEDIANA_BASE_URL', 'https://api.mediana.ir'),
            'timeout' => env('MEDIANA_TIMEOUT', 10),
        ],

        'ippanel' => [
            'api_key' => env('IPPANEL_API_KEY'),
            'line_number' => env('IPPANEL_LINE_NUMBER'),
            'base_url' => env('IPPANEL_BASE_URL', 'https://api2.ippanel.com/api/v1'),
            'timeout' => env('IPPANEL_TIMEOUT', 10),
        ],

        'telegram' => [
            'channel' => 'telegram',
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'timeout' => env('TELEGRAM_TIMEOUT', 10),
        ],

        'whatsapp' => [
            'channel' => 'whatsapp',
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
            'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
            'timeout' => env('WHATSAPP_TIMEOUT', 10),
        ],

        'bale' => [
            'channel' => 'bale',
            'bot_token' => env('BALE_BOT_TOKEN'),
            'base_url' => env('BALE_BASE_URL', 'https://tapi.bale.ai/bot'),
            'timeout' => env('BALE_TIMEOUT', 10),
        ],

        'eitaa' => [
            'channel' => 'eitaa',
            'token' => env('EITAA_TOKEN'),
            'base_url' => env('EITAA_BASE_URL', 'https://eitaayar.ir'),
            'timeout' => env('EITAA_TIMEOUT', 10),
        ],

        'rubika' => [
            'channel' => 'rubika',
            'bot_token' => env('RUBIKA_BOT_TOKEN'),
            'base_url' => env('RUBIKA_BASE_URL', 'https://botapi.rubika.ir/v3'),
            'timeout' => env('RUBIKA_TIMEOUT', 10),
        ],

        'gap' => [
            'channel' => 'gap',
            'token' => env('GAP_BOT_TOKEN'),
            'base_url' => env('GAP_BASE_URL', 'https://api.gap.im'),
            'timeout' => env('GAP_TIMEOUT', 10),
        ],

        'igap' => [
            'channel' => 'igap',
            'bot_token' => env('IGAP_BOT_TOKEN'),
            'base_url' => env('IGAP_BOT_BASE_URL', 'https://bot.igap.net'),
            'timeout' => env('IGAP_TIMEOUT', 10),
        ],

        'messenger' => [
            'channel' => 'messenger',
            'page_access_token' => env('MESSENGER_PAGE_ACCESS_TOKEN'),
            'page_id' => env('MESSENGER_PAGE_ID', 'me'),
            'api_version' => env('MESSENGER_API_VERSION', 'v21.0'),
            'base_url' => env('MESSENGER_BASE_URL', 'https://graph.facebook.com'),
            'timeout' => env('MESSENGER_TIMEOUT', 10),
        ],

        'viber' => [
            'channel' => 'viber',
            'auth_token' => env('VIBER_AUTH_TOKEN'),
            'sender_name' => env('VIBER_SENDER_NAME', 'Bot'),
            'base_url' => env('VIBER_BASE_URL', 'https://chatapi.viber.com/pa'),
            'timeout' => env('VIBER_TIMEOUT', 10),
        ],

        'line' => [
            'channel' => 'line',
            'channel_access_token' => env('LINE_CHANNEL_ACCESS_TOKEN'),
            'base_url' => env('LINE_BASE_URL', 'https://api.line.me/v2/bot'),
            'timeout' => env('LINE_TIMEOUT', 10),
        ],

        'discord' => [
            'channel' => 'discord',
            'bot_token' => env('DISCORD_BOT_TOKEN'),
            'api_version' => env('DISCORD_API_VERSION', 'v10'),
            'base_url' => env('DISCORD_BASE_URL', 'https://discord.com/api'),
            'timeout' => env('DISCORD_TIMEOUT', 10),
        ],

        'slack' => [
            'channel' => 'slack',
            'bot_token' => env('SLACK_BOT_TOKEN'),
            'base_url' => env('SLACK_BASE_URL', 'https://slack.com/api'),
            'timeout' => env('SLACK_TIMEOUT', 10),
        ],

        /*
        | Local / testing transports (no HTTP to providers).
        |   SMSAPI_DRIVER=log
        |   SMSAPI_DRIVER=array
        | Or in tests: Sms::fake();
        */
        'log' => [
            'channel' => env('SMSAPI_LOG_CHANNEL'),
            'level' => env('SMSAPI_LOG_LEVEL', 'debug'),
            'line_number' => env('SMSAPI_LINE_NUMBER'),
        ],

        'array' => [
            'line_number' => env('SMSAPI_LINE_NUMBER'),
            'credit' => 999999,
        ],

        /*
        | 'kavenegar' => [
        |     'api_key' => env('KAVENEGAR_API_KEY'),
        |     'line_number' => env('KAVENEGAR_LINE_NUMBER'),
        |     'base_url' => 'https://api.kavenegar.com/v1',
        |     'timeout' => 10,
        | ],
        */

    ],

];
