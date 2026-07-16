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
 * - НЕ блокирует админку (и вообще ничего кроме join/вступления в вейтлист)
 * - Блокирует запись на мероприятие (events.join, occurrences.join,
 *   occurrences.waitlist.join), если event_id входит в активный запрет
 *   пользователя, либо если у пользователя активен глобальный бан
 *   (event_ids пуст/NULL — запрет на ВСЕ мероприятия, "event_all").
 * - Отмену записи (events.leave) НЕ блокируем (пусть может выйти).
 */
class EnsureUserNotRestricted
{
    private const RESTRICTED_ROUTES = [
        'events.join',
        'occurrences.join',
        'occurrences.waitlist.join',
    ];

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
        // 1) Блокируем ТОЛЬКО join/вступление в вейтлист
        // -----------------------------
        $routeName = (string) ($request->route()?->getName() ?? '');
        if (!in_array($routeName, self::RESTRICTED_ROUTES, true)) {
            return $next($request);
        }

        // -----------------------------
        // 2) Достаем event_id из route model binding
        //    events.join: параметр 'event'
        //    occurrences.join / occurrences.waitlist.join: параметр 'occurrence' → берём event_id из него
        // -----------------------------
        $eventId = null;

        if ($routeName === 'occurrences.join' || $routeName === 'occurrences.waitlist.join') {
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

        // -----------------------------
        // 3) Собираем активные restrictions scope=events
        //    active = ends_at IS NULL OR ends_at > now()
        //    event_ids пуст/NULL у строки => глобальный бан (event_all, все мероприятия)
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
        $hasGlobalBan = false;

        foreach ($rows as $r) {
            // event_ids может быть json строкой или уже массивом (зависит от драйвера/каста)
            $decoded = $r->event_ids;

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            if (empty($decoded)) {
                $hasGlobalBan = true;
                continue;
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
        // 4) Глобальный бан — не пускаем независимо от event_id
        // -----------------------------
        if ($hasGlobalBan) {
            return redirect()
                ->to('/events')
                ->with('error', __('events.restriction_blocked_all'));
        }

        if (!$eventId) {
            return $next($request);
        }

        // -----------------------------
        // 5) Если этот event_id запрещен точечно — не пускаем
        // -----------------------------
        if (in_array($eventId, $restrictedEventIds, true)) {
            return redirect()
                ->to('/events')
                ->with('error', __('events.restriction_blocked_event'));
        }

        return $next($request);
    }
}
