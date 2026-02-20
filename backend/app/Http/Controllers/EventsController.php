<?php

// app/Http/Controllers/EventsController.php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventsController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // ====== GET-фильтр ======
        $direction = trim((string) request('direction', '')); // classic|beach|''
        $format    = trim((string) request('format', ''));    // training|game|...
        $levelRaw  = request('level', null);
        $level     = (is_null($levelRaw) || $levelRaw === '') ? null : (int) $levelRaw;

        // нормализуем direction, чтобы не прилетало мусора
        if (!in_array($direction, ['', 'classic', 'beach'], true)) {
            $direction = '';
        }

        // ✅ безопасно: сначала проверяем таблицу
        $hasRegTable    = Schema::hasTable('event_registrations');
        $hasCancelledAt = $hasRegTable && Schema::hasColumn('event_registrations', 'cancelled_at');
        $hasIsCancelled = $hasRegTable && Schema::hasColumn('event_registrations', 'is_cancelled');
        $hasStatus      = $hasRegTable && Schema::hasColumn('event_registrations', 'status');

        // =========================================================
        // OCCURRENCES-ветка
        // =========================================================
        if (Schema::hasTable('event_occurrences')) {
            $occQ = EventOccurrence::query()
                ->with([
                    'event.location:id,name,city,address',
                    'event.gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
                    'event.media',
                ])
                ->orderBy('starts_at', 'asc');

            // ✅ АКТУАЛЬНЫЕ OCCURRENCES (скрываем архивные на /events)
            $cutoffUtc = Carbon::now('UTC')->startOfDay();
            $hasOccStarts = Schema::hasColumn('event_occurrences', 'starts_at');
            $hasOccEnds   = Schema::hasColumn('event_occurrences', 'ends_at');

            if ($hasOccStarts) {
                if ($hasOccEnds) {
                    $occQ->where(function ($w) use ($cutoffUtc) {
                        $w->where(function ($q) use ($cutoffUtc) {
                            $q->whereNotNull('ends_at')
                                ->where('ends_at', '>=', $cutoffUtc);
                        })->orWhere(function ($q) use ($cutoffUtc) {
                            $q->whereNull('ends_at')
                                ->where('starts_at', '>=', $cutoffUtc);
                        });
                    });
                } else {
                    $occQ->where('starts_at', '>=', $cutoffUtc);
                }
            }

            // ✅ фильтр по регистрации/платности + приватность + GET-фильтры
            $occQ->whereHas('event', function ($q) use ($user, $direction, $format, $level) {
                // базовое условие: allow_registration OR (paid and allow_registration null/false)
                $q->where(function ($w) {
                    $w->where('allow_registration', true)
                        ->orWhere(function ($w2) {
                            $w2->where('is_paid', true)
                                ->where(function ($w3) {
                                    $w3->whereNull('allow_registration')
                                        ->orWhere('allow_registration', false);
                                });
                        });
                });

                // ✅ фильтр: направление
                if ($direction !== '') {
                    $q->where('direction', $direction);
                }

                // ✅ фильтр: формат
                if ($format !== '') {
                    $q->where('format', $format);
                }

                // ✅ фильтр: уровень (Вариант B)
                $this->applyLevelFilterVariantB($q, $direction, $level);

                // ✅ приватность (в whereHas префикс не нужен)
                $this->applyPrivateVisibilityScope($q, $user, '');
            });

            $occurrences = $occQ->get();

            // ✅ trainers map
            $trainerCol = Schema::hasColumn('events', 'trainer_user_id')
                ? 'trainer_user_id'
                : (Schema::hasColumn('events', 'trainer_id') ? 'trainer_id' : null);

            $trainerIds = [];
            if ($trainerCol) {
                foreach ($occurrences as $occ) {
                    $e = $occ->event;
                    if (!$e) continue;
                    $tid = (int) ($e->{$trainerCol} ?? 0);
                    if ($tid > 0) $trainerIds[] = $tid;
                }
            }
            $trainerIds = array_values(array_unique(array_filter($trainerIds)));

            $trainersById = [];
            if (!empty($trainerIds)) {
                $trainersById = User::query()
                    ->whereIn('id', $trainerIds)
                    ->get(['id', 'name', 'email'])
                    ->keyBy('id')
                    ->all();
            }

            [$joinedOccurrenceIds, $restrictedOccurrenceIds] = $this->joinedAndRestrictedOccurrenceIds(
                $hasCancelledAt,
                $hasIsCancelled,
                $hasStatus
            );

            return view('events.index', [
                'occurrences'             => $occurrences,
                'joinedOccurrenceIds'     => $joinedOccurrenceIds,
                'restrictedOccurrenceIds' => $restrictedOccurrenceIds,
                'trainersById'            => $trainersById,
                'trainerColumn'           => $trainerCol,

                // legacy
                'events'             => collect(),
                'joinedEventIds'     => [],
                'restrictedEventIds' => [],
            ]);
        }

        // =========================================================
        // legacy-ветка (если occurrences нет)
        // =========================================================
        $eventsQ = Event::query()
            ->with([
                'location:id,name,city,address',
                'gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
                'media',
            ])
            ->where(function ($w) {
                $w->where('allow_registration', true)
                    ->orWhere(function ($w2) {
                        $w2->where('is_paid', true)
                            ->where(function ($w3) {
                                $w3->whereNull('allow_registration')
                                    ->orWhere('allow_registration', false);
                            });
                    });
            });

        // ✅ АКТУАЛЬНЫЕ EVENTS (legacy): тот же cutoff
        $cutoffUtc = Carbon::now('UTC')->startOfDay();
        $hasEvStarts = Schema::hasColumn('events', 'starts_at');
        $hasEvEnds   = Schema::hasColumn('events', 'ends_at');

        if ($hasEvStarts) {
            if ($hasEvEnds) {
                $eventsQ->where(function ($w) use ($cutoffUtc) {
                    $w->where(function ($q) use ($cutoffUtc) {
                        $q->whereNotNull('ends_at')
                            ->where('ends_at', '>=', $cutoffUtc);
                    })->orWhere(function ($q) use ($cutoffUtc) {
                        $q->whereNull('ends_at')
                            ->whereNotNull('starts_at')
                            ->where('starts_at', '>=', $cutoffUtc);
                    });
                });
            } else {
                $eventsQ->whereNotNull('starts_at')
                    ->where('starts_at', '>=', $cutoffUtc);
            }
        }

        // ✅ GET-фильтры (legacy)
        if ($direction !== '') {
            $eventsQ->where('direction', $direction);
        }
        if ($format !== '') {
            $eventsQ->where('format', $format);
        }
        $this->applyLevelFilterVariantB($eventsQ, $direction, $level);

        // ✅ приватные: видны только admin / organizer / staff
        $this->applyPrivateVisibilityScope($eventsQ, $user);

        $events = $eventsQ->orderByDesc('id')->get();

        // ✅ trainers map for legacy
        $trainerCol = Schema::hasColumn('events', 'trainer_user_id')
            ? 'trainer_user_id'
            : (Schema::hasColumn('events', 'trainer_id') ? 'trainer_id' : null);

        $trainerIds = [];
        if ($trainerCol) {
            foreach ($events as $e) {
                $tid = (int) ($e->{$trainerCol} ?? 0);
                if ($tid > 0) $trainerIds[] = $tid;
            }
        }
        $trainerIds = array_values(array_unique(array_filter($trainerIds)));

        $trainersById = [];
        if (!empty($trainerIds)) {
            $trainersById = User::query()
                ->whereIn('id', $trainerIds)
                ->get(['id', 'name', 'email'])
                ->keyBy('id')
                ->all();
        }

        [$joinedEventIds, $restrictedEventIds] = $this->joinedAndRestrictedEventIds(
            $hasCancelledAt,
            $hasIsCancelled,
            $hasStatus
        );

        return view('events.index', [
            'events'             => $events,
            'joinedEventIds'     => $joinedEventIds,
            'restrictedEventIds' => $restrictedEventIds,
            'trainersById'       => $trainersById,
            'trainerColumn'      => $trainerCol,

            'occurrences'             => collect(),
            'joinedOccurrenceIds'     => [],
            'restrictedOccurrenceIds' => [],
        ]);
    }

    public function show(Request $request, Event $event)
    {
        $user = auth()->user();

        if ($this->isPrivateEventRow($event) && !$this->canViewPrivateEvent($event, $user)) {
            abort(404);
        }

        $relations = [
            'location:id,name,city,address',
            'gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
            'media',
        ];

        if (method_exists($event, 'trainer_user')) {
            $relations[] = 'trainer_user:id,name,email,nickname,username,phone';
        }

        $event->load($relations);

        $occurrenceId = (int) $request->query('occurrence', 0);
        $occurrence = null;

        if (Schema::hasTable('event_occurrences')) {
            if ($occurrenceId > 0) {
                $occurrence = EventOccurrence::query()
                    ->where('id', $occurrenceId)
                    ->where('event_id', (int) $event->id)
                    ->first();
            }
            if (!$occurrence) {
                $occurrence = EventOccurrence::query()
                    ->where('event_id', (int) $event->id)
                    ->orderBy('starts_at', 'asc')
                    ->first();
            }
        }

        $availability = $this->buildAvailabilityForEvent($event);

        if ($occurrence) {
            $payload = $this->availabilityOccurrence($occurrence)->getData(true);
            $availability = [
                'max_players'      => (int) ($payload['meta']['max_players'] ?? 0),
                'registered_total' => (int) ($payload['meta']['registered_total'] ?? 0),
                'remaining_total'  => (int) ($payload['meta']['remaining_total'] ?? 0),
                'free_positions'   => $payload['free_positions'] ?? [],
                'meta'             => $payload['meta'] ?? [],
                
            ];
        }

        return view('events.show', [
            'event'        => $event,
            'occurrence'   => $occurrence,
            'availability' => $availability,
        ]);
    }

    public function availability(Request $request, Event $event)
    {
        $occurrenceId = (int) $request->query('occurrence', 0);
        $occ = null;

        if ($occurrenceId > 0 && Schema::hasTable('event_occurrences')) {
            $occ = EventOccurrence::query()
                ->where('id', $occurrenceId)
                ->where('event_id', (int) $event->id)
                ->first();
        }

        if (!$occ) {
            $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
        }

        if (!$occ) {
            return response()->json([
                'ok' => false,
                'message' => 'Occurrence для события не найден.',
            ], 404);
        }

        return $this->availabilityOccurrence($occ);
    }

    public function availabilityOccurrence(EventOccurrence $occurrence)
    {
        $hasRegTable    = Schema::hasTable('event_registrations');
        $hasCancelledAt = $hasRegTable && Schema::hasColumn('event_registrations', 'cancelled_at');
        $hasIsCancelled = $hasRegTable && Schema::hasColumn('event_registrations', 'is_cancelled');
        $hasStatus      = $hasRegTable && Schema::hasColumn('event_registrations', 'status');
        $hasOccId       = $hasRegTable && Schema::hasColumn('event_registrations', 'occurrence_id');
        $hasEventId     = $hasRegTable && Schema::hasColumn('event_registrations', 'event_id');
        
        $occurrence->load(['event.gameSettings']);
        $event = $occurrence->event;

        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Событие для occurrence не найдено.'], 404);
            
        }

       $gs = $event->gameSettings;

        // ✅ приоритет: occurrence snapshot -> fallback to event.gameSettings
        $maxPlayers = (int) ($occurrence->max_players ?? ($gs?->max_players ?? 0));
        // ✅ gender policy (нужно ДО ранних return)
        $policy = (string) ($gs?->gender_policy ?? 'mixed_open');
        
        // =========================
        // ✅ mixed_5050 meta (M/F counts + limit) — считаем ДО ранних return
        // =========================
        $g5050Limit  = null;
        $g5050Male   = null;
        $g5050Female = null;
        
        if ($policy === 'mixed_5050' && $maxPlayers > 0) {
            $g5050Limit = intdiv((int)$maxPlayers, 2);
        
            $maleVals   = ['m','male'];
            $femaleVals = ['f','female'];
        
            $qMale = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id');
        
            $qFem = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id');
        
            if ($hasOccId) {
                $qMale->where('er.occurrence_id', (int)$occurrence->id);
                $qFem->where('er.occurrence_id', (int)$occurrence->id);
            } elseif ($hasEventId) {
                $qMale->where('er.event_id', (int)$event->id);
                $qFem->where('er.event_id', (int)$event->id);
            } else {
                $qMale->whereRaw('1=0');
                $qFem->whereRaw('1=0');
            }
        
            $qMale->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $maleVals);
            $qFem->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $femaleVals);
        
            $this->applyActiveScope($qMale, $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
            $this->applyActiveScope($qFem,  $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
        
            $g5050Male   = (int) $qMale->count();
            $g5050Female = (int) $qFem->count();
        }

        
        // ✅ allow_registration: occurrence snapshot -> fallback to event
        $allowReg = (bool) ($occurrence->allow_registration ?? ($event->allow_registration ?? false));
        
        if (!$allowReg) {
            return response()->json([
                'ok' => false,
                'code' => 'registration_disabled',
                'message' => 'Регистрация на это мероприятие выключена.',
                'meta' => [
                    'max_players'      => $maxPlayers,
                    'registered_total' => 0,
                    'remaining_total'  => $maxPlayers,
                    'gender_policy'      => $policy,
                    'gender_5050_limit'  => $g5050Limit,
                    'gender_5050_male'   => $g5050Male,
                    'gender_5050_female' => $g5050Female,

                ],
                'free_positions' => [],
            ], 403);
        }
        
        $tzEvent = (string) ($occurrence->timezone ?: ($event->timezone ?: 'UTC'));
        $nowUtc  = now('UTC');
        
        $rawStart = $occurrence->getRawOriginal('starts_at');
        $startsUtc = $rawStart ? Carbon::parse($rawStart, 'UTC') : null;

        // ✅ registration window: occurrence snapshot -> fallback to event (если старые данные/backfill)
        $regStartsUtc = $occurrence->registration_starts_at
            ? Carbon::parse($occurrence->registration_starts_at, 'UTC')
            : ($event->registration_starts_at ? Carbon::parse($event->registration_starts_at, 'UTC') : null);
        
        $regEndsUtc = $occurrence->registration_ends_at
            ? Carbon::parse($occurrence->registration_ends_at, 'UTC')
            : ($event->registration_ends_at ? Carbon::parse($event->registration_ends_at, 'UTC') : null);
        
        $cancelUtc = $occurrence->cancel_self_until
            ? Carbon::parse($occurrence->cancel_self_until, 'UTC')
            : ($event->cancel_self_until ? Carbon::parse($event->cancel_self_until, 'UTC') : null);

        
        // 0) событие уже началось
        if ($startsUtc && $nowUtc->gte($startsUtc)) {
            return response()->json([
                'ok' => false,
                'code' => 'event_started',
                'message' => 'Мероприятие уже началось — регистрация недоступна.',
                   'meta' => [
                      'timezone' => $tzEvent,
                      'starts_at_utc'   => $startsUtc?->toIso8601String(),
                      'starts_at_local' => $this->fmtInTz($startsUtc, $tzEvent),
                      'gender_policy'      => $policy,
                        'gender_5050_limit'  => $g5050Limit,
                        'gender_5050_male'   => $g5050Male,
                        'gender_5050_female' => $g5050Female,
                    
                      'registration_starts_at_utc'   => $regStartsUtc?->toIso8601String(),
                      'registration_starts_at_local' => $this->fmtInTz($regStartsUtc, $tzEvent),
                    
                      'registration_ends_at_utc'     => $regEndsUtc?->toIso8601String(),
                      'registration_ends_at_local'   => $this->fmtInTz($regEndsUtc, $tzEvent),
                    
                      'cancel_self_until_utc'        => $cancelUtc?->toIso8601String(),
                      'cancel_self_until_local'      => $this->fmtInTz($cancelUtc, $tzEvent),
                    ],

                'free_positions' => [],
            ], 403);
        }

        // 1) регистрация ещё не началась
        if ($regStartsUtc && $nowUtc->lt($regStartsUtc)) {
            return response()->json([
                'ok' => false,
                'code' => 'registration_not_started',
                'message' => 'Регистрация начнётся ' .
                    $this->fmtInTz($regStartsUtc, $tzEvent) . ' (' . $tzEvent . ') / ' . $this->fmtUtc($regStartsUtc) . '.',
                'meta' => [
                    'timezone' => $tzEvent,
                      'starts_at_utc'   => $startsUtc?->toIso8601String(),
                      'starts_at_local' => $this->fmtInTz($startsUtc, $tzEvent),
                      'gender_policy'      => $policy,
                        'gender_5050_limit'  => $g5050Limit,
                        'gender_5050_male'   => $g5050Male,
                        'gender_5050_female' => $g5050Female,

                    
                      'registration_starts_at_utc'   => $regStartsUtc?->toIso8601String(),
                      'registration_starts_at_local' => $this->fmtInTz($regStartsUtc, $tzEvent),
                    
                      'registration_ends_at_utc'     => $regEndsUtc?->toIso8601String(),
                      'registration_ends_at_local'   => $this->fmtInTz($regEndsUtc, $tzEvent),
                    
                      'cancel_self_until_utc'        => $cancelUtc?->toIso8601String(),
                      'cancel_self_until_local'      => $this->fmtInTz($cancelUtc, $tzEvent),
                ],
                'free_positions' => [],
            ], 403);
        }
        
        // 2) регистрация закрыта
        if ($regEndsUtc && $nowUtc->gte($regEndsUtc)) {
            return response()->json([
                'ok' => false,
                'code' => 'registration_closed',
                'message' => 'Регистрация закрыта (закрылась ' .
                    $this->fmtInTz($regEndsUtc, $tzEvent) . ' ' . $tzEvent . ').',
                'meta' => [
                   'timezone' => $tzEvent,
                      'starts_at_utc'   => $startsUtc?->toIso8601String(),
                      'starts_at_local' => $this->fmtInTz($startsUtc, $tzEvent),
                      'gender_policy'      => $policy,
                        'gender_5050_limit'  => $g5050Limit,
                        'gender_5050_male'   => $g5050Male,
                        'gender_5050_female' => $g5050Female,
                    
                      'registration_starts_at_utc'   => $regStartsUtc?->toIso8601String(),
                      'registration_starts_at_local' => $this->fmtInTz($regStartsUtc, $tzEvent),
                    
                      'registration_ends_at_utc'     => $regEndsUtc?->toIso8601String(),
                      'registration_ends_at_local'   => $this->fmtInTz($regEndsUtc, $tzEvent),
                    
                      'cancel_self_until_utc'        => $cancelUtc?->toIso8601String(),
                      'cancel_self_until_local'      => $this->fmtInTz($cancelUtc, $tzEvent),
                ],
                'free_positions' => [],
            ], 403);
        }

        $positions = $gs?->positions;
        if (is_string($positions)) {
            $decoded = json_decode($positions, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($positions)) $positions = [];
        $positions = array_values(array_unique(array_map('strval', $positions)));

        $totalQ = DB::table('event_registrations');
        if ($hasOccId) {
            $totalQ->where('occurrence_id', (int) $occurrence->id);
        } elseif ($hasEventId) {
            $totalQ->where('event_id', (int) $event->id);
        } else {
            $totalQ->whereRaw('1=0');
        }
        $this->applyActiveScope($totalQ, $hasCancelledAt, $hasIsCancelled, $hasStatus);

        $registeredTotal = (int) $totalQ->count();
        $remainingTotal  = max(0, $maxPlayers - $registeredTotal);
         $viewerGenderMF = $this->normalizeGenderToMF($viewer?->gender ?? null);

        if ($maxPlayers <= 0 || empty($positions)) {
            return response()->json([
                'ok' => true,
                'meta' => [
                    'max_players'      => $maxPlayers,
                    'registered_total' => $registeredTotal,
                    'remaining_total'  => $remainingTotal,
                    'gender_policy'      => $policy,
                    'gender_5050_limit'  => $g5050Limit,
                    'gender_5050_male'   => $g5050Male,
                    'gender_5050_female' => $g5050Female,
                ],
                'free_positions' => [],
            ]);
        }

        $byPosQ = DB::table('event_registrations');
        if ($hasOccId) {
            $byPosQ->where('occurrence_id', (int) $occurrence->id);
        } elseif ($hasEventId) {
            $byPosQ->where('event_id', (int) $event->id);
        } else {
            $byPosQ->whereRaw('1=0');
        }
        $this->applyActiveScope($byPosQ, $hasCancelledAt, $hasIsCancelled, $hasStatus);

        $byPos = $byPosQ
            ->select('position', DB::raw('COUNT(*) as cnt'))
            ->groupBy('position')
            ->pluck('cnt', 'position')
            ->toArray();

        $team = $this->teamMeta(
            (string) ($gs->subtype ?? ''),
            (string) ($gs->libero_mode ?? '')
        );

        $teamSize      = (int) ($team['team_size'] ?? 0);
        $perTeamCounts = (array) ($team['per_team'] ?? []);
        $teamsCount    = ($teamSize > 0) ? intdiv($maxPlayers, $teamSize) : 0;
        if ($teamsCount < 1) $teamsCount = 1;

        $capacityByPos = [];
        foreach ($perTeamCounts as $posKey => $cntPerTeam) {
            $capacityByPos[(string) $posKey] = max(0, (int) $cntPerTeam) * $teamsCount;
        }

        $viewer = auth()->user();
        $agePolicy = (string) ($occurrence->age_policy ?? $event->age_policy ?? 'any'); // adult|child|any
        $needBirthdate = false;
        $ageBlocked = false;
        
        if ($viewer && $agePolicy !== 'any') {
            $birth = $viewer->birthdate ?? $viewer->birthday ?? null; // под твою колонку в users
            if (!$birth) {
                $needBirthdate = true;
            } else {
                $bd = Carbon::parse($birth);
                $years = $bd->diffInYears(Carbon::now('UTC'));
        
                if ($agePolicy === 'adult' && $years < 18) $ageBlocked = true;
                if ($agePolicy === 'child' && $years >= 18) $ageBlocked = true;
            }
        }

       
        $policy = (string) ($gs->gender_policy ?? 'mixed_open');
        // =========================
        // ✅ mixed_5050 meta (M/F counts + limit)
        // =========================
        $g5050Limit = null;
        $g5050Male  = null;
        $g5050Female = null;
        
        if ($policy === 'mixed_5050' && $maxPlayers > 0) {
            $g5050Limit = intdiv((int)$maxPlayers, 2);
        
            // считаем активные регистрации по полу
            $maleVals   = ['m','male'];
            $femaleVals = ['f','female'];
        
            $qMale = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id');
        
            $qFemale = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id');
        
            if ($hasOccId) {
                $qMale->where('er.occurrence_id', (int)$occurrence->id);
                $qFemale->where('er.occurrence_id', (int)$occurrence->id);
            } elseif ($hasEventId) {
                $qMale->where('er.event_id', (int)$event->id);
                $qFemale->where('er.event_id', (int)$event->id);
            } else {
                $qMale->whereRaw('1=0');
                $qFemale->whereRaw('1=0');
            }
        
            $qMale->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $maleVals);
            $qFemale->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $femaleVals);
        
            // только активные
            $this->applyActiveScope($qMale, $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
            $this->applyActiveScope($qFemale, $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
        
            $g5050Male   = (int)$qMale->count();
            $g5050Female = (int)$qFemale->count();
        }

        $needProfileGender = (bool) ($viewer && !$viewerGenderMF);

        $genderBlocked = false;
        if ($viewer && $viewerGenderMF) {
            if ($policy === 'only_male' && $viewerGenderMF !== 'm') $genderBlocked = true;
            if ($policy === 'only_female' && $viewerGenderMF !== 'f') $genderBlocked = true;
        }

        $limitedSide = (string) ($gs->gender_limited_side ?? '');
        $limitedMax  = is_null($gs->gender_limited_max) ? null : (int) $gs->gender_limited_max;

        $limitedPositions = $gs->gender_limited_positions;
        if (is_string($limitedPositions)) {
            $decoded = json_decode($limitedPositions, true);
            $limitedPositions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($limitedPositions)) $limitedPositions = [];
        $limitedPositions = array_values(array_unique(array_map('strval', $limitedPositions)));

        $isLimitedUser = false;
        if ($viewer && $viewerGenderMF && $policy === 'mixed_limited' && in_array($limitedSide, ['male', 'female'], true)) {
            $isLimitedUser = ($limitedSide === 'male' && $viewerGenderMF === 'm')
                || ($limitedSide === 'female' && $viewerGenderMF === 'f');
        }

        $visiblePositions = $positions;
        if ($viewer && $isLimitedUser && !empty($limitedPositions)) {
            $visiblePositions = array_values(array_filter(
                $positions,
                fn ($p) => in_array((string) $p, $limitedPositions, true)
            ));
        }

        $limitedTaken = 0;
        if ($policy === 'mixed_limited' && $limitedMax !== null && $limitedMax >= 0 && in_array($limitedSide, ['male', 'female'], true)) {
            $needMF = ($limitedSide === 'male') ? 'm' : 'f';
            $gVals  = $this->allowedGenderDBValues($needMF);

            $q = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id')
                ->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $gVals);

            if ($hasOccId) {
                $q->where('er.occurrence_id', (int) $occurrence->id);
            } elseif ($hasEventId) {
                $q->where('er.event_id', (int) $event->id);
            } else {
                $q->whereRaw('1=0');
            }

            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
            $limitedTaken = (int) $q->count();
        }

        $limitedRemaining = null;
        if ($limitedMax !== null && $limitedMax >= 0) {
            $limitedRemaining = max(0, $limitedMax - $limitedTaken);
        }

        $freePositions = [];
        foreach ($visiblePositions as $p) {
            $key  = (string) $p;
            $cap  = (int) ($capacityByPos[$key] ?? 0);
            $taken = (int) ($byPos[$key] ?? 0);

            $free = max(0, $cap - $taken);

            if ($viewer && $isLimitedUser && $limitedRemaining !== null) {
                $free = min($free, $limitedRemaining);
            }

            if ($viewer && $viewerGenderMF && $genderBlocked) {
                $free = 0;
            }

            $freePositions[] = [
                'key'   => $key,
                'label' => $this->positionLabel($key),
                'free'  => $free,
            ];
        }

        $freePositions = array_values(array_filter($freePositions, fn ($x) => (int) ($x['free'] ?? 0) > 0));

        return response()->json([
            'ok' => true,
            'meta' => [
                'max_players'              => $maxPlayers,
                'registered_total'         => $registeredTotal,
                'remaining_total'          => $remainingTotal,
                'team_size'                => $teamSize,
                'teams_count'              => $teamsCount,
                'need_profile_gender'      => $needProfileGender,
                'gender_blocked'           => $genderBlocked,
                'gender_policy'            => $policy,
                'gender_5050_limit'        => $g5050Limit,
                'gender_5050_male'         => $g5050Male,
                'gender_5050_female'       => $g5050Female,

                'gender_limited_side'      => $limitedSide,
                'gender_limited_max'       => $limitedMax,
                'gender_limited_taken'     => $limitedTaken,
                'gender_limited_remaining' => $limitedRemaining,
                'age_policy'               => $agePolicy,
                'need_profile_birthdate'   => $needBirthdate,
                'age_blocked'              => $ageBlocked,
            ],
            'free_positions' => $freePositions,
        ]);
    }

    // ================= Helpers =================
    private function fmtInTz(?Carbon $dtUtc, string $tz): ?string
    {
        if (!$dtUtc) return null;
        return $dtUtc->copy()->utc()->setTimezone($tz)->format('d.m.Y H:i');
    }
    
    private function fmtUtc(?Carbon $dtUtc): ?string
    {
        if (!$dtUtc) return null;
        return $dtUtc->copy()->utc()->format('d.m.Y H:i') . ' UTC';
    }

    private function applyLevelFilterVariantB($q, string $direction, ?int $level): void
    {
        if (is_null($level)) return;

        // защитимся, если колонок уровней нет (иначе SQL упадёт)
        $hasClassicMin = Schema::hasColumn('events', 'classic_level_min');
        $hasClassicMax = Schema::hasColumn('events', 'classic_level_max');
        $hasBeachMin   = Schema::hasColumn('events', 'beach_level_min');
        $hasBeachMax   = Schema::hasColumn('events', 'beach_level_max');

        // если вообще ничего нет — не фильтруем
        if (!$hasClassicMin && !$hasClassicMax && !$hasBeachMin && !$hasBeachMax) {
            return;
        }

        $applyClassic = function ($qq) use ($level, $hasClassicMin, $hasClassicMax) {
            $qq->where(function ($w) use ($level, $hasClassicMin, $hasClassicMax) {
                if ($hasClassicMin) {
                    $w->where(function ($x) use ($level) {
                        $x->whereNull('classic_level_min')
                            ->orWhere('classic_level_min', '<=', $level);
                    });
                }
                if ($hasClassicMax) {
                    $w->where(function ($x) use ($level) {
                        $x->whereNull('classic_level_max')
                            ->orWhere('classic_level_max', '>=', $level);
                    });
                }
            });
        };

        $applyBeach = function ($qq) use ($level, $hasBeachMin, $hasBeachMax) {
            $qq->where(function ($w) use ($level, $hasBeachMin, $hasBeachMax) {
                if ($hasBeachMin) {
                    $w->where(function ($x) use ($level) {
                        $x->whereNull('beach_level_min')
                            ->orWhere('beach_level_min', '<=', $level);
                    });
                }
                if ($hasBeachMax) {
                    $w->where(function ($x) use ($level) {
                        $x->whereNull('beach_level_max')
                            ->orWhere('beach_level_max', '>=', $level);
                    });
                }
            });
        };

        // если direction выбран явно — фильтруем только по нему
        if ($direction === 'beach') {
            $applyBeach($q);
            return;
        }
        if ($direction === 'classic') {
            $applyClassic($q);
            return;
        }

        // ✅ ВАРИАНТ B: direction = '' (Все) => classic подходит ИЛИ beach подходит
        $q->where(function ($outer) use ($applyClassic, $applyBeach) {
            $outer->where(function ($classic) use ($applyClassic) {
                $applyClassic($classic);
            })->orWhere(function ($beach) use ($applyBeach) {
                $applyBeach($beach);
            });
        });
    }

    private function applyActiveScope($q, bool $hasCancelledAt, bool $hasIsCancelled, bool $hasStatus, string $prefix = ''): void
    {
        if ($hasIsCancelled) {
            $q->where($prefix . 'is_cancelled', false);
        }
        if ($hasCancelledAt) {
            $q->whereNull($prefix . 'cancelled_at');
        }
        if ($hasStatus) {
            $q->where($prefix . 'status', 'confirmed');
        }
        if (Schema::hasColumn('event_registrations', 'deleted_at')) {
            $q->whereNull($prefix . 'deleted_at');
        }
    }

    protected function joinedAndRestrictedEventIds(bool $hasCancelledAt, bool $hasIsCancelled, bool $hasStatus): array
    {
        $user = auth()->user();
        if (!$user) return [[], []];

        $userId = (int) $user->id;

        $joinedEventIds = [];
        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'event_id')) {
            $q = DB::table('event_registrations')->where('user_id', $userId);
            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus);

            $joinedEventIds = $q->pluck('event_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        $restrictedEventIds = [];
        if (Schema::hasTable('events')) {
            $hasIsPrivate  = Schema::hasColumn('events', 'is_private');
            $hasVisibility = Schema::hasColumn('events', 'visibility');

            if ($hasIsPrivate || $hasVisibility) {
                $restrictedQ = DB::table('events')
                    ->select('id')
                    ->where(function ($w) use ($hasIsPrivate, $hasVisibility) {
                        if ($hasIsPrivate)  $w->orWhere('is_private', 1);
                        if ($hasVisibility) $w->orWhere('visibility', 'private');
                    });

                $this->applyPrivateVisibilityNegationScope($restrictedQ, $user);

                $restrictedEventIds = $restrictedQ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [$joinedEventIds, $restrictedEventIds];
    }

    protected function joinedAndRestrictedOccurrenceIds(bool $hasCancelledAt, bool $hasIsCancelled, bool $hasStatus): array
    {
        $user = auth()->user();
        if (!$user) return [[], []];

        $userId = (int) $user->id;

        $joinedOccurrenceIds = [];
        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q = DB::table('event_registrations')
                ->where('user_id', $userId)
                ->whereNotNull('occurrence_id');

            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus);

            $joinedOccurrenceIds = $q->pluck('occurrence_id')
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        $restrictedOccurrenceIds = [];
        if (Schema::hasTable('event_occurrences')) {
            $hasIsPrivate  = Schema::hasColumn('events', 'is_private');
            $hasVisibility = Schema::hasColumn('events', 'visibility');

            if ($hasIsPrivate || $hasVisibility) {
                $restrictedQ = DB::table('event_occurrences as eo')
                    ->join('events as e', 'e.id', '=', 'eo.event_id')
                    ->select('eo.id')
                    ->where(function ($w) use ($hasIsPrivate, $hasVisibility) {
                        if ($hasIsPrivate)  $w->orWhere('e.is_private', 1);
                        if ($hasVisibility) $w->orWhere('e.visibility', 'private');
                    });

                $this->applyPrivateVisibilityNegationScope($restrictedQ, $user, 'e.');

                $restrictedOccurrenceIds = $restrictedQ->pluck('eo.id')
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return [$joinedOccurrenceIds, $restrictedOccurrenceIds];
    }

    protected function buildAvailabilityForEvent(Event $event): array
    {
        $gs = $event->gameSettings;
        $maxPlayers = (int) ($gs->max_players ?? 0);

        return [
            'max_players'      => $maxPlayers,
            'registered_total' => 0,
            'remaining_total'  => $maxPlayers,
            'free_positions'   => [],
            'meta' => [
                'max_players'      => $maxPlayers,
                'registered_total' => 0,
                'remaining_total'  => $maxPlayers,
            ],
        ];
    }

    private function getOrCreateFirstOccurrenceForEvent(Event $event): ?EventOccurrence
    {
        if (!Schema::hasTable('event_occurrences')) return null;

        $occ = EventOccurrence::query()
            ->where('event_id', (int) $event->id)
            ->orderBy('starts_at', 'asc')
            ->first();

        if ($occ) return $occ;
        if (!$event->starts_at) return null;

        $startUtc = Carbon::parse($event->starts_at, 'UTC');
        $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";

        $gs = $event->gameSettings; // может быть null

        return EventOccurrence::query()->updateOrCreate(
            ['uniq_key' => $uniq],
            [
                'event_id'  => (int) $event->id,
                'starts_at' => $startUtc,
                'ends_at'   => $event->ends_at ? Carbon::parse($event->ends_at, 'UTC') : null,
                'timezone'  => $event->timezone ?: 'UTC',
        
                // ✅ snapshot поля
                'location_id'         => $event->location_id ?? null,
                'allow_registration'  => $event->allow_registration ?? null,
                'max_players'         => $gs?->max_players ?? null,
                // ✅ NEW: age + climate snapshot
                'age_policy'          => $event->age_policy ?? 'any',   // adult|child|any
                'is_snow'             => $event->is_snow ?? null,       // bool|null
        
                'classic_level_min'   => $event->classic_level_min ?? null,
                'classic_level_max'   => $event->classic_level_max ?? null,
                'beach_level_min'     => $event->beach_level_min ?? null,
                'beach_level_max'     => $event->beach_level_max ?? null,
        
                // ✅ рег-окно для этого occurrence (если у event уже посчитано — ок как fallback)
                'registration_starts_at' => $event->registration_starts_at ?? null,
                'registration_ends_at'   => $event->registration_ends_at ?? null,
                'cancel_self_until'      => $event->cancel_self_until ?? null,
            ]
        );

    }

    private function positionLabel(string $key): string
    {
        return match ($key) {
            'setter'   => 'Связующий',
            'outside'  => 'Доигровщик',
            'opposite' => 'Диагональный',
            'middle'   => 'Центральный',
            'libero'   => 'Либеро',
            default    => $key,
        };
    }

    private function teamMeta(string $subtype, string $liberoMode): array
    {
        $subtype = trim($subtype);
        $liberoMode = trim($liberoMode);

        if ($subtype === '4x4') {
            return ['team_size' => 4, 'per_team' => ['setter' => 1, 'outside' => 2, 'opposite' => 1]];
        }
        if ($subtype === '4x2') {
            return ['team_size' => 6, 'per_team' => ['setter' => 1, 'outside' => 4]];
        }
        if ($subtype === '5x1') {
            $teamSize = ($liberoMode === 'with_libero') ? 7 : 6;
            $perTeam  = ['setter' => 1, 'outside' => 2, 'opposite' => 1, 'middle' => 2];
            if ($liberoMode === 'with_libero') $perTeam['libero'] = 1;
            return ['team_size' => $teamSize, 'per_team' => $perTeam];
        }

        return ['team_size' => 0, 'per_team' => []];
    }

    private function normalizeGenderToMF(?string $g): ?string
    {
        $g = strtolower(trim((string) $g));
        if ($g === '') return null;
        if (in_array($g, ['m', 'male', 'man'], true)) return 'm';
        if (in_array($g, ['f', 'female', 'woman'], true)) return 'f';
        return null;
    }

    private function allowedGenderDBValues(string $need): array
    {
        return $need === 'm' ? ['m', 'male'] : ['f', 'female'];
    }

    // ---------------- PRIVATE VISIBILITY HELPERS ----------------

    private function isPrivateEventRow(Event $event): bool
    {
        $isPrivate = false;

        if (Schema::hasColumn('events', 'is_private')) {
            $isPrivate = $isPrivate || ((int) ($event->is_private ?? 0) === 1);
        }
        if (Schema::hasColumn('events', 'visibility')) {
            $isPrivate = $isPrivate || ((string) ($event->visibility ?? '') === 'private');
        }

        return $isPrivate;
    }

    private function canViewPrivateEvent(Event $event, ?User $user): bool
    {
        if (!$this->isPrivateEventRow($event)) return true;
        if (!$user) return false;

        $role = strtolower(trim((string) ($user->role ?? 'user')));
        if (in_array($role, ['admin', 'superadmin', 'owner', 'root'], true)) return true;

        if (Schema::hasColumn('events', 'organizer_id')) {
            if ((int) ($event->organizer_id ?? 0) === (int) $user->id) return true;
        }

        if ($role === 'staff' && Schema::hasColumn('events', 'organizer_id')) {
            $organizerId = (int) ($event->organizer_id ?? 0);
            if ($organizerId > 0 && $this->isStaffOfOrganizer((int) $user->id, $organizerId)) return true;
        }

        foreach (['created_by', 'creator_user_id', 'created_user_id'] as $col) {
            if (Schema::hasColumn('events', $col) && (int) ($event->{$col} ?? 0) === (int) $user->id) {
                return true;
            }
        }

        return false;
    }

    // ✅ админ видит всё + public = (not private) AND (visibility != private) + поддержка prefix
    private function applyPrivateVisibilityScope($q, ?User $user, string $prefix = 'events.'): void
    {
        $hasIsPrivate  = Schema::hasColumn('events', 'is_private');
        $hasVisibility = Schema::hasColumn('events', 'visibility');
        if (!$hasIsPrivate && !$hasVisibility) return;

        if ($user) {
            $role = strtolower(trim((string) ($user->role ?? 'user')));
            if (in_array($role, ['admin', 'superadmin', 'owner', 'root'], true)) {
                return; // админские роли — ничего не режем
            }
        }

        $q->where(function ($w) use ($user, $hasIsPrivate, $hasVisibility, $prefix) {
            // public
            $w->where(function ($pub) use ($hasIsPrivate, $hasVisibility, $prefix) {
                if ($hasIsPrivate) {
                    $pub->where(function ($x) use ($prefix) {
                        $x->where($prefix . 'is_private', 0)
                            ->orWhereNull($prefix . 'is_private');
                    });
                }
                if ($hasVisibility) {
                    $pub->where(function ($x) use ($prefix) {
                        $x->where($prefix . 'visibility', '!=', 'private')
                            ->orWhereNull($prefix . 'visibility');
                    });
                }
            });

            // private -> allow for organizer/staff/creator
            if ($user) {
                $role = strtolower(trim((string) ($user->role ?? 'user')));

                if (Schema::hasColumn('events', 'organizer_id')) {
                    $w->orWhere($prefix . 'organizer_id', (int) $user->id);

                    if ($role === 'staff') {
                        $orgIds = $this->staffOrganizerIds((int) $user->id);
                        if (!empty($orgIds)) {
                            $w->orWhereIn($prefix . 'organizer_id', $orgIds);
                        }
                    }
                }

                foreach (['created_by', 'creator_user_id', 'created_user_id'] as $col) {
                    if (Schema::hasColumn('events', $col)) {
                        $w->orWhere($prefix . $col, (int) $user->id);
                    }
                }
            }
        });
    }

    private function applyPrivateVisibilityNegationScope($q, ?User $user, string $prefix = ''): void
    {
        if (!$user) return;

        $role = strtolower(trim((string) ($user->role ?? 'user')));
        if (in_array($role, ['admin', 'superadmin', 'owner', 'root'], true)) {
            $q->whereRaw('1=0'); // для restricted-списка: админ НЕ имеет restricted
            return;
        }

        if (Schema::hasColumn('events', 'organizer_id')) {
            $q->where($prefix . 'organizer_id', '!=', (int) $user->id);

            if ($role === 'staff') {
                $organizerIds = $this->staffOrganizerIds((int) $user->id);
                if (!empty($organizerIds)) {
                    $q->whereNotIn($prefix . 'organizer_id', $organizerIds);
                }
            }
        }
    }

    private function isStaffOfOrganizer(int $staffUserId, int $organizerId): bool
    {
        $ids = $this->staffOrganizerIds($staffUserId);
        return in_array($organizerId, $ids, true);
    }

    private function staffOrganizerIds(int $staffUserId): array
    {
        $candidates = [
            ['table' => 'organizer_staff', 'org' => 'organizer_id', 'staff' => 'staff_user_id'],
            ['table' => 'organizer_staff', 'org' => 'organizer_id', 'staff' => 'user_id'],
            ['table' => 'organizer_staff', 'org' => 'organizer_id', 'staff' => 'staff_id'],
            ['table' => 'organizer_staff', 'org' => 'organizer_id', 'staff' => 'staff_user'],
        ];

        foreach ($candidates as $c) {
            if (!Schema::hasTable($c['table'])) continue;
            if (!Schema::hasColumn($c['table'], $c['org'])) continue;
            if (!Schema::hasColumn($c['table'], $c['staff'])) continue;

            return DB::table($c['table'])
                ->where($c['staff'], $staffUserId)
                ->pluck($c['org'])
                ->map(fn ($v) => (int) $v)
                ->unique()
                ->values()
                ->all();
        }

        if (Schema::hasColumn('users', 'organizer_id')) {
            $orgId = (int) DB::table('users')->where('id', $staffUserId)->value('organizer_id');
            return $orgId > 0 ? [$orgId] : [];
        }

        return [];
    }
}
