<?php
declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\Laravel\Integration;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * send_default_pii=false в config/sentry.php отключает встроенный сбор
 * пользователя пакетом целиком (id/email/username), см.
 * Sentry\Laravel\ServiceProvider::bindEvents(). Кладём в scope только id.
 */
class SentryUserContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            Integration::configureScope(function (Scope $scope) use ($request) {
                $scope->setUser(['id' => $request->user()->getAuthIdentifier()]);
            });
        }

        return $next($request);
    }
}
