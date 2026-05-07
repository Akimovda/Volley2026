<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
        $isImpersonating = $request->hasSession() && $request->session()->has('impersonator_id');

        if ($isImpersonating && $request->user()) {
            // Синхронизируем hash для всех Sanctum guards (по умолчанию ['web'])
            // и default driver — на случай несовпадения.
            $drivers = array_unique(array_filter(array_merge(
                [Auth::getDefaultDriver()],
                (array) config('sanctum.guard', ['web'])
            )));
            $userHash = (string) $request->user()->getAuthPassword();

            foreach ($drivers as $driver) {
                $sessionKey = 'password_hash_' . $driver;
                if ((string) $request->session()->get($sessionKey, '') !== $userHash) {
                    $request->session()->put($sessionKey, $userHash);
                }
            }

            Log::warning('[ImpersonationSync] entering parent handle', [
                'path'             => $request->path(),
                'user_id'          => $request->user()->id,
                'impersonator_id'  => $request->session()->get('impersonator_id'),
                'drivers_synced'   => $drivers,
            ]);
        }

        try {
            return parent::handle($request, $next);
        } catch (AuthenticationException $e) {
            if ($isImpersonating) {
                Log::warning('[ImpersonationSync] parent threw AuthenticationException', [
                    'path'    => $request->path(),
                    'message' => $e->getMessage(),
                    'guards'  => $e->guards(),
                    'session_keys' => array_keys($request->session()->all()),
                ]);
            }
            throw $e;
        }
    }
}
