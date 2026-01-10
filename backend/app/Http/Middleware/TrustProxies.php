<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // nginx перед Laravel → доверяем прокси
    protected $proxies = '*';

    // учитываем X-Forwarded-* заголовки
    protected $headers = Request::HEADER_X_FORWARDED_ALL;
}
