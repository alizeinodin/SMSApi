<?php
return [
    'api_key' => env('SMSAPI_API_KEY', "null"),
    'secret_key' => env('SMSAPI_SECRET_KEY'),
    'line_number' => env('SMSAPI_LINE_NUMBER'),
    'ssl_verifier' => env('SMSAPI_SSL', true) === 'false' ? false : true,
];
