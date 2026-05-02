<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockInImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('impersonator_id')) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Действие недоступно в режиме просмотра от имени пользователя.'], 403);
            }

            return back()->with('error', 'Это действие недоступно в режиме просмотра от имени пользователя.');
        }

        return $next($request);
    }
}
