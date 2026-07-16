<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OrganizerWidget;
use App\Services\TournamentTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WidgetPublicController extends Controller
{
    /** Режимы регистрации, где счётчик ведётся в командах (см. countRegisteredTeams()) */
    private const TEAM_MODES = ['team_classic', 'team_beach', 'team'];

    /** Режимы регистрации, где счётчик в игроках, а лимит берётся из egs/ets (не из команд) */
    private const INDIVIDUAL_TOURNAMENT_MODES = ['tournament_individual', 'king_beach'];

    private TournamentTeamService $teamService;

    public function __construct(TournamentTeamService $teamService)
    {
        $this->teamService = $teamService;
    }

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

        // Подписи считаются на нашей стороне (i18n через __()) и подставляются в JS как
        // готовые строки — сам JS выполняется в браузере стороннего сайта, где Laravel-locale
        // недоступен. reserveSuffixTpl содержит плейсхолдер ':count', заменяется в рантайме JS.
        $unitTeams        = addslashes(__('events.card_seats_teams'));
        $unitPlayers       = addslashes(__('events.card_seats_players'));
        $ofWord            = addslashes(__('events.card_seats_of'));
        $reserveSuffixTpl  = addslashes(__('events.widget_reserve_suffix'));

        $js = <<<JS
(function() {
    var key = '{$key}';
    var container = document.getElementById('volley-widget');
    if (!container) { console.warn('volley-widget: #volley-widget not found'); return; }

    var color = container.dataset.color || '#f59e0b';
    var unitTeams = '{$unitTeams}';
    var unitPlayers = '{$unitPlayers}';
    var ofWord = '{$ofWord}';
    var reserveSuffixTpl = '{$reserveSuffixTpl}';

    function slotsText(si) {
        var unit = si.unit === 'teams' ? unitTeams : unitPlayers;
        var text = si.taken + ' ' + ofWord + ' ' + si.max + unit;
        if (si.reserve > 0) { text += reserveSuffixTpl.replace(':count', si.reserve); }
        return text;
    }

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
                    html += '<div style="font-size:13px;color:#666;margin-bottom:6px">📅 ' + ev.date_long + ', ' + ev.time_range + '</div>';
                    if (ev.address) html += '<div style="font-size:13px;color:#666;margin-bottom:6px">📍 ' + ev.address + '</div>';
                    if (ev.slots_info) html += '<div style="font-size:13px;color:#666;margin-bottom:8px">👥 ' + slotsText(ev.slots_info) + '</div>';
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
            // Живой COUNT вместо event_occurrence_stats (кеш устаревает и покрывает
            // только часть occurrences — см. report_cache_counters_audit_2026-07-16.md).
            // limit ≤ 50 (валидация в OrganizerWidgetController), скалярный подзапрос
            // в SELECT — не N+1, один запрос считает разом до 50 occurrences.
            // egs/ets — нужны для канонического лимита (tournament_teams_count → ets → egs,
            // см. EventRegistrationGuard::check()/buildAvailabilitySnapshot() и CLAUDE.md).
            $occurrences = \App\Models\EventOccurrence::query()
                ->join('events', 'events.id', '=', 'event_occurrences.event_id')
                ->leftJoin('locations', 'locations.id', '=', 'events.location_id')
                ->leftJoin('cities', 'cities.id', '=', 'locations.city_id')
                ->leftJoin('event_game_settings as egs', 'egs.event_id', '=', 'events.id')
                ->leftJoin('event_tournament_settings as ets', 'ets.event_id', '=', 'events.id')
                ->where('events.organizer_id', $userId)
                ->where('events.allow_registration', true)
                ->whereRaw('(event_occurrences.is_cancelled IS NULL OR event_occurrences.is_cancelled = false)')
                ->where('event_occurrences.starts_at', '>', now())
                ->orderBy('event_occurrences.starts_at')
                ->limit($limit)
                ->select([
                    'event_occurrences.id as occ_id',
                    'event_occurrences.starts_at',
                    'event_occurrences.max_players',
                    'event_occurrences.duration_sec',
                    DB::raw('(SELECT COUNT(*) FROM event_registrations er
                        WHERE er.occurrence_id = event_occurrences.id
                        AND er.cancelled_at IS NULL
                        AND (er.is_cancelled IS NULL OR er.is_cancelled = false)
                        AND (er.status IS NULL OR er.status != \'cancelled\')
                    ) as registered_count'),
                    'events.id as event_id',
                    'events.title',
                    'events.direction',
                    'events.format as ev_format',
                    'events.registration_mode as reg_mode',
                    'events.tournament_teams_count as tt_count',
                    'events.season_id as ev_season_id',
                    'events.is_private',
                    'events.is_paid',
                    'events.price_minor',
                    'events.price_currency',
                    'events.price_text',
                    'events.classic_level_min',
                    'events.classic_level_max',
                    'events.beach_level_min',
                    'events.beach_level_max',
                    'locations.name as location_name',
                    'locations.address as location_address',
                    'cities.name as city_name',
                    'egs.max_players as egs_max_players',
                    'egs.reserve_players_max as egs_reserve_players_max',
                    'egs.teams_count as egs_teams_count',
                    'ets.teams_count as ets_teams_count',
                    'ets.total_players_max as ets_total_players_max',
                ])
                ->get();

            return $occurrences->map(function ($occ) use ($showSlots, $showLoc) {
                $slotsInfo = $this->buildSlotsInfo($occ, $showSlots);

                // Адрес
                $addressParts = array_filter([
                    $occ->location_name,
                    $occ->city_name,
                    $occ->location_address,
                ]);
                $address = $showLoc ? implode(', ', $addressParts) : null;

                // Дата/время
                $startsAt = \Carbon\Carbon::parse($occ->starts_at, 'UTC');
                $endsAt   = $occ->duration_sec
                    ? $startsAt->copy()->addSeconds((int)$occ->duration_sec)
                    : null;

                // Уровень
                $dir = $occ->direction ?? 'classic';
                $lvMin = $dir === 'beach' ? $occ->beach_level_min : $occ->classic_level_min;
                $lvMax = $dir === 'beach' ? $occ->beach_level_max : $occ->classic_level_max;

                // Цена
                $priceLabel = null;
                if ($occ->is_paid) {
                    if (!is_null($occ->price_minor)) {
                        $priceLabel = number_format($occ->price_minor / 100, 0, '.', ' ') . ' ₽';
                    } elseif (!empty($occ->price_text)) {
                        $priceLabel = $occ->price_text;
                    }
                }

                return [
                    'title'      => $occ->title,
                    'date_long'  => $startsAt->locale('ru')->translatedFormat('d F'),
                    'time_range' => $startsAt->format('H:i') . ($endsAt ? '–' . $endsAt->format('H:i') : ''),
                    'direction'  => $dir,
                    'address'    => $address,
                    'slots_info' => $slotsInfo,
                    'level_min'  => $lvMin,
                    'level_max'  => $lvMax,
                    'price'      => $priceLabel,
                    'is_private' => (bool) $occ->is_private,
                    'url'        => route('events.show', [
                        'event'      => $occ->event_id,
                        'occurrence' => $occ->occ_id,
                    ]),
                ];
            })->toArray();
        });
    }

    /**
     * slots_info по канонической матрице типов (та же логика, что в EventRegistrationGuard/
     * OrgDashboardController/countRegisteredTeams — см. CLAUDE.md «Турниры» и «Выпил кеш-счётчиков»).
     * unit='teams' — командные турниры (счёт в командах, лимит tournament_teams_count→ets→egs);
     * unit='players' — всё остальное, включая tournament_individual/king_beach (лимит egs→ets,
     * без сложения с резервом — у individual/king_beach резерва в этом смысле нет).
     */
    private function buildSlotsInfo(object $occ, bool $showSlots): ?array
    {
        $isTournament = (string) ($occ->ev_format ?? '') === 'tournament';
        $regMode      = (string) ($occ->reg_mode ?? '');

        if ($isTournament && in_array($regMode, self::TEAM_MODES, true)) {
            $unit    = 'teams';
            $maxP    = (int) ($occ->tt_count ?: $occ->ets_teams_count ?: $occ->egs_teams_count ?: 0);
            $counts  = $this->teamService->countRegisteredTeams(
                (int) $occ->event_id,
                (int) $occ->occ_id,
                $occ->ev_season_id ? (int) $occ->ev_season_id : null
            );
            $taken   = $counts['registered'];
            $reserve = $counts['reserve'];
        } elseif ($isTournament && in_array($regMode, self::INDIVIDUAL_TOURNAMENT_MODES, true)) {
            $unit    = 'players';
            $maxP    = (int) ($occ->egs_max_players ?: $occ->ets_total_players_max ?: 0);
            $taken   = (int) ($occ->registered_count ?? 0);
            $reserve = 0;
        } else {
            $unit       = 'players';
            $reserveMax = (int) ($occ->egs_reserve_players_max ?? 0);
            $maxP       = (int) ($occ->max_players ?: $occ->egs_max_players ?: 0) + $reserveMax;
            $taken      = (int) ($occ->registered_count ?? 0);
            $reserve    = $reserveMax;
        }

        if (!$showSlots || $maxP <= 0) {
            return null;
        }

        return [
            'taken'   => $taken,
            'max'     => $maxP,
            'free'    => max(0, $maxP - $taken),
            'unit'    => $unit,
            'reserve' => $reserve,
        ];
    }
}
