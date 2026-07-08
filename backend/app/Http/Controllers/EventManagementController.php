<?php

namespace App\Http\Controllers;

use App\Jobs\ExpandEventOccurrencesJob;
use App\Models\Event;
use App\Models\User;
use App\Models\EventOccurrence;
use App\Models\Location;
use App\Services\UserNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventManagementController extends Controller
{
    public function __construct(
        private UserNotificationService $userNotificationService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $this->ensureCanCreateEvents($user);

        $tab = (string) $request->query('tab', 'mine');

        if ($tab === 'templates') {
            return redirect()->route('events.create.event_management', ['tab' => 'archive']);
        }

        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
        $nowUtc = Carbon::now('UTC');

        $organizerFilter = 0;
        if ($role === 'admin') {
            $organizerFilter = max(0, (int) $request->query('organizer_id', 0));
        }

        $regsSub = null;
        if (Schema::hasTable('event_registrations')) {
            $regsSub = DB::table('event_registrations')
            ->select('event_id', DB::raw('COUNT(*)::int as active_regs'));
        
        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $regsSub->whereNull('cancelled_at');
        }
        
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $regsSub->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }
        
        if (Schema::hasColumn('event_registrations', 'status')) {
            $regsSub->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }

            $regsSub->groupBy('event_id');
        }

        $nextOccSub = null;
        if (Schema::hasTable('event_occurrences')) {
            $nextOccSub = DB::table('event_occurrences as eo')
                ->select('eo.event_id', DB::raw('MIN(eo.starts_at) as next_occurrence_starts_at'))
                ->where('eo.starts_at', '>', $nowUtc);

            if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                $nextOccSub->whereNull('eo.cancelled_at');
            }
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $nextOccSub->where(function ($w) {
                    $w->whereNull('eo.is_cancelled')->orWhere('eo.is_cancelled', false);
                });
            }

            $nextOccSub->groupBy('eo.event_id');
        }

        $q = Event::query()->with([
            'location' => function ($q) {
                $q->select('id', 'name', 'address', 'city_id')
                    ->with(['city:id,name,region']);
            },
            'organizer:id,name,first_name,last_name,role',
            'gameSettings:event_id,max_players',
        ]);

        if ($regsSub) {
            $q->leftJoinSub($regsSub, 'ar', function ($join) {
                $join->on('events.id', '=', 'ar.event_id');
            });
        }

        if ($nextOccSub) {
            $q->leftJoinSub($nextOccSub, 'no', function ($join) {
                $join->on('events.id', '=', 'no.event_id');
            });
        }

        $nowUtcStr = $nowUtc->toDateTimeString();
        $q->addSelect([
            'events.*',
            DB::raw($regsSub ? 'COALESCE(ar.active_regs, 0) as active_regs' : '0 as active_regs'),
            DB::raw($nextOccSub ? 'no.next_occurrence_starts_at as next_occurrence_starts_at' : 'NULL as next_occurrence_starts_at'),
            DB::raw($nextOccSub ? "(SELECT eo2.id FROM event_occurrences eo2 WHERE eo2.event_id = events.id AND eo2.starts_at > '{$nowUtcStr}' AND (eo2.is_cancelled IS NULL OR eo2.is_cancelled = false) AND eo2.cancelled_at IS NULL ORDER BY eo2.starts_at ASC LIMIT 1) as next_occurrence_id" : 'NULL as next_occurrence_id'),
        ]);

        if ($role === 'admin') {
            if ($organizerFilter > 0) {
                $q->where('events.organizer_id', $organizerFilter);
            }
        } elseif ($role === 'organizer') {
            $q->where('events.organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            $q->where('events.organizer_id', (int) $organizerIdForStaff);
        } else {
            $q->whereRaw('1=0');
        }

        if ($tab === 'mine') {
            if ($nextOccSub) {
                $q->whereNotNull('no.next_occurrence_starts_at');
                $q->orderBy('no.next_occurrence_starts_at', 'asc')
                    ->orderBy('events.id', 'desc');
            } else {
                $q->whereRaw('1=0');
            }
        } else {
            if ($nextOccSub) {
                $q->whereNull('no.next_occurrence_starts_at');
            }
            $q->orderByDesc('events.id');
        }

        $events = $q->paginate(20)->withQueryString();

        foreach ($events as $e) {
            $e->max_players = (int) ($e->gameSettings?->max_players ?? 0);
        }



$organizers = [];

if ($role === 'admin') {
    $organizers = User::query()
        ->whereIn('id', function ($q) {
            $q->select('organizer_id')
              ->from('events')
              ->whereNotNull('organizer_id');
        })
        ->orderBy('first_name')
        ->orderBy('last_name')
        ->get();
}

        return view('events.event_management', [
            'tab' => $tab,
            'events' => $events,
            'organizers' => $organizers,
            'organizerFilter' => $organizerFilter,
        ]);
    }
   
    public function edit(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);

        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);

        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) abort(403);
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) abort(403);

        $event->load(['location.city', 'gameSettings', 'tournamentSetting']);

        // Информация о связанных лиге/сезоне/дивизионе для tournament-формата
        $seasonInfo = null;
        if ($event->season_id) {
            $season = \App\Models\TournamentSeason::with('league')->find($event->season_id);
            if ($season) {
                $division = DB::table('tournament_season_events')
                    ->join('tournament_leagues', 'tournament_leagues.id', '=', 'tournament_season_events.league_id')
                    ->where('tournament_season_events.event_id', $event->id)
                    ->select('tournament_leagues.id', 'tournament_leagues.name')
                    ->first();
                $seasonInfo = [
                    'league_name'   => $season->league?->name,
                    'season_name'   => $season->name,
                    'division_name' => $division?->name,
                    'league_url'    => $season->league
                        ? '/l/' . $season->league->slug
                        : null,
                    'season_url'    => $season->league && $season->slug
                        ? '/l/' . $season->league->slug . '/s/' . $season->slug
                        : null,
                ];
            }
        }

        $activeRegs = 0;
        if (Schema::hasTable('event_registrations')) {
            $r = DB::table('event_registrations')->where('event_id', (int) $event->id);

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $r->whereNull('cancelled_at');
        }
        
        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $r->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }
        
        if (Schema::hasColumn('event_registrations', 'status')) {
            $r->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }
            $activeRegs = (int) $r->count();
        }

        $currentCity = $event->location?->city;
        $currentCityId = $currentCity?->id;

        $locations = Location::query()
            ->with(['media', 'city'])
            ->orderBy('name')
            ->when($currentCityId, fn ($q) => $q->where('city_id', $currentCityId))
            ->get();

        // Каналы для анонсов: список каналов организатора + текущие подключения
        $userChannels = \App\Models\UserNotificationChannel::query()
            ->verified()
            ->where('user_id', (int) $event->organizer_id)
            ->orderBy('platform')
            ->orderBy('title')
            ->get();

        $eventChannels = \App\Models\EventNotificationChannel::query()
            ->where('event_id', (int) $event->id)
            ->get();
        $selectedChannelIds = $eventChannels->pluck('channel_id')->map(fn ($v) => (string) $v)->all();
        $savedThreadIds = $eventChannels->pluck('message_thread_id', 'channel_id')->all();
        $first = $eventChannels->first();
        $channelSettings = [
            'silent'                  => (bool) ($first->silent ?? false),
            'update_message'          => (bool) ($first->update_message ?? true),
            'include_image'           => (bool) ($first->include_image ?? true),
            'include_registered_list' => (bool) ($first->include_registered_list ?? true),
        ];

        // Автоопределение сезона по дате события + дивизионы для выбора
        $detectedSeason   = null;
        $detectedLeague   = null;
        $seasonDivisions  = collect();
        $currentDivisionId = null;
        if (($event->format ?? '') === 'tournament') {
            $eventDate = $event->starts_at
                ? \Carbon\Carbon::parse($event->starts_at)
                : now();

            $leagues = \App\Models\League::where('organizer_id', $event->organizer_id)
                ->orderBy('name')->get();

            foreach ($leagues as $lg) {
                $season = \App\Models\TournamentSeason::where('league_id', $lg->id)
                    ->where(function ($q) use ($eventDate) {
                        $q->whereNull('starts_at')->orWhere('starts_at', '<=', $eventDate);
                    })
                    ->where(function ($q) use ($eventDate) {
                        $q->whereNull('ends_at')->orWhere('ends_at', '>=', $eventDate);
                    })
                    ->with('leagues')
                    ->orderByDesc('starts_at')
                    ->first();

                if ($season) {
                    $detectedSeason  = $season;
                    $detectedLeague  = $lg;
                    $seasonDivisions = $season->leagues;
                    break;
                }
            }

            $currentDivisionId = DB::table('tournament_season_events')
                ->where('event_id', $event->id)
                ->value('league_id');
        }

        $orgPaySettings = \App\Models\PaymentSetting::where('organizer_id', (int) $event->organizer_id)->first();

        return view('events.event_management_edit', [
            'event'            => $event,
            'activeRegs'       => (int) $activeRegs,
            'locations'        => $locations,
            'currentCity'      => $currentCity,
            'userChannels'     => $userChannels,
            'selectedChannelIds' => $selectedChannelIds,
            'savedThreadIds'   => $savedThreadIds,
            'channelSettings'  => $channelSettings,
            'seasonInfo'       => $seasonInfo,
            'detectedSeason'   => $detectedSeason,
            'detectedLeague'   => $detectedLeague,
            'seasonDivisions'  => $seasonDivisions,
            'currentDivisionId' => $currentDivisionId,
            'orgPaySettings'   => $orgPaySettings,
        ]);
    }

    public function updateSeason(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $this->ensureCanCreateEvents($user);
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) abort(403);
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) abort(403);

        $request->validate([
            'division_id' => ['required', 'integer', 'exists:tournament_leagues,id'],
        ]);

        $divisionId = (int) $request->input('division_id');

        // Находим сезон по дивизиону
        $division = \App\Models\TournamentLeague::findOrFail($divisionId);
        $seasonId = $division->season_id;

        // Обновляем season_id на событии
        $event->season_id = $seasonId;
        $event->save();

        // Обновляем tournament_season_events для всех occurrence этого события
        if ($seasonId && $divisionId) {
            $occurrences = DB::table('event_occurrences')
                ->where('event_id', $event->id)
                ->pluck('id');

            // Удаляем старые записи
            DB::table('tournament_season_events')->where('event_id', $event->id)->delete();

            // Определяем round_number — берём максимальный существующий в этом сезоне + 1
            $maxRound = DB::table('tournament_season_events')
                ->where('season_id', $seasonId)
                ->max('round_number') ?? 0;

            $round = $maxRound + 1;
            foreach ($occurrences as $occId) {
                DB::table('tournament_season_events')->insert([
                    'season_id'     => $seasonId,
                    'league_id'     => $divisionId,
                    'event_id'      => $event->id,
                    'occurrence_id' => $occId,
                    'round_number'  => $round,
                    'status'        => 'pending',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
                $round++;
            }
        }

        return back()->with('success', 'Привязка к дивизиону обновлена.');
    }
    public function occurrences(\App\Models\Event $event)
    {
        $event->load([
            'location.city',
            'organizer',
        ]);

        $allOccurrences = $event->occurrences()
            ->withCount([
                'registrations as active_regs' => fn ($q) => $q->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)'),
            ])
            ->orderBy('starts_at')
            ->get();

        $now = now('UTC');
        $upcoming = $allOccurrences->filter(fn ($o) => $o->starts_at && \Carbon\Carbon::parse($o->starts_at, 'UTC')->gte($now))->values();
        $archived = $allOccurrences->filter(fn ($o) => !$o->starts_at || \Carbon\Carbon::parse($o->starts_at, 'UTC')->lt($now))->sortByDesc('starts_at')->values();

        $nextOccurrence = $upcoming->first();

        return view('events.event_management_occurrences', [
            'event'           => $event,
            'occurrences'     => $upcoming,
            'archived'        => $archived,
            'nextOccurrence'  => $nextOccurrence,
        ]);
    }
    public function toggleBotEvent(Request $request, Event $event): \Illuminate\Http\JsonResponse
    {
        $newValue = !(bool)($event->bot_assistant_enabled ?? false);
        $event->bot_assistant_enabled = $newValue;
        $event->save();

        return response()->json(['enabled' => $newValue]);
    }

    public function toggleBotOccurrence(Request $request, Event $event, \App\Models\EventOccurrence $occurrence): \Illuminate\Http\JsonResponse
    {
        $occOverride = $occurrence->getRawOriginal('bot_assistant_enabled');
        $effective = $occOverride === null
            ? (bool) ($event->bot_assistant_enabled ?? false)
            : (bool) $occOverride;

        $occurrence->bot_assistant_enabled = !$effective;
        $occurrence->save();

        return response()->json(['enabled' => !$effective]);
    }

    public function resetOccurrence(Request $request, Event $event, \App\Models\EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $role = (string)($user->role ?? 'user');
        if ($role === 'organizer' && (int)$event->organizer_id !== (int)$user->id) abort(403);
        if ($role === 'staff' && (int)$event->organizer_id !== (int)$this->resolveOrganizerIdForCreator($user)) abort(403);

        $nowUtc  = \Carbon\Carbon::now('UTC');
        $occStart = \Carbon\Carbon::parse($occurrence->starts_at, 'UTC');

        $regStarts = null;
        $regEnds   = null;
        $cancelTil = null;

        if ($event->starts_at && $event->registration_starts_at && $event->registration_ends_at) {
            $evStart  = \Carbon\Carbon::parse($event->starts_at, 'UTC');
            $secBefore = max(0, $evStart->diffInSeconds(\Carbon\Carbon::parse($event->registration_starts_at, 'UTC'), false) * -1);
            $endMin    = max(1, (int)round($evStart->diffInMinutes(\Carbon\Carbon::parse($event->registration_ends_at, 'UTC'), false) * -1));
            $regStarts = $occStart->copy()->subSeconds($secBefore);
            $regEnds   = $occStart->copy()->subMinutes($endMin);
        }

        if ($event->starts_at && $event->cancel_self_until) {
            $evStart   = \Carbon\Carbon::parse($event->starts_at, 'UTC');
            $cancelMin = max(1, (int)round($evStart->diffInMinutes(\Carbon\Carbon::parse($event->cancel_self_until, 'UTC'), false) * -1));
            $cancelTil = $occStart->copy()->subMinutes($cancelMin);
            if ($cancelTil->lte($nowUtc)) $cancelTil = null;
        }

        $occurrence->title               = null;
        $occurrence->description_html    = null;
        $occurrence->location_id         = null;
        $occurrence->allow_registration  = null;
        $occurrence->max_players         = null;
        $occurrence->duration_sec        = $event->duration_sec;
        $occurrence->classic_level_min   = null;
        $occurrence->classic_level_max   = null;
        $occurrence->beach_level_min     = null;
        $occurrence->beach_level_max     = null;
        $occurrence->show_participants   = $event->show_participants ?? true;
        $occurrence->age_policy          = null;
        $occurrence->child_age_min       = null;
        $occurrence->child_age_max       = null;
        $occurrence->is_paid             = null;
        $occurrence->price_minor         = null;
        $occurrence->price_currency      = null;
        $occurrence->price_text          = null;
        $occurrence->payment_method      = null;
        $occurrence->payment_link        = null;
        $occurrence->refund_hours_full   = null;
        $occurrence->refund_hours_partial = null;
        $occurrence->refund_partial_pct  = null;
        $occurrence->trainer_user_id     = null;
        $occurrence->registration_starts_at = $regStarts;
        $occurrence->registration_ends_at   = $regEnds;
        $occurrence->cancel_self_until      = $cancelTil;
        $occurrence->is_individually_edited = false;
        $occurrence->save();

        \App\Models\EventOccurrenceGameSetting::where('occurrence_id', $occurrence->id)->delete();

        return redirect()
            ->route('events.occurrences.edit', [$event, $occurrence])
            ->with('status', 'Повтор сброшен до значений серии.');
    }

    public function destroyOccurrence(\App\Models\EventOccurrence $occurrence, \Illuminate\Http\Request $request)
    {
        $deleteMode = (string)$request->input('delete_mode', 'single');

        if ($deleteMode === 'force') {
            $occurrence->delete();
            return back()->with('status', 'Повтор удалён навсегда.');
        }

        $occurrence->cancelled_at = now();
        $occurrence->is_cancelled = true;
        $occurrence->save();

        return back()->with('status', 'Повтор отменён.');
    }

    public function editOccurrence(Request $request, Event $event, \App\Models\EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $role = (string) ($user->role ?? 'user');
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
        if ($role !== 'admin' && $role !== 'organizer') {
            abort(403);
        }
        if ((int) $occurrence->event_id !== (int) $event->id) {
            abort(404);
        }

        // Eager-load настройки игры с обеих сторон
        $event->loadMissing('gameSettings');
        $occurrence->loadMissing('gameSettingsOverride');

        $tz = $event->timezone ?: 'UTC';
        $startsLocal = $occurrence->starts_at
            ? \Carbon\Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz)->format('Y-m-d\TH:i')
            : '';

        $locations = \App\Models\Location::orderBy('name')
            ->get(['id', 'name', 'address', 'city_id']);

        // Доступные подтипы для direction
        $direction = $event->direction ?: 'classic';
        $subtypes = array_keys(config("volleyball.{$direction}", []));

        // Эффективные игровые настройки (override ?? event ?? defaults)
        $gs = $occurrence->effectiveGameSettings();

        // Эффективные тренеры: override или, если override пуст — от event->trainers
        $occurrence->loadMissing('trainers');
        $event->loadMissing('trainers');
        $trainerInherited = $occurrence->trainers->isEmpty();
        $trainers = $trainerInherited ? $event->trainers : $occurrence->trainers;

        // Effective-значения (override ?? event) для blade-полей:
        $eff = function ($occVal, $eventVal) {
            return ($occVal === null || $occVal === '') ? $eventVal : $occVal;
        };

        $title          = $eff($occurrence->title, $event->title);
        $descriptionHtml= $eff($occurrence->description_html, $event->description_html);

        $classicLevelMin= $eff($occurrence->classic_level_min, $event->classic_level_min);
        $classicLevelMax= $eff($occurrence->classic_level_max, $event->classic_level_max);
        $beachLevelMin  = $eff($occurrence->beach_level_min, $event->beach_level_min);
        $beachLevelMax  = $eff($occurrence->beach_level_max, $event->beach_level_max);
        $agePolicy      = $eff($occurrence->age_policy, $event->age_policy ?? 'adult');
        $childAgeMin    = $eff($occurrence->child_age_min, $event->child_age_min);
        $childAgeMax    = $eff($occurrence->child_age_max, $event->child_age_max);

        $isPaid         = (bool) $eff($occurrence->is_paid, $event->is_paid ?? false);
        $priceMinor     = $eff($occurrence->price_minor, $event->price_minor);
        $priceRub       = $priceMinor !== null ? number_format((int) $priceMinor / 100, 0, '.', '') : '';

        // Gender / subtype (из effective game settings $gs)
        $subtypeVal          = $gs->subtype ?? null;
        $genderPolicyVal     = $gs->gender_policy ?? 'mixed_open';
        $genderLimitedSideVal= $gs->gender_limited_side ?? null;
        $genderLimitedMaxVal = $gs->gender_limited_max ?? null;

        $locationId    = $eff($occurrence->location_id, $event->location_id);
        $minPlayersVal = $gs->min_players ?? null;

        return view('events.occurrence_edit', compact(
            'event', 'occurrence', 'startsLocal', 'tz', 'locations',
            'subtypes', 'gs', 'trainers', 'trainerInherited',
            'title', 'descriptionHtml',
            'classicLevelMin', 'classicLevelMax', 'beachLevelMin', 'beachLevelMax',
            'agePolicy', 'childAgeMin', 'childAgeMax',
            'isPaid', 'priceRub', 'locationId', 'minPlayersVal',
            'subtypeVal', 'genderPolicyVal', 'genderLimitedSideVal', 'genderLimitedMaxVal'
        ));
    }

    
    public function updateOccurrence(Request $request, Event $event, \App\Models\EventOccurrence $occurrence)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');

        $role = (string) ($user->role ?? 'user');
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
        if ($role !== 'admin' && $role !== 'organizer') {
            abort(403);
        }
        if ((int) $occurrence->event_id !== (int) $event->id) {
            abort(404);
        }

        $data = $request->validate([
            'title'                            => 'nullable|string|max:255',
            'description_html'                 => 'nullable|string|max:65535',

            'starts_at_local'                  => 'required|date',
            'duration_hours'                   => 'nullable|integer|min:0|max:23',
            'duration_minutes'                 => 'nullable|integer|min:0|max:59',
            'location_id'                      => 'nullable|integer|exists:locations,id',
            'show_participants'                => 'sometimes|boolean',

            'allow_registration'               => 'sometimes|boolean',
            'reg_starts_days_before'           => 'nullable|integer|min:0|max:365',
            'reg_starts_hours_before'          => 'nullable|integer|min:0|max:23',
            'reg_ends_minutes_before'          => 'nullable|integer|min:0|max:10080',
            'cancel_lock_minutes_before'          => 'nullable|integer|min:0|max:10080',
            'cancel_lock_waitlist_minutes_before' => 'nullable|integer|min:0|max:10080',
            'remind_registration_enabled'      => 'sometimes|boolean',
            'remind_registration_minutes_before' => 'nullable|integer|min:0|max:10080',

            'classic_level_min'                => 'nullable|integer|min:1|max:10',
            'classic_level_max'                => 'nullable|integer|min:1|max:10',
            'beach_level_min'                  => 'nullable|integer|min:1|max:10',
            'beach_level_max'                  => 'nullable|integer|min:1|max:10',
            'age_policy'                       => 'nullable|string|in:adult,child,any',
            'child_age_min'                    => 'nullable|integer|min:3|max:18',
            'child_age_max'                    => 'nullable|integer|min:3|max:18',

            // ===== Игровая схема =====
            'subtype'                          => 'nullable|string|max:10',
            'teams_count'                      => 'nullable|integer|min:2|max:16',
            'min_players'                      => 'nullable|integer|min:0|max:100',

            // ===== Гендер =====
            'gender_policy'                    => 'nullable|string|in:mixed_open,mixed_5050,only_male,only_female,mixed_limited',
            'girls_max'                        => 'nullable|integer|min:0|max:20',
            'gender_limited_max'               => 'nullable|integer|min:0|max:10',
            'gender_limited_side'              => 'nullable|string|in:female,male',
            'gender_limited_positions'         => 'nullable|array',
            'gender_limited_positions.*'       => 'nullable|string|max:32',

            // ===== Оплата =====
            'is_paid'                          => 'sometimes|boolean',
            'price_rub'                        => 'nullable|numeric|min:0',
            'price_currency'                   => 'nullable|string|max:3',
            'price_text'                       => 'nullable|string|max:255',
            'payment_method'                   => 'nullable|string|max:30',
            'payment_link'                     => 'nullable|string|max:500',

            'refund_hours_full'                => 'nullable|integer|min:0|max:720',
            'refund_hours_partial'             => 'nullable|integer|min:0|max:720',
            'refund_partial_pct'               => 'nullable|integer|min:0|max:100',

            'trainer_user_id'                  => 'nullable|integer|exists:users,id',
            'trainer_user_ids'                 => 'nullable|array',
            'trainer_user_ids.*'               => 'integer|exists:users,id',
            'requires_personal_data'           => 'sometimes|boolean',
        ]);


        $tz = $event->timezone ?: 'UTC';
        $startsUtc = \Carbon\Carbon::parse($data['starts_at_local'], $tz)->utc();

        $durationSec = ((int)($data['duration_hours'] ?? 0)) * 3600
                     + ((int)($data['duration_minutes'] ?? 0)) * 60;
        if ($durationSec <= 0) {
            $durationSec = $occurrence->duration_sec ?: ($event->duration_sec ?: 7200);
        }

        // Хелпер: override-логика (NULL если совпадает с event, значение если отличается)
        $override = function ($value, $eventValue) {
            if ($value === null || $value === '') return null;
            if (is_bool($eventValue) || is_bool($value)) {
                return ((bool)$value) === ((bool)$eventValue) ? null : (bool)$value;
            }
            if (is_numeric($eventValue) && is_numeric($value)) {
                return ((float)$value) == ((float)$eventValue) ? null : $value;
            }
            return (string)$value === (string)$eventValue ? null : $value;
        };

        // ===== Основные поля =====
        $occurrence->starts_at = $startsUtc;
        $occurrence->duration_sec = $durationSec;
        $occurrence->location_id = $data['location_id'] ?? $event->location_id;

        // ===== Название и описание (override) =====
        $occurrence->title = $override($data['title'] ?? null, $event->title);
        $occurrence->description_html = $override($data['description_html'] ?? null, $event->description_html);

        // ===== Показ участников (override) =====
        // Только если поле реально пришло в запросе (sometimes|boolean)
        if (array_key_exists('show_participants', $data)) {
            $occurrence->show_participants = $override(
                (bool) $data['show_participants'],
                (bool) ($event->show_participants ?? true)
            );
        }

        // ===== Уровни и возраст (override) =====
        $occurrence->classic_level_min = $override($data['classic_level_min'] ?? null, $event->classic_level_min);
        $occurrence->classic_level_max = $override($data['classic_level_max'] ?? null, $event->classic_level_max);
        $occurrence->beach_level_min = $override($data['beach_level_min'] ?? null, $event->beach_level_min);
        $occurrence->beach_level_max = $override($data['beach_level_max'] ?? null, $event->beach_level_max);
        $occurrence->age_policy = $override($data['age_policy'] ?? null, $event->age_policy ?? 'adult');
        $occurrence->child_age_min = $override($data['child_age_min'] ?? null, $event->child_age_min ?? null);
        $occurrence->child_age_max = $override($data['child_age_max'] ?? null, $event->child_age_max ?? null);

        // ===== Оплата (override) =====
        $isPaidInput = (bool) ($data['is_paid'] ?? false);
        $occurrence->is_paid = $override($isPaidInput, (bool) ($event->is_paid ?? false));

        $priceMinorInput = isset($data['price_rub']) && $data['price_rub'] !== null && $data['price_rub'] !== ''
            ? (int) round(((float) $data['price_rub']) * 100)
            : null;
        $occurrence->price_minor = $override($priceMinorInput, $event->price_minor ?? null);
        $occurrence->price_currency = $override($data['price_currency'] ?? null, $event->price_currency ?? 'RUB');
        $occurrence->price_text = $override($data['price_text'] ?? null, $event->price_text ?? null);
        $occurrence->payment_method = $override($data['payment_method'] ?? null, $event->payment_method ?? null);
        $occurrence->payment_link = $override($data['payment_link'] ?? null, $event->payment_link ?? null);

        // ===== Возврат (override) =====
        $occurrence->refund_hours_full = $override($data['refund_hours_full'] ?? null, $event->refund_hours_full ?? null);
        $occurrence->refund_hours_partial = $override($data['refund_hours_partial'] ?? null, $event->refund_hours_partial ?? null);
        $occurrence->refund_partial_pct = $override($data['refund_partial_pct'] ?? null, $event->refund_partial_pct ?? null);

        // ===== Тренер (override) =====
        // Тренеры-override через pivot event_occurrence_trainers
        $trainerIds = $data['trainer_user_ids'] ?? [];
        if (is_string($trainerIds)) $trainerIds = [$trainerIds];
        if (!is_array($trainerIds)) $trainerIds = [];
        $trainerIds = array_values(array_unique(array_map('intval', $trainerIds)));
        $trainerIds = array_values(array_filter($trainerIds, fn($id) => $id > 0));

        // Sync override. Пустой массив = override "нет тренеров" (явно).
        // Чтобы вернуться к наследованию — нужна отдельная кнопка (TODO).
        // Сейчас: если массив отличается от event->trainers — пишем override,
        // иначе очищаем override (наследуем).
        $eventTrainerIds = $event->trainers()->pluck('users.id')->map(fn($v) => (int)$v)->sort()->values()->all();
        $submitted = collect($trainerIds)->sort()->values()->all();

        if ($submitted === $eventTrainerIds) {
            // Совпадает с серией → наследуем (чистим override)
            $occurrence->trainers()->sync([]);
        } else {
            $occurrence->trainers()->sync($trainerIds);
        }

        // Legacy single trainer_user_id — первый из эффективного списка
        $effectiveFirst = !empty($trainerIds)
            ? $trainerIds[0]
            : ($eventTrainerIds[0] ?? null);
        $occurrence->trainer_user_id = $effectiveFirst;

        // ===== Окно регистрации (override) =====
        if (array_key_exists('allow_registration', $data)) {
            $occurrence->allow_registration = $override(
                (bool) $data['allow_registration'],
                (bool) ($event->allow_registration ?? false)
            );
        }

        if (isset($data['reg_starts_days_before'])) {
            $regStartsDaysBefore = (int) $data['reg_starts_days_before'];
            $regStartsHoursBefore = (int) ($data['reg_starts_hours_before'] ?? 0);
            $occurrence->registration_starts_at = $startsUtc->copy()->subDays($regStartsDaysBefore)->subHours($regStartsHoursBefore);
        }

        if (isset($data['reg_ends_minutes_before'])) {
            $occurrence->registration_ends_at = $startsUtc->copy()->subMinutes((int) $data['reg_ends_minutes_before']);
        }

        if (isset($data['cancel_lock_minutes_before'])) {
            $occurrence->cancel_self_until = $startsUtc->copy()->subMinutes((int) $data['cancel_lock_minutes_before']);
        }

        if (isset($data['cancel_lock_waitlist_minutes_before'])) {
            $wlMin = (int) $data['cancel_lock_waitlist_minutes_before'];
            $occurrence->cancel_self_until_waitlist = $wlMin > 0
                ? $startsUtc->copy()->subMinutes($wlMin)
                : null;
        }

        $occurrence->is_individually_edited = true;
        $occurrence->save();

        // =============================================================
        // ===== ИГРОВЫЕ НАСТРОЙКИ (event_occurrence_game_settings) =====
        // =============================================================
        $eventGs = $event->gameSettings;  // текущие настройки серии

        // gender_limited_positions: сравниваем как отсортированные массивы
        $glpSubmitted = isset($data['gender_limited_positions']) && is_array($data['gender_limited_positions'])
            ? array_values(array_unique(array_filter($data['gender_limited_positions'])))
            : null;
        $glpEvent = $eventGs?->gender_limited_positions ?? null;
        if (is_string($glpEvent)) $glpEvent = json_decode($glpEvent, true) ?? [];
        $glpOverride = null;
        if ($glpSubmitted !== null) {
            $a = $glpSubmitted; sort($a);
            $b = is_array($glpEvent) ? $glpEvent : []; sort($b);
            $glpOverride = ($a === $b) ? null : $glpSubmitted;
        }

        $gsOverride = [
            'subtype'                  => $override($data['subtype'] ?? null, $eventGs?->subtype),
            'teams_count'              => $override(isset($data['teams_count']) ? (int)$data['teams_count'] : null, $eventGs?->teams_count),
            'min_players'              => $override(isset($data['min_players']) && $data['min_players'] !== '' ? (int)$data['min_players'] : null, $eventGs?->min_players),
            'gender_policy'            => $override($data['gender_policy'] ?? null, $eventGs?->gender_policy),
            'girls_max'                => $override(isset($data['girls_max']) && $data['girls_max'] !== '' ? (int)$data['girls_max'] : null, $eventGs?->girls_max),
            'gender_limited_max'       => $override(isset($data['gender_limited_max']) && $data['gender_limited_max'] !== '' ? (int)$data['gender_limited_max'] : null, $eventGs?->gender_limited_max),
            'gender_limited_side'      => $override($data['gender_limited_side'] ?? null, $eventGs?->gender_limited_side),
            'gender_limited_positions' => $glpOverride,
        ];

        // Если все override-значения NULL — удаляем запись override (нет смысла держать пустую)
        $hasAnyOverride = collect($gsOverride)->contains(fn($v) => $v !== null);

        if ($hasAnyOverride) {
            \App\Models\EventOccurrenceGameSetting::updateOrCreate(
                ['occurrence_id' => $occurrence->id],
                $gsOverride
            );
        } else {
            \App\Models\EventOccurrenceGameSetting::where('occurrence_id', $occurrence->id)->delete();
        }

        return redirect()
            ->to(route('events.show', $event) . '?occurrence=' . $occurrence->id)
            ->with('status', 'Изменения сохранены.');
    }

    
    public function update(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
    
        $this->ensureCanCreateEvents($user);
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
    
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) {
            abort(403);
        }
    
        // Допустимые методы оплаты: настроенные у организатора + текущий сохранённый (чтобы не сломать редактирование)
        $payMethodAllowed = \App\Models\PaymentSetting::availableMethodsFor((int) $event->organizer_id);
        if ($event->payment_method && !in_array($event->payment_method, $payMethodAllowed)) {
            $payMethodAllowed[] = $event->payment_method;
        }

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'direction'   => ['nullable', 'string', 'in:classic,beach'],
            'format'      => ['nullable', 'string', 'max:32'],
            'starts_at'   => ['required', 'date'],
            'timezone'    => ['required', 'string', 'max:64'],
            'location_id' => ['required', 'integer'],
            'duration_sec'     => ['nullable', 'integer', 'min:0'],
            'duration_hours'   => ['nullable', 'integer', 'min:0', 'max:23'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
            'allow_registration'       => ['sometimes', 'boolean'],
            'classic_level_min'        => ['nullable', 'integer', 'min:0', 'max:10'],
            'classic_level_max'        => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_min'          => ['nullable', 'integer', 'min:0', 'max:10'],
            'beach_level_max'          => ['nullable', 'integer', 'min:0', 'max:10'],
            'age_policy'               => ['nullable', 'string', 'in:adult,child,any'],
            'reg_starts_days_before'   => ['nullable', 'integer', 'min:0', 'max:365'],
            'reg_starts_hours_before'  => ['nullable', 'integer', 'min:0', 'max:23'],
            'reg_ends_minutes_before'  => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cancel_lock_minutes_before'          => ['nullable', 'integer', 'min:0', 'max:10080'],
            'cancel_lock_waitlist_minutes_before' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'game_max_players'             => ['nullable', 'integer', 'min:0'],
            'game_min_players'             => ['nullable', 'integer', 'min:0'],
            'game_reserve_players_max'     => ['nullable', 'integer', 'min:0', 'max:20'],
            'game_subtype'             => ['nullable', 'string', 'max:32'],
            'game_libero_mode'         => ['nullable', 'string', 'max:32'],
            'game_gender_policy'       => ['nullable', 'string', 'max:64'],
            'game_gender_limited_side' => ['nullable', 'string', 'max:16'],
            'game_gender_limited_max'  => ['nullable', 'integer', 'min:0'],
            'game_gender_limited_positions'   => ['nullable', 'array'],
            'game_gender_limited_positions.*' => ['string', 'max:32'],
            'game_gender_limited_reg_starts_days_before' => ['nullable', 'integer', 'min:0', 'max:365'],
            'game_allow_girls'         => ['nullable', 'boolean'],
            'game_girls_max'           => ['nullable', 'integer', 'min:0'],
            'is_private'               => ['sometimes', 'boolean'],
            'is_paid'                  => ['sometimes', 'boolean'],
            'price_amount'             => ['nullable', 'numeric', 'min:0', 'max:500000'],
            'price_currency'           => ['nullable', 'string', 'max:3'],
            'payment_method'           => ['nullable', 'string', \Illuminate\Validation\Rule::in($payMethodAllowed)],
            'payment_link'             => ['nullable', 'string', 'max:500'],
            'tournament_payment_mode'  => ['nullable', 'string', 'in:team,per_player'],
            'teams_count'              => ['nullable', 'integer', 'min:2', 'max:200'],
            'show_participants'        => ['sometimes', 'boolean'],
            'remind_registration_enabled'         => ['sometimes', 'boolean'],
            'remind_registration_minutes_before'  => ['nullable', 'integer', 'min:0'],
            'description_html'         => ['nullable', 'string'],
            'event_photos'             => ['nullable', 'string'],
            'requires_personal_data'     => ['sometimes', 'boolean'],
            'bot_assistant_enabled'      => ['sometimes', 'boolean'],
            'bot_assistant_threshold'    => ['sometimes', 'integer', 'min:5', 'max:30'],
            'bot_assistant_max_fill_pct' => ['sometimes', 'integer', 'min:10', 'max:60'],
            'channels'                   => ['sometimes', 'array'],
            'channels.*'                 => ['integer', 'min:1'],
            'channel_silent'             => ['sometimes', 'boolean'],
            'channel_update_message'     => ['sometimes', 'boolean'],
            'channel_include_image'      => ['sometimes', 'boolean'],
            'channel_include_registered' => ['sometimes', 'boolean'],
            'channel_use_private_link'   => ['sometimes', 'boolean'],

            // Повторение мероприятия
            'is_recurring'          => ['sometimes', 'boolean'],
            'recurrence_type'       => ['nullable', 'string', 'in:daily,weekly,monthly'],
            'recurrence_weekdays'   => ['nullable', 'array'],
            'recurrence_weekdays.*' => ['integer', 'between:1,7'],
            'recurrence_end_type'   => ['nullable', 'string', 'in:none,until,count'],
            'recurrence_end_until'  => ['nullable', 'string', 'max:20'],
            'recurrence_end_count'  => ['nullable', 'integer', 'min:1', 'max:9999'],
            'recurrence_interval'   => ['nullable', 'integer', 'min:1', 'max:365'],

            // Турнирные настройки (только при format=tournament)
            'tournament_game_scheme'              => ['nullable', 'string', 'max:16'],
            'tournament_team_size_min'            => ['nullable', 'integer', 'min:1', 'max:20'],
            'tournament_reserve_players_max'      => ['nullable', 'integer', 'min:0', 'max:20'],
            'tournament_application_mode'         => ['nullable', 'string', 'in:auto,manual'],
            'tournament_captain_confirms_members' => ['sometimes', 'boolean'],
            'tournament_auto_submit_when_ready'   => ['sometimes', 'boolean'],
            'tournament_allow_incomplete_application' => ['sometimes', 'boolean'],
            'tournament_individual_reg'           => ['sometimes', 'boolean'],
            'timeline_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        // Переключение режима регистрации турнира (командная <-> индивидуальная):
        // если уже есть команды дальше стадии draft, менять режим нельзя — данные потеряются.
        if (($event->format ?? 'game') === 'tournament') {
            $wasIndividual = $event->registration_mode === 'tournament_individual';
            $willBeIndividual = (bool) ($data['tournament_individual_reg'] ?? false);

            if ($wasIndividual !== $willBeIndividual) {
                $committedTeams = \App\Models\EventTeam::where('event_id', $event->id)
                    ->whereNotIn('status', ['draft', 'withdrawn', 'rejected'])
                    ->count();

                if ($committedTeams > 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'tournament_individual_reg' => 'Нельзя сменить режим записи — есть команды, прошедшие дальше черновика (' . $committedTeams . '). Сначала расформируйте или переведите их в резерв.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($event, $data, $user) {
            $tz = (string) $data['timezone'];
            $startsUtc = Carbon::parse($data['starts_at'], $tz)->utc();
            // duration: приоритет у duration_sec (вычислен JS), fallback hours+min
            if (!empty($data['duration_sec'])) {
                $durationSec = (int) $data['duration_sec'];
            } elseif (!empty($data['duration_hours']) || !empty($data['duration_minutes'])) {
                $durationSec = ((int)($data['duration_hours'] ?? 0)) * 3600
                             + ((int)($data['duration_minutes'] ?? 0)) * 60;
            } else {
                $durationSec = null;
            }
    
            $allowReg = (bool) ($data['allow_registration'] ?? false);
            $daysBefore = (int) ($data['reg_starts_days_before'] ?? 3);
            $hoursBefore = (int) ($data['reg_starts_hours_before'] ?? 0);
            $endsMinBefore = (int) ($data['reg_ends_minutes_before'] ?? 15);
            $cancelMinBefore = (int) ($data['cancel_lock_minutes_before'] ?? 60);
            $cancelWaitlistMinBefore = isset($data['cancel_lock_waitlist_minutes_before']) && (int)$data['cancel_lock_waitlist_minutes_before'] > 0
                ? (int) $data['cancel_lock_waitlist_minutes_before']
                : null;
    
            $event->title = $data['title'];
            $event->timezone = $tz;
            $event->location_id = (int) $data['location_id'];

            // Цвет в таймлайне — только владелец локации (club manager), проверяем на сервере
            if (array_key_exists('timeline_color', $data)) {
                $newLocation = \App\Models\Location::find((int) $data['location_id']);
                if (
                    !empty($data['timeline_color'])
                    && $user->is_club_manager
                    && $newLocation
                    && (int) $newLocation->owner_id === (int) $user->id
                ) {
                    $event->timeline_color = $data['timeline_color'];
                } else {
                    $event->timeline_color = null;
                }
            }

            $event->starts_at = $startsUtc;
            $event->duration_sec = $durationSec;
            $event->allow_registration = $allowReg;
            $event->classic_level_min = $data['classic_level_min'] ?? null;
            $event->classic_level_max = $data['classic_level_max'] ?? null;
            $event->beach_level_min = $data['beach_level_min'] ?? null;
            $event->beach_level_max = $data['beach_level_max'] ?? null;
            $event->bot_assistant_enabled      = in_array($data['bot_assistant_enabled'] ?? '0', [1, '1', true, 'true', 'on'], true);
            $event->bot_assistant_threshold    = max(5, min(30, (int) ($data['bot_assistant_threshold'] ?? 10)));
            $event->bot_assistant_max_fill_pct = max(10, min(60, (int) ($data['bot_assistant_max_fill_pct'] ?? 40)));

            // Новые поля
            if (array_key_exists('direction', $data) && $data['direction']) {
                $event->direction = $data['direction'];
            }
            if (array_key_exists('format', $data) && $data['format']) {
                $event->format = $data['format'];
            }
            if (isset($data['teams_count'])) {
                $event->tournament_teams_count = (int) $data['teams_count'];
            }
            if (array_key_exists('age_policy', $data)) {
                $event->age_policy = $data['age_policy'] ?? 'adult';
            }
            $event->is_private        = (bool) ($data['is_private'] ?? false);
            $event->is_paid           = (bool) ($data['is_paid'] ?? false);
            $event->price_minor       = $event->is_paid && !empty($data['price_amount'])
                ? (int) round((float)$data['price_amount'] * 100)
                : null;
            $event->price_currency    = $event->is_paid
                ? ($data['price_currency'] ?? 'RUB')
                : null;
            $event->payment_method    = $event->is_paid
                ? ($data['payment_method'] ?? 'cash')
                : null;
            $event->payment_link      = $event->is_paid
                ? ($data['payment_link'] ?? null)
                : null;
            $event->show_participants = (bool) ($data['show_participants'] ?? false);
            $event->requires_personal_data             = (bool) ($data['requires_personal_data'] ?? false);
            $event->remind_registration_enabled        = (bool) ($data['remind_registration_enabled'] ?? false);
            // Fallback: если hidden-поле не пришло (JS не отработал) — оставляем текущее значение
            $event->remind_registration_minutes_before = $data['remind_registration_minutes_before']
                ?? $event->remind_registration_minutes_before
                ?? 600;

            if (array_key_exists('description_html', $data) && $data['description_html'] !== null) {
                $event->description_html = $data['description_html'];
            }

            if (array_key_exists('event_photos', $data)) {
                $event->event_photos = json_decode($data['event_photos'] ?? '[]', true) ?: [];
            }

            if ($allowReg) {
                $event->registration_starts_at = $startsUtc->copy()->subDays($daysBefore)->subHours($hoursBefore);
                $event->registration_ends_at = $startsUtc->copy()->subMinutes($endsMinBefore);
                $event->cancel_self_until = $startsUtc->copy()->subMinutes($cancelMinBefore);
                $event->cancel_self_until_waitlist = $cancelWaitlistMinBefore !== null
                    ? $startsUtc->copy()->subMinutes($cancelWaitlistMinBefore)
                    : null;
            } else {
                $event->registration_starts_at = null;
                $event->registration_ends_at = null;
                $event->cancel_self_until = null;
                $event->cancel_self_until_waitlist = null;
            }

            // Повторение
            $recResult = app(\App\Services\EventOccurrenceService::class)
                ->normalizeRecurrenceRule($data, $allowReg, $tz);
            if (!empty($recResult['errors'])) {
                throw \Illuminate\Validation\ValidationException::withMessages($recResult['errors']);
            }
            $event->is_recurring    = $recResult['isRecurring'];
            $event->recurrence_rule = $recResult['isRecurring'] ? $recResult['recRule'] : null;

            $event->save();
    
            $glp = $data['game_gender_limited_positions'] ?? null;
            if (is_array($glp)) {
                $glp = json_encode(array_values($glp), JSON_UNESCAPED_UNICODE);
            }
    
            $gsPayload = array_filter([
                'subtype' => $data['game_subtype'] ?? $data['tournament_game_scheme'] ?? null,
                'libero_mode' => $data['game_libero_mode'] ?? null,
                'min_players' => $data['game_min_players'] ?? null,
                'max_players' => $data['game_max_players'] ?? null,
                'gender_policy' => $data['game_gender_policy'] ?? null,
                'gender_limited_side' => $data['game_gender_limited_side'] ?? null,
                'gender_limited_max' => $data['game_gender_limited_max'] ?? null,
                'gender_limited_positions' => $glp,
                'gender_limited_reg_starts_days_before' =>
                    (isset($data['game_gender_limited_reg_starts_days_before']) && $data['game_gender_limited_reg_starts_days_before'] !== '')
                        ? (int) $data['game_gender_limited_reg_starts_days_before']
                        : null,
                'allow_girls' => array_key_exists('game_allow_girls', $data)
                    ? (bool) $data['game_allow_girls']
                    : null,
                'girls_max' => $data['game_girls_max'] ?? null,
                'teams_count' => $data['teams_count'] ?? null,
                'reserve_players_max' => array_key_exists('game_reserve_players_max', $data)
                    ? (($data['game_reserve_players_max'] === '' || $data['game_reserve_players_max'] === null) ? null : (int) $data['game_reserve_players_max'])
                    : null,
            ], static fn ($v) => $v !== null);
    
            if (!isset($gsPayload['subtype']) && !$event->gameSettings) {
                $dir = (string) ($event->direction ?? 'classic');
                $gsPayload['subtype'] = ($dir === 'beach') ? '2x2' : '4x2';
            }
    
            if (!empty($gsPayload)) {
                $event->gameSettings()->updateOrCreate(
                    ['event_id' => (int) $event->id],
                    $gsPayload
                );
            }
    
            $event->load('gameSettings');

            // Пересинхронизировать event_role_slots с актуальными game_settings
            // (иначе max_slots остаётся от старого teams_count/subtype — баг с "лишними" свободными местами)
            if ($event->gameSettings) {
                $gs = $event->gameSettings;
                $teams = max(2, min((int) ($gs->teams_count ?? 2), 200));

                $calc = \App\Services\GameCalculator::calculate(
                    (string) $gs->subtype,
                    $gs->libero_mode,
                    $teams
                );

                $roles = [];
                foreach (($calc['roles'] ?? []) as $role => $count) {
                    $roles[$role] = $count * $teams;
                }

                if (!empty($roles)) {
                    app(\App\Services\EventRoleSlotService::class)->syncRoleSlots($event, $roles);
                }

                // max_players тоже пересчитываем от актуальных schema/teams_count —
                // иначе "Свободных мест" на странице события считает от старого значения.
                $newMaxPlayers = (int) ($calc['max_players'] ?? 0);
                if ($newMaxPlayers > 0 && (int) $gs->max_players !== $newMaxPlayers) {
                    $gs->max_players = $newMaxPlayers;
                    $gs->save();
                }
            }

            // Обновляем настройки турнира (если турнир): схема, состав, заявки, режим записи и пр.
            if ($event->format === 'tournament') {
                $isBeach = ($event->direction ?? 'classic') === 'beach';
                $defaultScheme = $isBeach ? '2x2' : '5x1';
                $payMode = $event->is_paid ? ($data['tournament_payment_mode'] ?? 'team') : 'free';
                $scheme = $data['tournament_game_scheme'] ?? $defaultScheme;

                $teamSizeMin = isset($data['tournament_team_size_min']) ? (int) $data['tournament_team_size_min'] : null;
                $reserveMax  = isset($data['tournament_reserve_players_max']) ? (int) $data['tournament_reserve_players_max'] : null;

                // Форма редактирования не имеет чекбокса king_beach_reg (вне скоупа) —
                // без этой проверки любое сохранение формы молча откатывало
                // king_beach-турнир на team_beach (registration_mode перезаписывался
                // безусловно). Сохраняем king_beach, если явно не переключили на individual.
                $isIndividual = (bool) ($data['tournament_individual_reg'] ?? false);
                if ($event->registration_mode === 'king_beach' && !$isIndividual) {
                    $registrationMode = 'king_beach';
                } else {
                    $registrationMode = $isIndividual ? 'tournament_individual' : ($isBeach ? 'team_beach' : 'team_classic');
                }
                $event->registration_mode = $registrationMode;
                $event->save();

                $tsPayload = array_filter([
                    'registration_mode'            => $registrationMode,
                    'game_scheme'                  => $scheme,
                    'require_libero'               => !$isBeach && $scheme === '5x1_libero',
                    'team_size_min'                => $teamSizeMin,
                    'team_size_max'                => $teamSizeMin,
                    'reserve_players_max'          => $reserveMax,
                    // Пересчитывается из min+reserve на каждое сохранение — иначе остаётся
                    // от прежней схемы/резерва, как раньше происходило с require_libero.
                    'total_players_max'            => ($teamSizeMin !== null && $reserveMax !== null) ? $teamSizeMin + $reserveMax : null,
                    'teams_count'                  => isset($data['teams_count']) ? (int) $data['teams_count'] : null,
                    'application_mode'             => $data['tournament_application_mode'] ?? 'manual',
                    'captain_confirms_members'     => array_key_exists('tournament_captain_confirms_members', $data) ? (bool) $data['tournament_captain_confirms_members'] : null,
                    'auto_submit_when_ready'       => array_key_exists('tournament_auto_submit_when_ready', $data) ? (bool) $data['tournament_auto_submit_when_ready'] : null,
                    'allow_incomplete_application' => array_key_exists('tournament_allow_incomplete_application', $data) ? (bool) $data['tournament_allow_incomplete_application'] : null,
                    'payment_mode'                 => $payMode,
                ], static fn ($v) => $v !== null);

                \App\Models\EventTournamentSetting::updateOrCreate(
                    ['event_id' => (int) $event->id],
                    $tsPayload
                );
            }
    
            if (Schema::hasTable('event_occurrences') && $event->starts_at) {
                $startUtc = Carbon::parse($event->starts_at, 'UTC');
                $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";
                $nowUtc = Carbon::now('UTC');
    
                if (!(bool) ($event->is_recurring ?? false)) {
                    $cleanupQ = DB::table('event_occurrences')
                        ->where('event_id', (int) $event->id)
                        ->where('uniq_key', '!=', $uniq);
    
                    if (Schema::hasColumn('event_occurrences', 'starts_at')) {
                        $cleanupQ->where('starts_at', '>=', $nowUtc);
                    }
    
                    $payload = [
                        'updated_at' => $nowUtc,
                    ];
    
                    if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                        $payload['cancelled_at'] = $nowUtc;
                    }
    
                    if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                        $payload['is_cancelled'] = true;
                    }
    
                    $cleanupQ->update($payload);
                }
    
                EventOccurrence::query()->updateOrCreate(
                    ['uniq_key' => $uniq],
                    [
                        'event_id' => (int) $event->id,
                        'starts_at' => $startUtc,
                        'duration_sec' => $event->duration_sec,
                        'timezone' => $event->timezone ?: 'UTC',
                        'location_id' => $event->location_id ?? null,
                        'allow_registration' => $event->allow_registration ?? null,
                        'max_players' => $event->gameSettings?->max_players ?? null,
                        'registration_starts_at' => $event->registration_starts_at ?? null,
                        'registration_ends_at' => $event->registration_ends_at ?? null,
                        'cancel_self_until'         => $event->cancel_self_until ?? null,
                        'cancel_self_until_waitlist' => $event->cancel_self_until_waitlist ?? null,
                        'age_policy' => $event->age_policy ?? null,
                        'is_snow' => $event->is_snow ?? null,
                    ]
                );

                // Для повторяющихся серий: обновить все ещё не начавшиеся occurrences
                // (starts_at > now). Архивные (прошедшие) не трогаем.
                if ((bool) ($event->is_recurring ?? false)) {
                    $futureOccs = EventOccurrence::query()
                        ->where('event_id', (int) $event->id)
                        ->where('starts_at', '>', $nowUtc)
                        ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                        ->get();

                    // Новое время серии (час:минута в TZ события)
                    $eventTz = $event->timezone ?: 'UTC';
                    $newEventStartInTz = Carbon::parse($event->starts_at, 'UTC')->setTimezone($eventTz);
                    $newHour   = $newEventStartInTz->hour;
                    $newMinute = $newEventStartInTz->minute;

                    foreach ($futureOccs as $occ) {
                        // Индивидуально отредактированные повторения защищены от перезаписи
                        if ((bool)($occ->is_individually_edited ?? false)) {
                            continue;
                        }

                        $occStart = Carbon::parse($occ->starts_at, 'UTC');
                        $occStartInTz = $occStart->copy()->setTimezone($occ->timezone ?: $eventTz);

                        // Если время occurrence отличается от нового времени серии — это дубль со старым временем
                        if ($occStartInTz->hour !== $newHour || $occStartInTz->minute !== $newMinute) {
                            // Отменяем только если нет активных регистраций
                            $hasRegs = DB::table('event_registrations')
                                ->where('occurrence_id', $occ->id)
                                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                                ->where('status', '!=', 'cancelled')
                                ->exists();
                            if (!$hasRegs) {
                                $occ->is_cancelled = true;
                                $occ->cancelled_at = $nowUtc;
                                $occ->save();
                            }
                            continue;
                        }

                        $occ->duration_sec       = $event->duration_sec;
                        $occ->timezone           = $event->timezone ?: 'UTC';
                        $occ->location_id        = $event->location_id ?? null;
                        $occ->allow_registration = $event->allow_registration ?? null;
                        $occ->max_players        = $event->gameSettings?->max_players ?? null;
                        $occ->age_policy         = $event->age_policy ?? null;
                        $occ->is_snow            = $event->is_snow ?? null;

                        if ($allowReg) {
                            $occ->registration_starts_at = $occStart->copy()->subDays($daysBefore)->subHours($hoursBefore);
                            $occ->registration_ends_at   = $occStart->copy()->subMinutes($endsMinBefore);

                            // Если рассчитанный дедлайн отмены уже в прошлом — не блокируем выход
                            $cancelUntil = $occStart->copy()->subMinutes($cancelMinBefore);
                            $occ->cancel_self_until = $cancelUntil->greaterThan($nowUtc) ? $cancelUntil : null;

                            $cancelWlUntil = $cancelWaitlistMinBefore !== null
                                ? $occStart->copy()->subMinutes($cancelWaitlistMinBefore)
                                : null;
                            $occ->cancel_self_until_waitlist = ($cancelWlUntil && $cancelWlUntil->greaterThan($nowUtc))
                                ? $cancelWlUntil
                                : null;
                        } else {
                            $occ->registration_starts_at     = null;
                            $occ->registration_ends_at       = null;
                            $occ->cancel_self_until          = null;
                            $occ->cancel_self_until_waitlist = null;
                        }

                        $occ->save();
                    }
                }
            }
        });

        $event->refresh();

        // Обновляем анонсы в каналах если событие изменилось
        $hasChannels = DB::table('event_notification_channels')
            ->where('event_id', (int) $event->id)
            ->exists();
        if ($hasChannels) {
            $occurrenceIds = DB::table('event_occurrences')
                ->where('event_id', (int) $event->id)
                ->where('starts_at', '>=', now())
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->pluck('id');
            foreach ($occurrenceIds as $occId) {
                \App\Jobs\RefreshOccurrenceAnnouncementJob::dispatch((int) $occId)
                    ->onQueue('default');
            }
        }

        // Каналы анонсов: пересохраняем привязки (delete-then-insert)
        app(\App\Services\EventNotificationChannelService::class)->updateChannels($event, $request);

        // Обработка будущих повторений при изменении расписания серии
        $futureOccurrencesAction = $request->input('future_occurrences_action', 'keep');
        $cancelledFutureIds = [];
        if (
            $futureOccurrencesAction === 'cancel'
            && (bool) $event->is_recurring
            && Schema::hasTable('event_occurrences')
        ) {
            $nowUtc = CarbonImmutable::now('UTC');

            $futureIds = DB::table('event_occurrences')
                ->where('event_id', (int) $event->id)
                ->where('starts_at', '>', $nowUtc)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();

            if (!empty($futureIds)) {
                // Разделяем: с активными регистрациями → cancel+уведомление, без → delete
                $withRegs = DB::table('event_registrations')
                    ->whereIn('occurrence_id', $futureIds)
                    ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                    ->distinct()
                    ->pluck('occurrence_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();

                $withoutRegs = array_values(array_diff($futureIds, $withRegs));

                // Без регистраций — удаляем (мусор)
                if (!empty($withoutRegs)) {
                    DB::table('event_occurrences')->whereIn('id', $withoutRegs)->delete();
                }

                // С регистрациями — отменяем и уведомляем
                if (!empty($withRegs)) {
                    $cancelPayload = ['cancelled_at' => $nowUtc, 'updated_at' => $nowUtc];
                    if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                        $cancelPayload['is_cancelled'] = true;
                    }
                    DB::table('event_occurrences')
                        ->whereIn('id', $withRegs)
                        ->update($cancelPayload);
                    $cancelledFutureIds = $withRegs;
                }
            }
        }

        if ((bool) $event->is_recurring && trim((string) $event->recurrence_rule) !== '') {
            ExpandEventOccurrencesJob::dispatch((int) $event->id, 90, 500);
        }

        // Уведомляем участников отменённых повторений (только тех у кого были регистрации)
        foreach ($cancelledFutureIds as $occurrenceId) {
            $this->notifyUsersAboutCancelledEvent(
                event: $event,
                occurrenceId: $occurrenceId,
                reason: 'Расписание серии изменено организатором'
            );
        }

        return redirect()
            ->route('events.create.event_management', ['tab' => 'mine'])
            ->with('status', 'Мероприятие обновлено. Активные записи сохранены.');
    }
    private function safeForceDeleteEvent(Event $event): void
    {
        DB::transaction(function () use ($event) {
            $eventId = (int) $event->id;
    
            $occurrenceIds = [];
            if (Schema::hasTable('event_occurrences')) {
                $occurrenceIds = DB::table('event_occurrences')
                    ->where('event_id', $eventId)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }
    
            if (Schema::hasTable('notification_deliveries')) {
                if (Schema::hasColumn('notification_deliveries', 'event_id')) {
                    DB::table('notification_deliveries')
                        ->where('event_id', $eventId)
                        ->delete();
                }
    
                if (!empty($occurrenceIds) && Schema::hasColumn('notification_deliveries', 'occurrence_id')) {
                    DB::table('notification_deliveries')
                        ->whereIn('occurrence_id', $occurrenceIds)
                        ->delete();
                }
            }
    
            if (Schema::hasTable('event_trainers') && Schema::hasColumn('event_trainers', 'event_id')) {
                DB::table('event_trainers')
                    ->where('event_id', $eventId)
                    ->delete();
            }
    
            $event->delete();
        });
    }
    public function destroy(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) return redirect()->route('login');
    
        $this->ensureCanCreateEvents($user);
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        if ($role === 'organizer' && (int) $event->organizer_id !== (int) $user->id) {
            abort(403);
        }
    
        if ($role === 'staff' && (int) $event->organizer_id !== (int) $organizerIdForStaff) {
            abort(403);
        }
    
        $mode = (string) $request->input('delete_mode', 'cancel');
    
        if ($mode === 'force') {
            $isPrivileged = in_array($role, ['admin'], true);
    
            if (!$isPrivileged) {
                return back()->with('error', 'Полное удаление доступно только администратору.');
            }
    
            $this->safeForceDeleteEvent($event);
    
            return back()->with('status', 'Мероприятие удалено навсегда.');
        }
    
        $now = CarbonImmutable::now('UTC');
    
        $isRootRecurring =
            (bool) ($event->is_recurring ?? false)
            && trim((string) ($event->recurrence_rule ?? '')) !== '';
    
        $buildCancelPayload = function () use ($now) {
            $payload = [
                'cancelled_at' => $now,
                'updated_at' => $now,
            ];
    
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $payload['is_cancelled'] = true;
            }
    
            return $payload;
        };
    
        // cancel series
        if ($mode === 'series') {
            if (!$isRootRecurring) {
                return back()->with('error', 'Удаление цепочки доступно только для повторяющегося (корневого) мероприятия.');
            }
    
            $cancelledOccurrenceIds = [];
    
            DB::transaction(function () use ($event, $now, $buildCancelPayload, &$cancelledOccurrenceIds) {
                $event->is_recurring = false;
                $event->recurrence_rule = null;
                $event->save();
    
                if (Schema::hasTable('event_occurrences')) {
                    $q = DB::table('event_occurrences')
                        ->where('event_id', (int) $event->id)
                        ->whereNotNull('starts_at')
                        ->where('starts_at', '>', $now);
    
                    if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                        $q->whereNull('cancelled_at');
                    }
    
                    if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                        $q->where(function ($w) {
                            $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                        });
                    }
    
                    $cancelledOccurrenceIds = $q->pluck('id')
                        ->map(fn ($v) => (int) $v)
                        ->all();
    
                    if (!empty($cancelledOccurrenceIds)) {
                        DB::table('event_occurrences')
                            ->whereIn('id', $cancelledOccurrenceIds)
                            ->update($buildCancelPayload());
                    }
                }
            });
    
            foreach ($cancelledOccurrenceIds as $occurrenceId) {
                $this->notifyUsersAboutCancelledEvent(
                    event: $event,
                    occurrenceId: (int) $occurrenceId,
                    reason: 'Отменено организатором'
                );
            }
    
            return back()->with('status', 'Цепочка удалена: повторение выключено, будущие occurrences отменены, история сохранена.');
        }
    
        // default = cancel one future date
        $cancelledOccurrenceIds = [];
    
        DB::transaction(function () use ($event, $now, $buildCancelPayload, &$cancelledOccurrenceIds, $isRootRecurring) {
            if (!Schema::hasTable('event_occurrences')) {
                return;
            }
    
            $q = DB::table('event_occurrences')
                ->where('event_id', (int) $event->id)
                ->whereNotNull('starts_at')
                ->where('starts_at', '>', $now);
    
            if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
                $q->whereNull('cancelled_at');
            }
    
            if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
                $q->where(function ($w) {
                    $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
                });
            }
    
            if ($isRootRecurring && $event->starts_at) {
                $targetRows = (clone $q)
                    ->where('starts_at', '=', $event->starts_at)
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
    
                if (!empty($targetRows)) {
                    $cancelledOccurrenceIds = $targetRows;
                }
            }
    
            if (empty($cancelledOccurrenceIds)) {
                $row = $q->orderBy('starts_at')->first(['id']);
                if ($row) {
                    $cancelledOccurrenceIds = [(int) $row->id];
                }
            }
    
            if (!empty($cancelledOccurrenceIds)) {
                DB::table('event_occurrences')
                    ->whereIn('id', $cancelledOccurrenceIds)
                    ->update($buildCancelPayload());
            }
        });
    
        if (empty($cancelledOccurrenceIds)) {
            return back()->with('error', 'Нет будущих дат для отмены. История прошедших мероприятий сохраняется.');
        }
    
        foreach ($cancelledOccurrenceIds as $occurrenceId) {
            $this->notifyUsersAboutCancelledEvent(
                event: $event,
                occurrenceId: (int) $occurrenceId,
                reason: 'Отменено организатором'
            );
        }
    
        return back()->with('status', 'Мероприятие отменено: дата скрыта из UI, история сохранена.');
    }

  public function bulkDelete(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
    
        $this->ensureCanCreateEvents($user);
    
        $tab = (string) $request->query('tab', 'mine');
        $tab = in_array($tab, ['archive', 'mine'], true) ? $tab : 'mine';
    
        $role = (string) ($user->role ?? 'user');
        $organizerIdForStaff = $this->resolveOrganizerIdForCreator($user);
    
        $deleteMode = (string) $request->input('delete_mode', 'cancel');
        $deleteMode = in_array($deleteMode, ['cancel', 'force'], true) ? $deleteMode : 'cancel';
    
        if ($deleteMode === 'force' && $role !== 'admin') {
            return back()->with('error', 'Полное удаление доступно только администратору.');
        }
    
        $ids = $request->input('ids', []);
        if (!is_array($ids)) {
            $ids = [];
        }
    
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_values(array_filter($ids, fn ($v) => $v > 0));
    
        if (empty($ids)) {
            return back()->with('error', 'Ничего не выбрано для удаления.');
        }
    
        $q = Event::query()->whereIn('id', $ids);
    
        if ($role === 'admin') {
            // admin может любые
        } elseif ($role === 'organizer') {
            $q->where('organizer_id', (int) $user->id);
        } elseif ($role === 'staff') {
            $q->where('organizer_id', (int) $organizerIdForStaff);
        } else {
            $q->whereRaw('1=0');
        }
    
        $events = $q->get();
    
        $affected = 0;
        $cancelledFuture = 0;
        $deletedHard = 0;
        $skippedForbidden = count($ids) - $events->count();
        $skippedNothingToCancel = 0;
        $notificationsToSend = [];
    
        /*
        |--------------------------------------------------------------------------
        | FORCE DELETE (admin only)
        |--------------------------------------------------------------------------
        */
        if ($deleteMode === 'force') {
            foreach ($events as $event) {
                $this->safeForceDeleteEvent($event);
                $deletedHard++;
                $affected++;
            }
    
            $msg = "Bulk force delete: затронуто {$affected}.";
            if ($deletedHard > 0) {
                $msg .= " Удалено навсегда: {$deletedHard}.";
            }
            if ($skippedForbidden > 0) {
                $msg .= " Недоступно по правам/не найдено: {$skippedForbidden}.";
            }
    
            return redirect()
                ->route('events.create.event_management', ['tab' => $tab])
                ->with('status', $msg)
                ->with('bulk_affected', $affected)
                ->with('bulk_cancelled_future', $cancelledFuture)
                ->with('bulk_deleted_hard', $deletedHard)
                ->with('bulk_skipped_nothing', $skippedNothingToCancel)
                ->with('bulk_skipped_forbidden', $skippedForbidden);
        }
    
        /*
        |--------------------------------------------------------------------------
        | CANCEL MODE (default)
        |--------------------------------------------------------------------------
        */
        $nowUtc = Carbon::now('UTC');
    
        $hasOccTable = Schema::hasTable('event_occurrences');
        $occHasCancelledAt = $hasOccTable && Schema::hasColumn('event_occurrences', 'cancelled_at');
        $occHasIsCancelled = $hasOccTable && Schema::hasColumn('event_occurrences', 'is_cancelled');
    
        $buildCancelPayload = function () use ($nowUtc, $occHasIsCancelled) {
            $payload = [
                'cancelled_at' => $nowUtc,
                'updated_at' => $nowUtc,
            ];
    
            if ($occHasIsCancelled) {
                $payload['is_cancelled'] = true;
            }
    
            return $payload;
        };
    
        DB::transaction(function () use (
            $events,
            $nowUtc,
            $hasOccTable,
            $occHasCancelledAt,
            $occHasIsCancelled,
            $buildCancelPayload,
            &$affected,
            &$cancelledFuture,
            &$skippedNothingToCancel,
            &$notificationsToSend
        ) {
            foreach ($events as $event) {
                if (!$hasOccTable) {
                    // В новой схеме bulk cancel работает только через event_occurrences.
                    $skippedNothingToCancel++;
                    continue;
                }
    
                $occQ = DB::table('event_occurrences')
                    ->where('event_id', (int) $event->id)
                    ->whereNotNull('starts_at')
                    ->where('starts_at', '>', $nowUtc);
    
                if ($occHasCancelledAt) {
                    $occQ->whereNull('cancelled_at');
                }
    
                if ($occHasIsCancelled) {
                    $occQ->where(function ($w) {
                        $w->whereNull('is_cancelled')
                          ->orWhere('is_cancelled', false);
                    });
                }
    
                $idsToCancel = $occQ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
    
                if (empty($idsToCancel)) {
                    $skippedNothingToCancel++;
                    continue;
                }
    
                DB::table('event_occurrences')
                    ->whereIn('id', $idsToCancel)
                    ->update($buildCancelPayload());
    
                // считаем, что bulk cancel выключает дальнейшую генерацию дат
                if ((bool) ($event->is_recurring ?? false) && trim((string) ($event->recurrence_rule ?? '')) !== '') {
                    $event->is_recurring = false;
                    $event->recurrence_rule = null;
                    $event->save();
                }
    
                foreach ($idsToCancel as $occurrenceId) {
                    $notificationsToSend[] = [
                        'event' => $event,
                        'occurrence_id' => (int) $occurrenceId,
                        'reason' => 'Отменено организатором',
                    ];
                }
    
                $cancelledFuture++;
                $affected++;
            }
        });
    
        foreach ($notificationsToSend as $item) {
            $this->notifyUsersAboutCancelledEvent(
                event: $item['event'],
                occurrenceId: $item['occurrence_id'],
                reason: $item['reason']
            );
        }
    
        $msg = "Bulk cancel: затронуто {$affected}.";
        if ($cancelledFuture > 0) {
            $msg .= " Отменено будущих occurrences: {$cancelledFuture} (история сохранена).";
        }
        if ($skippedNothingToCancel > 0) {
            $msg .= " Пропущено (нет будущих дат для отмены): {$skippedNothingToCancel}.";
        }
        if ($skippedForbidden > 0) {
            $msg .= " Недоступно по правам/не найдено: {$skippedForbidden}.";
        }
    
        return redirect()
            ->route('events.create.event_management', ['tab' => $tab])
            ->with('status', $msg)
            ->with('bulk_affected', $affected)
            ->with('bulk_cancelled_future', $cancelledFuture)
            ->with('bulk_deleted_hard', $deletedHard)
            ->with('bulk_skipped_nothing', $skippedNothingToCancel)
            ->with('bulk_skipped_forbidden', $skippedForbidden);
    }
    private function notifyUsersAboutCancelledEvent(Event $event, ?int $occurrenceId, string $reason): void
    {
        if (!Schema::hasTable('event_registrations')) {
            return;
        }

        $q = DB::table('event_registrations')
            ->where('event_id', (int) $event->id);

        if (!is_null($occurrenceId) && Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q->where('occurrence_id', (int) $occurrenceId);
        }

        if (Schema::hasColumn('event_registrations', 'cancelled_at')) {
            $q->whereNull('cancelled_at');
        }

        if (Schema::hasColumn('event_registrations', 'is_cancelled')) {
            $q->where(function ($w) {
                $w->whereNull('is_cancelled')->orWhere('is_cancelled', false);
            });
        }

        if (Schema::hasColumn('event_registrations', 'status')) {
            $q->where(function ($w) {
                $w->whereNull('status')->orWhere('status', 'confirmed');
            });
        }

        $userIds = $q->pluck('user_id')
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values();

        foreach ($userIds as $userId) {
            $this->userNotificationService->createEventCancelledNotification(
                userId: $userId,
                eventId: (int) $event->id,
                occurrenceId: $occurrenceId,
                eventTitle: (string) ($event->title ?? ('Мероприятие #' . $event->id)),
                reason: $reason
            );
        }
    }

    private function ensureCanCreateEvents($user): void
    {
        if (!$user) abort(403);

        $role = (string) ($user->role ?? 'user');
        if (!in_array($role, ['admin', 'organizer', 'staff'], true)) {
            abort(403);
        }
    }

    private function resolveOrganizerIdForCreator($user): int
    {
        $role = (string) ($user->role ?? 'user');

        if ($role === 'organizer') {
            return (int) $user->id;
        }

        if ($role === 'staff') {
            $row = DB::table('organizer_staff')
                ->where('staff_user_id', (int) $user->id)
                ->orderBy('id')
                ->first(['organizer_id']);

            return $row ? (int) $row->organizer_id : 0;
        }

        return 0;
    }
}