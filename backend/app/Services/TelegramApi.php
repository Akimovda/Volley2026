<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Единая точка вызова Telegram Bot API (backend).
 *  - базовый адрес из services.telegram.api_base (TELEGRAM_API_BASE): по умолчанию https://api.telegram.org,
 *    при проблемах пути до Telegram — адрес релея (nginx на отдельном сервере);
 *  - короткий таймаут соединения (3 с) и повтор ТОЛЬКО при сбое установки соединения (запрос гарантированно не дошёл),
 *    чтобы не задвоить сообщение при «Operation timed out» уже после отправки.
 */
class TelegramApi
{
    public static function base(): string
    {
        return rtrim((string) (config('services.telegram.api_base') ?: 'https://api.telegram.org'), '/');
    }

    public static function url(string $token, string $method): string
    {
        return self::base() . "/bot{$token}/{$method}";
    }

    public static function http(int $timeout = 8): PendingRequest
    {
        return Http::connectTimeout(3)->timeout($timeout)->retry(3, 300, function ($e) {
            return $e instanceof ConnectionException
                && preg_match('/Failed to connect|SSL connection timeout|Could not resolve host|Connection refused|Connection timed out after \d+ milliseconds/i', $e->getMessage()) === 1;
        }, throw: false);
    }
}
