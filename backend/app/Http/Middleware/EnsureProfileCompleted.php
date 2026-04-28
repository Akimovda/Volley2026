<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfileCompleted
{
    private const ALLOWED_PREFIXES = [
        '/profile/complete',
        '/profile/extra',
        '/auth/',
        '/logout',
        '/api/',
        '/storage/',
        '/assets/',
        '/build/',
        '/admin/',
        '/payment/',
        '/yookassa/',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Боты, тест-аккаунты, admins — не трогаем
        if ($user->is_bot || $user->is_test || $user->isAdmin()) {
            return $next($request);
        }

        $path = '/' . ltrim($request->path(), '/');

        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return $next($request);
            }
        }

        if ($user->isProfileComplete()) {
            // Фиксируем дату первого заполнения если ещё не стоит
            if (is_null($user->profile_completed_at)) {
                $user->profile_completed_at = now();
                $user->save();
            }
            return $next($request);
        }

        // AJAX / JSON запросы — не редиректим
        if ($request->expectsJson() || $request->ajax()) {
            return $next($request);
        }

        return redirect()->route('profile.complete')
            ->with('profile_prompt', true);
    }
}
