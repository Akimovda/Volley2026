<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    /**
     * Если пользователь гость и лезет в защищённый роут —
     * запоминаем куда он хотел попасть (intended) и уводим на /login.
     *
     * После успешного логина Laravel сам вернёт на intended,
     * если вы используете redirect()->intended(...) где это нужно
     * (или у вас Fortify/Jetstream это делает).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $next($request);
            }
        }

        // Сохраняем intended только для обычных GET-страниц
        // (чтобы не ломать POST/DELETE и т.п.)
        if ($request->method() === 'GET' && !$request->expectsJson()) {
            // Laravel сам использует это значение для redirect()->intended()
            $request->session()->put('url.intended', $request->fullUrl());
        }

        // Если у вас реально нет страницы /login — поменяйте на ваш роут/страницу входа
        return redirect()->to('/login');
    }
}
