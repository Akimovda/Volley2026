<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Боты/сканеры/мониторинг без сессионной куки не создают строку в `sessions`.
 *
 * Без этого каждый GET без куки = INSERT в БД (+ чтение при старте сессии): сканер на 10 запросов/с
 * пишет 10 строк/с в sessions. Подменяем драйвер на `array` ДО StartSession (драйвер читается
 * из конфига в момент старта сессии) — сессия живёт только в памяти запроса.
 * Обычные браузеры (и приложения) не затрагиваются: у них нужна сессия для CSRF первой же формы.
 */
class SkipSessionForBots
{
    private const PATTERN = '/bot|crawl|spider|slurp|curl|wget|python|go-http|java\/|okhttp(?!.*VolleyPlayApp)|libwww|nuclei|zgrab|masscan|scanner|headless|uptime-?kuma|monitor|axios|node-fetch|httpclient|^$/i';

    public function handle(Request $request, Closure $next): Response
    {
        if (
            $request->isMethodSafe()
            && !$request->cookies->has((string) config('session.cookie'))
            && preg_match(self::PATTERN, (string) $request->userAgent()) === 1
        ) {
            config(['session.driver' => 'array']);
        }

        return $next($request);
    }
}
