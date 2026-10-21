<?php

namespace App\Http\Middleware;

use App\Models\BroadcastLink;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * scorer.link — доступ ведущего матча по токен-ссылке площадки (/s/{token}). Работает ПОСЛЕ группы web (сессия, CSRF).
 * Отозвана/просрочена/не найдена — страница «Ссылка недействительна» (это не оверлей, текст допустим).
 * Приглашение пользователя (assigned_user_id): без входа — на вход с возвратом, под другим аккаунтом — отказ.
 */
class ScorerLinkAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->route('token');
        $link = $token !== '' ? BroadcastLink::with(['court', 'event'])->where('token', $token)->first() : null;

        if (!$link || $link->role !== BroadcastLink::ROLE_SCORER || !$link->court || !$link->isActive()) {
            return $this->page($request, 404, 'invalid');
        }

        if ($link->assigned_user_id) {
            $user = $request->user();
            if (!$user) {
                if ($request->isMethodSafe() && !$request->expectsJson()) {
                    $request->session()->put('url.intended', $request->fullUrl());
                    return redirect()->route('login', ['return' => $request->fullUrl()]);
                }
                return $this->deny($request, 401, __('broadcast.scorer_login_required'));
            }
            if ((int) $user->id !== (int) $link->assigned_user_id) {
                return $this->page($request, 403, 'other_user');
            }
        }

        $request->attributes->set('broadcast_link', $link);

        return $next($request);
    }

    private function deny(Request $request, int $status, string $message): Response
    {
        return response()->json(['error' => $message], $status);
    }

    private function page(Request $request, int $status, string $reason): Response
    {
        if ($request->expectsJson()) {
            return $this->deny($request, $status, __('broadcast.scorer_invalid_title'));
        }

        return response()->view('scorer.invalid', ['reason' => $reason], $status)
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
