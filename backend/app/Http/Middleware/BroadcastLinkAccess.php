<?php

namespace App\Http\Middleware;

use App\Models\BroadcastLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * broadcast.link:{role} — проверка токен-ссылки (роль, отзыв, срок). Любая причина отказа — 404,
 * чтобы не подсказывать, существует ли токен. Ссылка кладётся в $request->attributes['broadcast_link'].
 */
class BroadcastLinkAccess
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $token = (string) $request->route('token');
        $link = $token !== '' ? BroadcastLink::where('token', $token)->first() : null;

        if (!$link || $link->role !== $role || !$link->isActive()) {
            return self::blank($request);
        }

        if ($link->last_used_at === null || $link->last_used_at->lt(now()->subMinute())) {
            BroadcastLink::whereKey($link->id)->update(['last_used_at' => now()]);
        }

        $request->attributes->set('broadcast_link', $link);

        return $next($request);
    }

    /** 404 без видимого текста: в кадре эфира ошибка недопустима (страница — прозрачная, /state — пустое тело). */
    public static function blank(Request $request): Response
    {
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
        if (str_ends_with($request->path(), '/state')) {
            return response('', 404, $headers + ['Access-Control-Allow-Origin' => '*']);
        }

        return response('<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<style>html,body{margin:0;background:transparent}</style></head><body></body></html>',
            404, $headers + ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
