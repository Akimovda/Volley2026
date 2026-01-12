<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * ------------------------------------------------------------------
     * Исключения из CSRF-проверки
     * ------------------------------------------------------------------
     *
     * ⚠️ ВАЖНО:
     * - НЕ отключаем CSRF глобально
     * - Исключаем ТОЛЬКО Telegram Login Widget callback
     *
     * Почему:
     * - Telegram НЕ отправляет CSRF token
     * - VK / Yandex — OAuth, CSRF не нужен (state handled Socialite)
     * - Fortify (login/register/password/confirm) ОБЯЗАНЫ быть под CSRF
     *
     * @var array<int, string>
     */
    protected $except = [
        // Telegram Login Widget callback
        'auth/telegram/callback',
    ];
}
