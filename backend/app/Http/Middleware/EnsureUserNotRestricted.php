<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * EnsureUserNotRestricted (ONLY events restrictions)
 *
 * Что делает:
 * - НЕ блокирует админку (и вообще ничего кроме join)
 * - Блокирует запись на мероприятие (events.join и occurrences.join),
 *   если event_id входит в активный запрет пользователя.
 * - Отмену записи (events.leave) НЕ блокируем (пусть может выйти).
 */
class EnsureUserNotRestricted
{
    public function handle(Request $request, Closure $next)
    {
        // -----------------------------
        // 0) Гость — нечего проверять
        // -----------------------------
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        // -----------------------------
        // 1) Блокируем ТОЛЬКО join (legacy events.join и новый occurrences.join)
        // -----------------------------
        $routeName = (string) ($request->route()?->getName() ?? '');
        if (!in_array($routeName, ['events.join', 'occurrences.join'], true)) {
            return $next($request);
        }

        // -----------------------------
        // 2) Достаем event_id из route model binding
        //    events.join: параметр 'event'
        //    occurrences.join: параметр 'occurrence' → берём event_id из него
        // -----------------------------
        $eventId = null;

        if ($routeName === 'occurrences.join') {
            $occurrenceParam = $request->route('occurrence');
            if (is_object($occurrenceParam) && isset($occurrenceParam->event_id)) {
                $eventId = (int) $occurrenceParam->event_id;
            } elseif (is_numeric($occurrenceParam)) {
                $eventId = (int) DB::table('event_occurrences')
                    ->where('id', (int) $occurrenceParam)
                    ->value('event_id');
            }
        } else {
            $eventParam = $request->route('event');
            if (is_object($eventParam) && isset($eventParam->id)) {
                $eventId = (int) $eventParam->id;
            } elseif (is_numeric($eventParam)) {
                $eventId = (int) $eventParam;
            }
        }

        if (!$eventId) {
            return $next($request);
        }

        // -----------------------------
        // 3) Собираем активные restrictions scope=events
        //    active = ends_at IS NULL OR ends_at > now()
        // -----------------------------
        $rows = DB::table('user_restrictions')
            ->select(['event_ids'])
            ->where('user_id', (int) $user->id)
            ->where('scope', 'events')
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get();

        $restrictedEventIds = [];

        foreach ($rows as $r) {
            // event_ids может быть json строкой или уже массивом (зависит от драйвера/каста)
            $decoded = $r->event_ids;

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (is_array($decoded)) {
                foreach ($decoded as $id) {
                    if (is_numeric($id)) {
                        $restrictedEventIds[] = (int) $id;
                    }
                }
            }
        }

        $restrictedEventIds = array_values(array_unique($restrictedEventIds));

        // -----------------------------
        // 4) Если этот event_id запрещен — не пускаем
        // -----------------------------
        if (in_array($eventId, $restrictedEventIds, true)) {
            return redirect()
                ->to('/events')
                ->with('error', 'У вашей учетной записи есть ограничения для этого мероприятия.');
        }

        return $next($request);
    }
}
