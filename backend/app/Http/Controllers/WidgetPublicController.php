<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\OrganizerWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class WidgetPublicController extends Controller
{
    /** iFrame страница */
    public function iframe(Request $request, int $userId): Response
    {
        $widget = $this->resolveWidget($request, $userId);

        if (!$widget) {
            return response('Виджет недоступен.', 403);
        }

        $events = $this->getEvents($widget, $userId);

        return response()
            ->view('widget.iframe', compact('widget', 'events', 'userId'))
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors *");
    }

    /** JSON API для JS-виджета */
    public function json(Request $request): JsonResponse
    {
        $key    = $request->query('key', '');
        $widget = OrganizerWidget::where('api_key', $key)->where('is_active', true)->first();

        if (!$widget) {
            return response()->json(['ok' => false, 'error' => 'Invalid key'], 403);
        }

        // Проверка домена
        $referer = $request->header('Referer', '');
        if ($referer && !empty($widget->allowed_domains)) {
            $domain = parse_url($referer, PHP_URL_HOST) ?? '';
            if (!$widget->allowsDomain($domain)) {
                return response()->json(['ok' => false, 'error' => 'Domain not allowed'], 403);
            }
        }

        $events = $this->getEvents($widget, $widget->user_id);

        return response()->json([
            'ok'       => true,
            'settings' => $widget->settings,
            'events'   => $events,
        ])->header('Access-Control-Allow-Origin', '*');
    }

    /** JS-скрипт виджета */
    public function script(Request $request): Response
    {
        $key = $request->query('key', '');

        $js = <<<JS
(function() {
    var key = '{$key}';
    var container = document.getElementById('volley-widget');
    if (!container) { console.warn('volley-widget: #volley-widget not found'); return; }

    var color = container.dataset.color || '#f59e0b';

    container.innerHTML = '<div style="font-family:sans-serif;color:#666;padding:12px">Загрузка...</div>';

    fetch('https://volley-bot.store/api/widget/events?key=' + encodeURIComponent(key))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) { container.innerHTML = '<div style="color:red;padding:12px">Ошибка: ' + (data.error || 'unavailable') + '</div>'; return; }

            var html = '<div style="font-family:sans-serif;max-width:600px">';
            html += '<div style="font-weight:700;font-size:16px;margin-bottom:12px;color:' + color + '">🏐 Ближайшие мероприятия</div>';

            if (!data.events || data.events.length === 0) {
                html += '<div style="color:#999;font-size:14px">Нет запланированных мероприятий.</div>';
            } else {
                data.events.forEach(function(ev) {
                    html += '<div style="border:1px solid #eee;border-radius:12px;padding:14px;margin-bottom:10px">';
                    html += '<div style="font-weight:600;font-size:15px;margin-bottom:4px">' + ev.title + '</div>';
                    html += '<div style="font-size:13px;color:#666;margin-bottom:6px">📅 ' + ev.starts_at + '</div>';
                    if (ev.location) html += '<div style="font-size:13px;color:#666;margin-bottom:6px">📍 ' + ev.location + '</div>';
                    if (ev.slots_info) html += '<div style="font-size:13px;color:#666;margin-bottom:8px">👥 ' + ev.slots_info + '</div>';
                    html += '<a href="' + ev.url + '" target="_blank" style="display:inline-block;padding:6px 14px;background:' + color + ';color:#fff;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none">Записаться</a>';
                    html += '</div>';
                });
            }

            html += '<div style="margin-top:8px;font-size:11px;color:#ccc;text-align:right">на базе <a href="https://volley-bot.store" target="_blank" style="color:#ccc">volley-bot.store</a></div>';
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(function(e) { container.innerHTML = '<div style="color:red;padding:12px">Ошибка загрузки.</div>'; });
})();
JS;

        return response($js, 200, [
            'Content-Type'                => 'application/javascript',
            'Cache-Control'               => 'public, max-age=60',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function resolveWidget(Request $request, int $userId): ?OrganizerWidget
    {
        $key    = $request->query('key', '');
        $widget = OrganizerWidget::where('user_id', $userId)
            ->where('api_key', $key)
            ->where('is_active', true)
            ->first();

        return $widget;
    }

    private function getEvents(OrganizerWidget $widget, int $userId): array
    {
        $limit      = (int) $widget->getSetting('limit', 10);
        $showSlots  = (bool) $widget->getSetting('show_slots', true);
        $showLoc    = (bool) $widget->getSetting('show_location', true);

        $cacheKey = "widget_events_{$userId}_{$limit}";

        return Cache::remember($cacheKey, 120, function () use ($userId, $limit, $showSlots, $showLoc) {
            $occurrences = \App\Models\EventOccurrence::query()
                ->join('events', 'events.id', '=', 'event_occurrences.event_id')
                ->leftJoin('locations', 'locations.id', '=', 'events.location_id')
                ->where('events.organizer_id', $userId)
                ->where('events.allow_registration', true)
                ->whereNull('events.deleted_at')
                ->whereNull('event_occurrences.deleted_at')
                ->where('event_occurrences.starts_at', '>', now())
                ->orderBy('event_occurrences.starts_at')
                ->limit($limit)
                ->select([
                    'event_occurrences.id as occ_id',
                    'event_occurrences.starts_at',
                    'event_occurrences.slots_total',
                    'event_occurrences.slots_taken',
                    'events.id as event_id',
                    'events.title',
                    'events.is_private',
                    'locations.name as location_name',
                ])
                ->get();

            return $occurrences->map(function ($occ) use ($showSlots, $showLoc) {
                $slotsInfo = null;
                if ($showSlots && $occ->slots_total) {
                    $free      = max(0, $occ->slots_total - ($occ->slots_taken ?? 0));
                    $slotsInfo = "Свободно мест: {$free} из {$occ->slots_total}";
                }

                return [
                    'title'     => $occ->title,
                    'starts_at' => \Carbon\Carbon::parse($occ->starts_at)->format('d.m.Y H:i'),
                    'location'  => $showLoc ? $occ->location_name : null,
                    'slots_info'=> $slotsInfo,
                    'url'       => route('events.show', [
                        'event'      => $occ->event_id,
                        'occurrence' => $occ->occ_id,
                    ]),
                ];
            })->toArray();
        });
    }
}
