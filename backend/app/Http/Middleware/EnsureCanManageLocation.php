<?php

namespace App\Http\Middleware;

use App\Models\Location;
use Closure;
use Illuminate\Http\Request;

/**
 * Доступ к настройкам локации: администратор или владелец площадки (менеджер клуба с owner_id = он сам).
 */
class EnsureCanManageLocation
{
    public function handle(Request $request, Closure $next)
    {
        $location = $request->route('location');
        if (!$location instanceof Location) {
            $location = Location::find((int) $location);
        }

        abort_unless($location && $location->canBeManagedBy($request->user()), 403);

        return $next($request);
    }
}
