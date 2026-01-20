<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\DB;

class EventsController extends Controller
{
    public function index()
    {
        // -----------------------------
        // 1) Список событий
        // -----------------------------
        $events = Event::query()
            ->orderByDesc('id')
            ->get();

        // -----------------------------
        // 2) На какие события юзер уже записан
        // -----------------------------
        $joinedEventIds = [];
        $restrictedEventIds = [];

        if (auth()->check()) {
            $userId = (int) auth()->id();

            $joinedEventIds = DB::table('event_registrations')
                ->where('user_id', $userId)
                ->pluck('event_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            // -----------------------------
            // 3) Собираем активные events-ограничения
            //    active = ends_at IS NULL OR ends_at > now()
            // -----------------------------
            $rows = DB::table('user_restrictions')
                ->select(['event_ids'])
                ->where('user_id', $userId)
                ->where('scope', 'events')
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
                })
                ->get();

            $ids = [];

            foreach ($rows as $r) {
                $decoded = $r->event_ids;

                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }

                if (is_array($decoded)) {
                    foreach ($decoded as $eid) {
                        if (is_numeric($eid)) {
                            $ids[] = (int) $eid;
                        }
                    }
                }
            }

            $restrictedEventIds = array_values(array_unique($ids));
        }

        // -----------------------------
        // 4) Отдаем всё в шаблон
        // -----------------------------
        return view('events.index', [
            'events'             => $events,
            'joinedEventIds'     => $joinedEventIds,
            'restrictedEventIds' => $restrictedEventIds, // <-- для disabled кнопки "Записаться"
        ]);
    }
}
