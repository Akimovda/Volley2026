<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OrganizerWidget;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Данные мероприятий для виджета организатора (iframe / JS / предпросмотр).
 * Вынесено из WidgetPublicController, чтобы тот же список использовал и предпросмотр в ЛК.
 */
class WidgetEventsService
{
    /** Режимы регистрации, где счётчик ведётся в командах (см. countRegisteredTeams()) */
    private const TEAM_MODES = ['team_classic', 'team_beach', 'team'];

    /** Режимы регистрации, где счётчик в игроках, а лимит берётся из egs/ets (не из команд) */
    private const INDIVIDUAL_TOURNAMENT_MODES = ['tournament_individual', 'king_beach'];

    public function __construct(private TournamentTeamService $teamService)
    {
    }

    public function getEvents(OrganizerWidget $widget, int $userId): array
    {
        $limit      = (int) $widget->getSetting('limit', 10);
        $showSlots  = (bool) $widget->getSetting('show_slots', true);
        $showLoc    = (bool) $widget->getSetting('show_location', true);

        $cacheKey = "widget_events_v7_{$userId}_{$limit}_" . (int) $showSlots . (int) $showLoc . '_' . app()->getLocale();

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
                    'events.public_token',
                    'events.event_photos',
                    'events.timezone as ev_timezone',
                    'event_occurrences.timezone as occ_timezone',
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
                    'cities.region as city_region',
                    'egs.max_players as egs_max_players',
                    'egs.reserve_players_max as egs_reserve_players_max',
                    'egs.teams_count as egs_teams_count',
                    'ets.teams_count as ets_teams_count',
                    'ets.total_players_max as ets_total_players_max',
                ])
                ->get();

            $photoUrls = $this->firstPhotoUrls($occurrences);
            $models = \App\Models\EventOccurrence::with(['event.gameSettings', 'event.organizer'])
                ->whereIn('id', $occurrences->pluck('occ_id'))->get()->keyBy('id');

            return $occurrences->map(function ($occ) use ($showSlots, $showLoc, $photoUrls, $models, $userId) {
                $slotsInfo = $this->buildSlotsInfo($occ, $showSlots);

                // Адрес
                $addressParts = array_filter([
                    $occ->location_name,
                    $occ->city_name,
                    $occ->location_address,
                ]);
                $address = $showLoc ? implode(', ', $addressParts) : null;

                // Дата/время
                // starts_at хранится в UTC — показываем в поясе мероприятия, как на /events
                $tz       = (string) ($occ->occ_timezone ?: ($occ->ev_timezone ?: 'Europe/Moscow'));
                $startsAt = \Carbon\Carbon::parse($occ->starts_at, 'UTC')->setTimezone($tz);
                $endsAt   = $occ->duration_sec
                    ? $startsAt->copy()->addSeconds((int)$occ->duration_sec)
                    : null;

                // Уровень
                $dir = $occ->direction ?? 'classic';
                $lvMin = $dir === 'beach' ? $occ->beach_level_min : $occ->classic_level_min;
                $lvMax = $dir === 'beach' ? $occ->beach_level_max : $occ->classic_level_max;
                $levelScope = level_terminology_scope_for_region($occ->city_region ?? null);

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
                    'date_long'  => $startsAt->locale(app()->getLocale())->translatedFormat('d F'),
                    'time_range' => $startsAt->format('H:i') . ($endsAt ? '–' . $endsAt->format('H:i') : ''),
                    'direction'  => $dir,
                    'address'    => $address,
                    'slots_info' => $slotsInfo,
                    'organizer_id' => (int) $userId ?: null,
                    'level_min'  => $lvMin,
                    'level_max'  => $lvMax,
                    'level_scope' => $levelScope,
                    'price'      => $priceLabel,
                    'is_private' => (bool) $occ->is_private,
                    'extra'      => isset($models[$occ->occ_id]) ? $this->extras($models[$occ->occ_id]) : [],
                    'photo'      => $photoUrls[(int) $occ->event_id]
                        ?? $this->absoluteUrl('/img/' . ($dir === 'beach' ? 'beach.webp' : 'classic.webp')),
                    // Приватное мероприятие открывается только по токену (/e/{token}) —
                    // обычная ссылка events.show даёт 404 всем, кроме организатора.
                    'url'        => ($occ->is_private && $occ->public_token)
                        ? route('events.public', ['token' => $occ->public_token, 'occurrence' => $occ->occ_id])
                        : route('events.show', [
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

    /**
     * Блоки как на карточке /events: организатор, бейджи (подтип, пол, возраст, оплата, рейтинг), статус, погода.
     * Подписи — на языке запроса (виджет выставляет локаль до вызова), поэтому локаль входит в ключ кеша.
     */
    private function extras(\App\Models\EventOccurrence $occ): array
    {
        $event = $occ->event;
        if (!$event) {
            return [];
        }
        $gs  = $event->gameSettings;
        $dir = (string) ($event->direction ?? 'classic');

        // Организатор: имя/ник (email на публичной странице не показываем)
        $organizer = null;
        if ($org = $event->organizer) {
            $name = trim(($org->first_name ?? '') . ' ' . ($org->last_name ?? ''));
            $name = $name !== '' ? $name : trim((string) ($org->name ?? ''));
            $name = $name !== '' ? $name : (string) ($org->nickname ?? '');
            if ($name !== '') {
                $organizer = ['name' => $name, 'url' => url('/user/' . (int) $org->id)];
            }
        }

        $badges = [];
        $subtype = (string) ($gs?->subtype ?? '');
        if ((string) ($event->format ?? '') === 'master_class') {
            // у мастер-класса subtype — технический дефолт (2x2/4x2), показываем тип мероприятия
            $badges['subtype'] = __('events.fmt_master_class');
        } elseif ($subtype !== '') {
            $badges['subtype'] = volley_scheme_label($subtype, false, false);
            if ($subtype === '5x1_libero' || ($subtype === '5x1' && (string) ($gs?->libero_mode ?? '') === 'with_libero')) {
                $badges['libero'] = __('events.card_badge_libero');
            }
        }
        $gp = (string) ($gs?->gender_policy ?? '');
        if (in_array($gp, ['only_male', 'only_female', 'mixed_5050', 'mixed_limited'], true)) {
            $badges['gender'] = __('events.gender_' . ($gp === 'mixed_5050' ? '5050' : $gp));
        }
        $age = (string) ($event->age_policy ?? 'any');
        if ($age === 'adult') {
            $badges['age'] = __('events.card_age_adult');
        } elseif ($age === 'child') {
            $mn = $event->child_age_min;
            $mx = $event->child_age_max;
            $badges['age'] = (!is_null($mn) && !is_null($mx)) ? __('events.card_age_child_range', ['min' => (int) $mn, 'max' => (int) $mx])
                : (!is_null($mx) ? __('events.card_age_child_max', ['max' => (int) $mx]) : __('events.card_age_child'));
        }
        $pm = (string) ($event->payment_method ?? '');
        if (!empty($event->is_paid) && $pm !== '') {
            $badges['pay'] = $pm === 'cash' ? __('events.card_pay_cash') : __('events.card_pay_cashless');
        }
        if (!empty($event->collect_stats) && !empty($event->stats_rated)) {
            $badges['rated'] = __('events.card_badge_rated');
        }

        // Статус (как на карточке): идёт / регистрация открыта; «завершено» в ленте виджета не бывает (только будущие)
        $now = now('UTC');
        $start = $occ->starts_at ? \Illuminate\Support\Carbon::parse($occ->starts_at, 'UTC') : null;
        $end = ($start && $occ->duration_sec) ? $start->copy()->addSeconds((int) $occ->duration_sec) : null;
        $regStart = $occ->effectiveRegistrationStartsAt();
        $regEnd = $occ->effectiveRegistrationEndsAt();
        $status = null;
        if ($start && $now->gte($start) && !($end && $now->gte($end))) {
            $status = ['key' => 'live', 'label' => __('events.card_status_live')];
        } elseif (!empty($event->allow_registration) && $start && $now->lt($start)
            && !($regStart && $now->lt($regStart)) && !($regEnd && $now->gte($regEnd))) {
            $status = ['key' => 'open', 'label' => __('events.card_status_open')];
        }

        $w = app(WeatherService::class)->forOccurrence($occ);

        return [
            'organizer' => $organizer,
            'badges'    => $badges,
            'status'    => $status,
            'weather'   => $w ? ['icon' => $w['icon'], 'temp' => $w['temp'], 'pop' => (int) $w['pop']] : null,
            'free'      => empty($event->is_paid),
        ];
    }

    /** Первое фото каждого мероприятия (event_thumb), один запрос на весь список. */
    private function firstPhotoUrls($occurrences): array
    {
        $firstIds = [];
        foreach ($occurrences as $occ) {
            $ids = json_decode((string) ($occ->event_photos ?? '[]'), true);
            $first = is_array($ids) ? (int) (array_values(array_filter($ids))[0] ?? 0) : 0;
            if ($first > 0) {
                $firstIds[(int) $occ->event_id] = $first;
            }
        }
        if (!$firstIds) {
            return [];
        }

        $media = Media::whereIn('id', array_unique(array_values($firstIds)))->get()->keyBy('id');
        $out = [];
        foreach ($firstIds as $eventId => $mediaId) {
            if ($m = $media->get($mediaId)) {
                $out[$eventId] = $this->absoluteUrl($m->hasGeneratedConversion('event_thumb')
                    ? $m->getUrl('event_thumb')
                    : $m->getUrl());
            }
        }
        return $out;
    }

    private function absoluteUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
    }

    /** Демо-данные для предпросмотра, когда у организатора нет будущих мероприятий. */
    public function sampleEvents(): array
    {
        $mk = fn (string $title, string $dir, string $date, string $time, int $taken, int $max, ?string $price, ?int $lmin, ?int $lmax) => [
            'title' => $title, 'date_long' => $date, 'time_range' => $time, 'direction' => $dir,
            'address' => 'Спорткомплекс «Волна», Москва, ул. Примерная, 1',
            'slots_info' => ['taken' => $taken, 'max' => $max, 'free' => $max - $taken, 'unit' => 'players', 'reserve' => 0],
            'level_min' => $lmin, 'level_max' => $lmax, 'level_scope' => 'standard',
            'price' => $price, 'is_private' => false, 'url' => '#',
            'extra' => [
                'organizer' => ['name' => 'Иван Петров', 'url' => '#'],
                'badges' => ['subtype' => $dir === 'beach' ? '2×2' : '4-2', 'gender' => __('events.gender_5050'), 'pay' => $price ? __('events.card_pay_cash') : null],
                'status' => ['key' => 'open', 'label' => __('events.card_status_open')],
                'weather' => $dir === 'beach' ? ['icon' => '⛅', 'temp' => '+17°', 'pop' => 20] : null,
                'free' => $price === null,
            ],
            'photo' => $this->absoluteUrl('/img/' . ($dir === 'beach' ? 'beach.webp' : 'classic.webp')),
        ];

        return [
            $mk('Игра на песке', 'beach', '12 октября', '19:00–21:00', 8, 12, '500 ₽', 2, 4),
            $mk('Классика 4-2', 'classic', '14 октября', '20:00–22:00', 10, 12, null, null, null),
            $mk('Вечерняя тренировка', 'classic', '16 октября', '19:30–21:30', 3, 12, '400 ₽', 3, 5),
        ];
    }
}
