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
        // ✅ безопасно: сначала проверяем таблицу
        $hasRegTable    = Schema::hasTable('event_registrations');
        $hasCancelledAt = $hasRegTable && Schema::hasColumn('event_registrations', 'cancelled_at');
        $hasIsCancelled = $hasRegTable && Schema::hasColumn('event_registrations', 'is_cancelled');
        $hasStatus      = $hasRegTable && Schema::hasColumn('event_registrations', 'status');

        if (Schema::hasTable('event_occurrences')) {
            $occQ = EventOccurrence::query()
                ->with([
                    'event.location:id,name,city,address',
                    'event.gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
                    'event.media',
                ])
                ->orderBy('starts_at', 'asc');

            $occQ->whereHas('event', function ($q) {
                $q->where(function ($w) {
                    $w->where('events.allow_registration', true)
                        ->orWhere(function ($w2) {
                            $w2->where('events.is_paid', true)
                                ->where(function ($w3) {
                                    $w3->whereNull('events.allow_registration')
                                        ->orWhere('events.allow_registration', false);
                                });
                        });
                });
            });

            $occurrences = $occQ->get();

            // ✅ trainers map (без зависимостей от relations)
            $trainerCol = Schema::hasColumn('events', 'trainer_user_id')
                ? 'trainer_user_id'
                : (Schema::hasColumn('events', 'trainer_id') ? 'trainer_id' : null);

            $trainerIds = [];
            if ($trainerCol) {
                foreach ($occurrences as $occ) {
                    $e = $occ->event;
                    if (!$e) continue;
                    $tid = (int)($e->{$trainerCol} ?? 0);
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

        $events = Event::query()
            ->with([
                'location:id,name,city,address',
                'gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
                'media',
            ])
            ->where(function ($w) {
                $w->where('events.allow_registration', true)
                    ->orWhere(function ($w2) {
                        $w2->where('events.is_paid', true)
                            ->where(function ($w3) {
                                $w3->whereNull('events.allow_registration')
                                    ->orWhere('events.allow_registration', false);
                            });
                    });
            })
            ->orderByDesc('id')
            ->get();

        // ✅ trainers map for legacy list
        $trainerCol = Schema::hasColumn('events', 'trainer_user_id')
            ? 'trainer_user_id'
            : (Schema::hasColumn('events', 'trainer_id') ? 'trainer_id' : null);

        $trainerIds = [];
        if ($trainerCol) {
            foreach ($events as $e) {
                $tid = (int)($e->{$trainerCol} ?? 0);
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
        $event->load([
            'location:id,name,city,address',
            'gameSettings:event_id,subtype,libero_mode,max_players,positions,gender_policy,gender_limited_side,gender_limited_max,gender_limited_positions,allow_girls,girls_max',
            'media',
            // ✅ trainer relation (вариант A)
            'trainer_user:id,name,email,nickname,username,phone',
        ]);

        $occurrenceId = (int)$request->query('occurrence', 0);
        $occurrence = null;

        if (Schema::hasTable('event_occurrences')) {
            if ($occurrenceId > 0) {
                $occurrence = EventOccurrence::query()
                    ->where('id', $occurrenceId)
                    ->where('event_id', (int)$event->id)
                    ->first();
            }
            if (!$occurrence) {
                $occurrence = EventOccurrence::query()
                    ->where('event_id', (int)$event->id)
                    ->orderBy('starts_at', 'asc')
                    ->first();
            }
        }

        $availability = $this->buildAvailabilityForEvent($event);
        if ($occurrence) {
            $payload = $this->availabilityOccurrence($occurrence)->getData(true);
            $availability = [
                'max_players'       => (int)($payload['meta']['max_players'] ?? 0),
                'registered_total'  => (int)($payload['meta']['registered_total'] ?? 0),
                'remaining_total'   => (int)($payload['meta']['remaining_total'] ?? 0),
                'free_positions'    => $payload['free_positions'] ?? [],
                'meta'              => $payload['meta'] ?? [],
            ];
        }

        // ✅ убрали ручной trainerUser/trainerColumn — blade использует $event->trainer_user
        return view('events.show', [
            'event'        => $event,
            'occurrence'   => $occurrence,
            'availability' => $availability,
        ]);
    }

    public function availability(Event $event)
    {
        $occ = $this->getOrCreateFirstOccurrenceForEvent($event);
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

        $occurrence->load(['event.gameSettings']);
        $event = $occurrence->event;
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Событие для occurrence не найдено.'], 404);
        }

        $gs = $event->gameSettings;
        $maxPlayers = (int)($gs->max_players ?? 0);

        if (!(bool)$event->allow_registration) {
            return response()->json([
                'ok' => true,
                'meta' => [
                    'max_players'       => $maxPlayers,
                    'registered_total'  => 0,
                    'remaining_total'   => $maxPlayers,
                ],
                'free_positions' => [],
            ]);
        }

        $positions = $gs?->positions;
        if (is_string($positions)) {
            $decoded = json_decode($positions, true);
            $positions = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($positions)) $positions = [];
        $positions = array_values(array_unique(array_map('strval', $positions)));

        $totalQ = DB::table('event_registrations')
            ->where('occurrence_id', (int)$occurrence->id);
        $this->applyActiveScope($totalQ, $hasCancelledAt, $hasIsCancelled, $hasStatus);
        $registeredTotal = (int)$totalQ->count();
        $remainingTotal = max(0, $maxPlayers - $registeredTotal);

        if ($maxPlayers <= 0 || empty($positions)) {
            return response()->json([
                'ok' => true,
                'meta' => [
                    'max_players'       => $maxPlayers,
                    'registered_total'  => $registeredTotal,
                    'remaining_total'   => $remainingTotal,
                ],
                'free_positions' => [],
            ]);
        }

        $byPosQ = DB::table('event_registrations')
            ->where('occurrence_id', (int)$occurrence->id);
        $this->applyActiveScope($byPosQ, $hasCancelledAt, $hasIsCancelled, $hasStatus);
        $byPos = $byPosQ
            ->select('position', DB::raw('COUNT(*) as cnt'))
            ->groupBy('position')
            ->pluck('cnt', 'position')
            ->toArray();

        $team = $this->teamMeta(
            (string)($gs->subtype ?? ''),
            (string)($gs->libero_mode ?? '')
        );
        $teamSize = (int)($team['team_size'] ?? 0);
        $perTeamCounts = (array)($team['per_team'] ?? []);
        $teamsCount = ($teamSize > 0) ? intdiv($maxPlayers, $teamSize) : 0;
        if ($teamsCount < 1) $teamsCount = 1;

        $capacityByPos = [];
        foreach ($perTeamCounts as $posKey => $cntPerTeam) {
            $capacityByPos[(string)$posKey] = max(0, (int)$cntPerTeam) * $teamsCount;
        }

        $viewer = auth()->user();
        $viewerGenderMF = $this->normalizeGenderToMF($viewer?->gender ?? null);
        $policy = (string)($gs->gender_policy ?? 'mixed_open');
        $needProfileGender = (bool)($viewer && !$viewerGenderMF);

        $genderBlocked = false;
        if ($viewer && $viewerGenderMF) {
            if ($policy === 'only_male' && $viewerGenderMF !== 'm') $genderBlocked = true;
            if ($policy === 'only_female' && $viewerGenderMF !== 'f') $genderBlocked = true;
        }

        $limitedSide = (string)($gs->gender_limited_side ?? '');
        $limitedMax  = is_null($gs->gender_limited_max) ? null : (int)$gs->gender_limited_max;

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
            $visiblePositions = array_values(array_filter($positions, fn($p) => in_array((string)$p, $limitedPositions, true)));
        }

        $limitedTaken = 0;
        if ($policy === 'mixed_limited' && $limitedMax !== null && $limitedMax >= 0 && in_array($limitedSide, ['male', 'female'], true)) {
            $needMF = ($limitedSide === 'male') ? 'm' : 'f';
            $gVals = $this->allowedGenderDBValues($needMF);
            $q = DB::table('event_registrations as er')
                ->join('users as u', 'u.id', '=', 'er.user_id')
                ->where('er.occurrence_id', (int)$occurrence->id)
                ->whereIn(DB::raw("LOWER(COALESCE(u.gender,''))"), $gVals);
            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus, 'er.');
            $limitedTaken = (int)$q->count();
        }

        $limitedRemaining = null;
        if ($limitedMax !== null && $limitedMax >= 0) {
            $limitedRemaining = max(0, $limitedMax - $limitedTaken);
        }

        $freePositions = [];
        foreach ($visiblePositions as $p) {
            $key = (string)$p;
            $cap = (int)($capacityByPos[$key] ?? 0);
            $taken = (int)($byPos[$key] ?? 0);
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

        $freePositions = array_values(array_filter($freePositions, fn($x) => (int)($x['free'] ?? 0) > 0));

        return response()->json([
            'ok' => true,
            'meta' => [
                'max_players'        => $maxPlayers,
                'registered_total'   => $registeredTotal,
                'remaining_total'    => $remainingTotal,
                'team_size'          => $teamSize,
                'teams_count'        => $teamsCount,
                'need_profile_gender'=> $needProfileGender,
                'gender_blocked'     => $genderBlocked,
                'gender_policy'      => $policy,
                'gender_limited_side'=> $limitedSide,
                'gender_limited_max' => $limitedMax,
                'gender_limited_taken' => $limitedTaken,
                'gender_limited_remaining' => $limitedRemaining,
            ],
            'free_positions' => $freePositions,
        ]);
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
        $userId = (int)$user->id;
        $role = (string)($user->role ?? 'user');
        $isPrivileged = in_array($role, ['admin'], true);

        $joinedEventIds = [];
        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'event_id')) {
            $q = DB::table('event_registrations')->where('user_id', $userId);
            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus);
            $joinedEventIds = $q->pluck('event_id')
                ->map(fn($v) => (int)$v)
                ->unique()
                ->values()
                ->all();
        }

        $restrictedEventIds = [];
        if (!$isPrivileged && Schema::hasTable('events')) {
            $restrictedQ = DB::table('events')->select('id');
            $hasIsPrivate = Schema::hasColumn('events', 'is_private');
            $hasVisibility = Schema::hasColumn('events', 'visibility');

            if ($hasIsPrivate || $hasVisibility) {
                $restrictedQ->where(function ($w) use ($hasIsPrivate, $hasVisibility) {
                    if ($hasIsPrivate) $w->orWhere('is_private', 1);
                    if ($hasVisibility) $w->orWhere('visibility', 'private');
                });

                if (!empty($joinedEventIds)) $restrictedQ->whereNotIn('id', $joinedEventIds);

                $restrictedEventIds = $restrictedQ->pluck('id')
                    ->map(fn($v) => (int)$v)
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
        $userId = (int)$user->id;
        $role = (string)($user->role ?? 'user');
        $isPrivileged = in_array($role, ['admin'], true);

        $joinedOccurrenceIds = [];
        if (Schema::hasTable('event_registrations') && Schema::hasColumn('event_registrations', 'occurrence_id')) {
            $q = DB::table('event_registrations')
                ->where('user_id', $userId)
                ->whereNotNull('occurrence_id');
            $this->applyActiveScope($q, $hasCancelledAt, $hasIsCancelled, $hasStatus);
            $joinedOccurrenceIds = $q->pluck('occurrence_id')
                ->map(fn($v) => (int)$v)
                ->unique()
                ->values()
                ->all();
        }

        $restrictedOccurrenceIds = [];
        if (!$isPrivileged && Schema::hasTable('event_occurrences')) {
            $restrictedQ = DB::table('event_occurrences as eo')
                ->join('events as e', 'e.id', '=', 'eo.event_id')
                ->select('eo.id');

            $hasIsPrivate = Schema::hasColumn('events', 'is_private');
            $hasVisibility = Schema::hasColumn('events', 'visibility');

            if ($hasIsPrivate || $hasVisibility) {
                $restrictedQ->where(function ($w) use ($hasIsPrivate, $hasVisibility) {
                    if ($hasIsPrivate) $w->orWhere('e.is_private', 1);
                    if ($hasVisibility) $w->orWhere('e.visibility', 'private');
                });

                if (!empty($joinedOccurrenceIds)) $restrictedQ->whereNotIn('eo.id', $joinedOccurrenceIds);

                $restrictedOccurrenceIds = $restrictedQ->pluck('eo.id')
                    ->map(fn($v) => (int)$v)
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
        $maxPlayers = (int)($gs->max_players ?? 0);
        return [
            'max_players'       => $maxPlayers,
            'registered_total'  => 0,
            'remaining_total'   => $maxPlayers,
            'free_positions'    => [],
            'meta'              => [
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
            ->where('event_id', (int)$event->id)
            ->orderBy('starts_at', 'asc')
            ->first();

        if ($occ) return $occ;
        if (!$event->starts_at) return null;

        $startUtc = Carbon::parse($event->starts_at, 'UTC');
        $uniq = "event:{$event->id}:{$startUtc->format('YmdHis')}";

        return EventOccurrence::query()->updateOrCreate(
            ['uniq_key' => $uniq],
            [
                'event_id'  => (int)$event->id,
                'starts_at' => $startUtc,
                'ends_at'   => $event->ends_at ? Carbon::parse($event->ends_at, 'UTC') : null,
                'timezone'  => $event->timezone ?: 'UTC',
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
            return [
                'team_size' => 4,
                'per_team'  => ['setter' => 1, 'outside' => 2, 'opposite' => 1],
            ];
        }
        if ($subtype === '4x2') {
            return [
                'team_size' => 6,
                'per_team'  => ['setter' => 1, 'outside' => 4],
            ];
        }
        if ($subtype === '5x1') {
            $teamSize = ($liberoMode === 'with_libero') ? 7 : 6;
            $perTeam  = ['setter' => 1, 'outside' => 2, 'opposite' => 1, 'middle' => 2];
            if ($liberoMode === 'with_libero') $perTeam['libero'] = 1;
            return [
                'team_size' => $teamSize,
                'per_team'  => $perTeam,
            ];
        }
        return ['team_size' => 0, 'per_team' => []];
    }

    private function normalizeGenderToMF(?string $g): ?string
    {
        $g = strtolower(trim((string)$g));
        if ($g === '') return null;
        if (in_array($g, ['m', 'male', 'man'], true)) return 'm';
        if (in_array($g, ['f', 'female', 'woman'], true)) return 'f';
        return null;
    }

    private function allowedGenderDBValues(string $need): array
    {
        return $need === 'm' ? ['m', 'male'] : ['f', 'female'];
    }
}
