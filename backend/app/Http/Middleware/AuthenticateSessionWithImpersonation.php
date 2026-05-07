<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Jetstream\Http\Middleware\AuthenticateSession as JetstreamAuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Расширение Jetstream\AuthenticateSession.
 *
 * При активной impersonation (в session есть impersonator_id) синхронизирует
 * `password_hash_<driver>` в session с текущим аутентифицированным пользователем
 * ДО проверки родителя. Без этого Auth::loginUsingId() в ImpersonationController
 * может оставить расхождение hash → parent::handle() вызывает session()->flush()
 * (теряется impersonator_id) и AuthenticationException → редирект на /login.
 */
class AuthenticateSessionWithImpersonation extends JetstreamAuthenticateSession
{
    public function handle($request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has('impersonator_id') && $request->user()) {
            $driver = Auth::getDefaultDriver();
            $sessionKey = 'password_hash_' . $driver;
            $userHash = (string) $request->user()->getAuthPassword();
            $stored = (string) $request->session()->get($sessionKey, '');

            if ($stored !== $userHash) {
                $request->session()->put($sessionKey, $userHash);
            }
        }

        return parent::handle($request, $next);
    }
}
