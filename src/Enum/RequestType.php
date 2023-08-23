<?php

namespace Alizne\SmsApi\Enum;
enum RequestType: string
{
    case GET = 'GET';
    case POST = 'POST';
    case DELETE = 'DELETE';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
}