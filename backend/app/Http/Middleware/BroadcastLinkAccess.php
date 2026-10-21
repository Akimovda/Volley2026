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
            abort(404);
        }

        if ($link->last_used_at === null || $link->last_used_at->lt(now()->subMinute())) {
            BroadcastLink::whereKey($link->id)->update(['last_used_at' => now()]);
        }

        $request->attributes->set('broadcast_link', $link);

        return $next($request);
    }
}
