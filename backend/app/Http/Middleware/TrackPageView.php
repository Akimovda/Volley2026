<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TrackPageView
{
    public function handle(Request $request, Closure $next, string $entityType, string $routeParam): mixed
    {
        $response = $next($request);

        // Только GET и 200
        if (!$request->isMethod('GET') || $response->getStatusCode() !== 200) {
            return $response;
        }

        try {
            $routeValue = $request->route($routeParam);
            if (!$routeValue) return $response;
            // Route model binding возвращает объект — берём id
            $entityId = is_object($routeValue) ? $routeValue->getKey() : (int) $routeValue;
            if (!$entityId) return $response;

            $userId = auth()->id();
            $sessionId = $request->session()->getId();

            // Дедупликация — одна запись на сессию
            $exists = DB::table('page_views')
                ->where('entity_type', $entityType)
                ->where('entity_id', (int) $entityId)
                ->where('session_id', $sessionId)
                ->where('created_at', '>=', now()->subHours(1))
                ->exists();

            if (!$exists) {
                DB::table('page_views')->insert([
                    'entity_type' => $entityType,
                    'entity_id'   => (int) $entityId,
                    'user_id'     => $userId,
                    'ip'          => $request->ip(),
                    'session_id'  => $sessionId,
                    'is_bot'      => false,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        } catch (\Throwable $e) {
            \Log::warning("TrackPageView error: " . $e->getMessage(), [
                "entity_type" => $entityType,
                "route_param" => $routeParam,
                "url" => $request->url(),
            ]);
        }

        return $response;
    }
}
