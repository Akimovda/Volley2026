<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'yookassa/webhook',
            'auth/biometric-login',
            'auth/apple/callback',
            'auth/apple/native-callback',
        ]);
        $middleware->alias([
            'user.restricted'     => \App\Http\Middleware\EnsureUserNotRestricted::class,
            'track.view'          => \App\Http\Middleware\TrackPageView::class,
            'profile.completed'   => \App\Http\Middleware\EnsureProfileCompleted::class,
            'block.impersonation' => \App\Http\Middleware\BlockInImpersonation::class,
            'no-store-html'       => \App\Http\Middleware\NoStoreHtml::class,
            'abilities'           => \Laravel\Sanctum\Http\Middleware\CheckAbilities::class,
        ]);
        $middleware->appendToGroup('web', \App\Http\Middleware\SetLocale::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\EnsureProfileCompleted::class);
        $middleware->appendToGroup('web', \App\Http\Middleware\SavePushToken::class);
        $middleware->prependToGroup('api', \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class);
        // GlitchTip/Sentry — user.id в scope (web+api), см. config/sentry.php send_default_pii=false
        $middleware->append(\App\Http\Middleware\SentryUserContext::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 405 Method Not Allowed -> 404 (чтобы не палить внутренние роуты)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e, \Illuminate\Http\Request $request) {
            abort(404);
        });

        // GlitchTip/Sentry: «ожидаемые» исключения не репортим. Формально уже входят
        // в $internalDontReport самого Laravel (Handler.php) — дублируем явно, чтобы
        // не зависеть от недокументированного дефолта фреймворка.
        $exceptions->dontReport([
            \Illuminate\Auth\AuthenticationException::class,
            \Illuminate\Validation\ValidationException::class,
            \Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class,
        ]);

        \Sentry\Laravel\Integration::handles($exceptions);
    })
    ->create();