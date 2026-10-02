<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Доступ к Pro-аналитике организатора: активная подписка Организатор Pro (админ — всегда).
 * Без Pro: для HTML — страница с предложением подписки (403), для AJAX/JSON — 403 JSON.
 */
class EnsureOrganizerPro
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isAdmin() || $user->isOrganizerPro())) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok'    => false,
                'code'  => 'subscription_inactive',
                'error' => __('ui.pro_analytics_locked_text'),
            ], 403);
        }

        return response()->view('organizer-pro.analytics-locked', [], 403);
    }
}
