<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EventIndexService
{
    private EventVisibilityService $visibility;

    private bool $hasOccurrences;
    private bool $hasRegistrations;
    private bool $hasCancelledAt;
    private bool $hasIsCancelled;
    private bool $hasStatus;

    private array $locationColumns;

    public function __construct(EventVisibilityService $visibility)
    {
        $this->visibility = $visibility;

        $this->hasOccurrences   = Schema::hasTable('event_occurrences');
        $this->hasRegistrations = Schema::hasTable('event_registrations');

        $this->hasCancelledAt = $this->hasRegistrations && Schema::hasColumn('event_registrations', 'cancelled_at');
        $this->hasIsCancelled = $this->hasRegistrations && Schema::hasColumn('event_registrations', 'is_cancelled');
        $this->hasStatus      = $this->hasRegistrations && Schema::hasColumn('event_registrations', 'status');

        $this->locationColumns = $this->detectLocationColumns();
    }
    private function applyActiveOccurrenceScope($query): void
    {
        if (Schema::hasColumn('event_occurrences', 'cancelled_at')) {
            $query->whereNull('cancelled_at');
        }
    
        if (Schema::hasColumn('event_occurrences', 'is_cancelled')) {
            $query->where(function ($q) {
                $q->whereNull('is_cancelled')
                  ->orWhere('is_cancelled', false);
            });
        }
    }
    /*
    |--------------------------------------------------------------------------
    | ENTRY POINT
    |--------------------------------------------------------------------------
    */

    public function handle(Request $request)
    {
        $user   = auth()->user();
        $userId = (int) ($user?->id ?? 0);

        $role = strtolower(trim((string) ($user?->role ?? 'user')));
        $isAdmin = in_array($role, ['admin','superadmin','owner','root'], true);

        $direction = trim((string) $request->query('direction',''));
        $format    = trim((string) $request->query('format',''));

        $levelRaw = $request->query('level');
        $level = ($levelRaw === null || $levelRaw === '') ? null : (int) $levelRaw;
        $location = trim((string) $request->query('location', ''));
        
        if (!in_array($direction, ['', 'classic', 'beach'], true)) {
            $direction = '';
        }

        if ($this->hasOccurrences) {
            return $this->occurrenceIndex($user, $userId, $isAdmin, $direction, $format, $level, $location);
        }

        return $this->legacyIndex($user, $userId, $isAdmin, $direction, $format, $level);
    }

    /*
    |--------------------------------------------------------------------------
    | OCCURRENCE INDEX
    |--------------------------------------------------------------------------
    */

    private function occurrenceIndex($user, int $userId, bool $isAdmin, string $direction, string $format, ?int $level, string $location = '')
    {
        $occQ = EventOccurrence::query()
            ->with([
                'event.location:' . implode(',', $this->locationColumns),
                'event.location.city:id,name,region,timezone',
                'event.gameSettings:event_id,subtype,libero_mode,max_players,reserve_players_max,gender_policy,gender_limited_side,gender_limited_reg_starts_days_before',
                'event.media'
            ]);
        
        $this->applyActiveOccurrenceScope($occQ);
        $this->applyDateFilter($occQ);
        
        $occQ->orderBy('starts_at', 'asc');

        // Фильтр по городу пользователя (по умолчанию)
        $cityParam = trim((string) request('city', ''));
        if ($cityParam !== 'all' && $user && $user->city_id) {
            $userCityId = (int) $user->city_id;
            $occQ->whereHas('event', function ($eq) use ($userCityId) {
                $eq->whereHas('location', function ($lq) use ($userCityId) {
                    $lq->where('city_id', $userCityId);
                });
            });
        }

        // Staff: получаем organizer_id своего организатора
        $staffOrganizerIds = [];
        if ($userId > 0 && $user && in_array($user->role ?? '', ['staff'], true)) {
            $staffOrgId = \DB::table('staff_assignments')
                ->where('staff_user_id', $userId)
                ->value('organizer_id');
            if ($staffOrgId) {
                $staffOrganizerIds = [$staffOrgId];
            }
        }

        // Приватные события к которым у пользователя есть доступ по токену
        $privateAccessEventIds = [];
        if ($userId > 0) {
            $privateAccessEventIds = \DB::table('event_private_accesses')
                ->where('user_id', $userId)
                ->pluck('event_id')
                ->map(fn($v) => (int)$v)
                ->all();
        }

        $occQ->whereHas('event', function ($q) use ($user,$userId,$isAdmin,$direction,$format,$level,$location,$staffOrganizerIds,$privateAccessEventIds) {

            if (!$isAdmin) {
                $q->where(function ($outer) use ($userId, $staffOrganizerIds, $privateAccessEventIds) {

                    // 1. Обычные публичные мероприятия с записью
                    $outer->where(function ($w) {
                        $w->where('allow_registration', true)
                          ->where('is_private', false);
                    });

                    // 2. Оплаченные рекламные (публичные)
                    $outer->orWhere(function ($w) {
                        $w->where('allow_registration', false)
                          ->where('is_private', false)
                          ->where(function ($ww) {
                              $ww->where('ad_payment_status', 'paid')
                                 ->orWhere(function ($old) {
                                     // Старые события до введения системы оплаты
                                     $old->whereNull('ad_payment_status')
                                         ->where('created_at', '<', '2026-04-13 00:00:00');
                                 });
                          });
                    });

                    // 3. Приватные события по которым был доступ по токену
                    if (!empty($privateAccessEventIds)) {
                        $outer->orWhereIn('id', $privateAccessEventIds);
                    }

                    // 4. Свои события (Organizer/Staff) — все включая приватные и неоплаченные
                    if ($userId > 0) {
                        if (\Schema::hasColumn('events', 'organizer_id')) {
                            $outer->orWhere('organizer_id', $userId);
                        }
                        foreach (['created_by', 'creator_user_id', 'created_user_id'] as $col) {
                            if (\Schema::hasColumn('events', $col)) {
                                $outer->orWhere($col, $userId);
                            }
                        }
                    }

                    // 5. Staff видит все события своего Organizer
                    if (!empty($staffOrganizerIds) && \Schema::hasColumn('events', 'organizer_id')) {
                        $outer->orWhereIn('organizer_id', $staffOrganizerIds);
                    }
                });
            }

            if ($direction !== '') {
                $q->where('direction', $direction);
            }

            if ($format !== '') {
                $q->where('format', $format);
            }
            
            $this->applyLevelFilterVariantB($q, $direction, $level);

            $this->visibility->applyPrivateVisibilityScope($q, $user, '');
            
            if ($location !== '') {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $location) . '%';
                $q->whereHas('location', function ($lq) use ($like) {
                    $lq->where(function ($w) use ($like) {
                        $w->where('name', 'ilike', $like)
                          ->orWhere('address', 'ilike', $like);
                    });
                });
            }
        });

        // Берём ближайшие 10 уникальных дат

        $offset = max(0, (int) request('offset', 0));
        
        $allDates = (clone $occQ)
            ->reorder()
            ->selectRaw('DATE(starts_at) as day')
            ->groupBy('day')
            ->orderBy('day')
            ->skip($offset)
            ->take(10)
            ->pluck('day');
        
        if ($allDates->isEmpty()) {
            $occurrences = $occQ->paginate(30);
        } else {
            $firstDate = $allDates->first();
            $lastDate  = $allDates->last();
            $occurrences = $occQ
                ->whereDate('starts_at', '>=', $firstDate)
                ->whereDate('starts_at', '<=', $lastDate)
                ->paginate(500);
        }

        /*
        |--------------------------------------------------------------------------
        | REGISTRATION SNAPSHOT
        |--------------------------------------------------------------------------
        */

        $occurrenceIds = $occurrences->pluck('id')->all();

        $registeredCounts = $this->registrationSnapshot($occurrenceIds);

        /*
        |--------------------------------------------------------------------------
        | USER REGISTRATIONS
        |--------------------------------------------------------------------------
        */

        $userRegistrations = $this->userRegistrations($user, $occurrenceIds);

        /*
        |--------------------------------------------------------------------------
        | ENRICH OCCURRENCES
        |--------------------------------------------------------------------------
        */

        foreach ($occurrences as $occ) {

            $registered = $registeredCounts[$occ->id] ?? 0;

            $maxPlayers =
                $occ->max_players
                ?? ($occ->event->gameSettings->max_players ?? 0);

            $occ->availability = [
                'registered' => $registered,
                'remaining'  => max(0, $maxPlayers - $registered),
                'max'        => $maxPlayers,
            ];

            $isJoined = in_array((int)$occ->id, $userRegistrations, true);

         
            $quick = app(\App\Services\EventRegistrationGuard::class)->quickCheck($user, $occ);
            
            $occ->join = !$quick->allowed
                ? (object)['allowed' => false, 'code' => $quick->code, 'message' => $quick->message]
                : (object)['allowed' => !$isJoined, 'code' => null,
                'message' => $isJoined ? 'Вы уже записаны.' : null];

            $occ->cancel = (object)[
                'allowed' => $isJoined,
                'message' => null,
            ];
        }

        [$joinedIds,$restrictedIds] = $this->joinedIds('occurrence_id');

        return view('events.index',[
            'occurrences' => $occurrences,
            'joinedOccurrenceIds' => $joinedIds,
            'restrictedOccurrenceIds' => $restrictedIds,
            'events' => collect(),
            'joinedEventIds' => [],
            'restrictedEventIds' => []
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTRATION SNAPSHOT
    |--------------------------------------------------------------------------
    */

    private function registrationSnapshot(array $occurrenceIds): array
    {
        if (!$this->hasRegistrations || empty($occurrenceIds)) {
            return [];
        }

        $rows = DB::table('event_registrations')
            ->selectRaw('occurrence_id, count(*) as registered')
            ->whereIn('occurrence_id', $occurrenceIds)
            ->when($this->hasCancelledAt, fn($q)=>$q->whereNull('cancelled_at'))
            ->when($this->hasIsCancelled, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('is_cancelled')
                      ->orWhere('is_cancelled', false);
                });
            })
            ->when($this->hasStatus, fn($q)=>$q->where('status','confirmed'))
            ->groupBy('occurrence_id')
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $result[(int)$row->occurrence_id] = (int)$row->registered;
        }

        return $result;
    }

    /*
    |--------------------------------------------------------------------------
    | USER REGISTRATIONS
    |--------------------------------------------------------------------------
    */

    private function userRegistrations($user, array $occurrenceIds): array
    {
        if (!$user || empty($occurrenceIds)) {
            return [];
        }

        $regIds = DB::table('event_registrations')
            ->where('user_id', $user->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->when($this->hasCancelledAt, fn($q) => $q->whereNull('cancelled_at'))
            ->when($this->hasIsCancelled, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('is_cancelled')
                      ->orWhere('is_cancelled', false);
                });
            })
            ->when($this->hasStatus, fn($q) => $q->where('status', 'confirmed'))
            ->pluck('occurrence_id')
            ->map(fn($v) => (int)$v);

        // Добавляем occurrence_id из командных турниров
        $teamIds = DB::table('event_team_members')
            ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
            ->where('event_team_members.user_id', $user->id)
            ->whereIn('event_team_members.confirmation_status', ['confirmed', 'joined'])
            ->whereIn('event_teams.status', ['draft', 'ready', 'pending_members', 'submitted', 'confirmed', 'approved'])
            ->whereIn('event_teams.occurrence_id', $occurrenceIds)
            ->pluck('event_teams.occurrence_id')
            ->map(fn($v) => (int)$v);

        return $regIds->merge($teamIds)->unique()->values()->all();
    }

    /*
    |--------------------------------------------------------------------------
    | LEGACY INDEX
    |--------------------------------------------------------------------------
    */

    private function legacyIndex($user,int $userId,bool $isAdmin,string $direction,string $format,?int $level)
    {
        $eventsQ = Event::query();

        if (!$isAdmin) {
            $eventsQ->where(function($q) {
                $q->where('allow_registration', true)
                  ->orWhere(function($w) {
                      $w->where('allow_registration', false)
                        ->where(function($ww) {
                            $ww->where('ad_payment_status', 'paid')
                               ->orWhereNull('ad_payment_status');
                        });
                  });
            });
        }

        if ($direction !== '') {
            $eventsQ->where('direction',$direction);
        }

        if ($format !== '') {
            $eventsQ->where('format',$format);
        }

        $this->applyLevelFilterVariantB($eventsQ,$direction,$level);

        $this->visibility->applyPrivateVisibilityScope($eventsQ,$user,'events.');

        $events = $eventsQ->orderByDesc('id')->get();

        [$joinedIds,$restrictedIds] = $this->joinedIds('event_id');

        return view('events.index',[
            'events'=>$events,
            'joinedEventIds'=>$joinedIds,
            'restrictedEventIds'=>$restrictedIds,
            'occurrences'=>collect(),
            'joinedOccurrenceIds'=>[],
            'restrictedOccurrenceIds'=>[]
        ]);
    }

   /*
    |--------------------------------------------------------------------------
    | DATE FILTER
    |--------------------------------------------------------------------------
    */
    private function applyDateFilter($query): void
    {
        if (!Schema::hasColumn('event_occurrences','starts_at')) {
            return;
        }
    
        $now = Carbon::now('UTC');
    
        /*
        |--------------------------------------------------------------------------
        | Если есть ends_at
        |--------------------------------------------------------------------------
        */
    
        if (Schema::hasColumn('event_occurrences','ends_at')) {
    
            $query->where(function($q) use ($now){
    
                $q->where(function($w) use ($now){
                    $w->whereNotNull('ends_at')
                      ->where('ends_at','>', $now);
                })
    
                ->orWhere(function($w) use ($now){
                    $w->whereNull('ends_at')
                      ->where('starts_at','>', $now);
                });
    
            });
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | Если ends_at нет — считаем через duration_sec
        |--------------------------------------------------------------------------
        */
    
        if (Schema::hasColumn('event_occurrences','duration_sec')) {
    
            $query->whereRaw(
                "starts_at + ((duration_sec) * interval '1 second') > ?",
                [$now]
            );
    
            return;
        }
    
        /*
        |--------------------------------------------------------------------------
        | fallback
        |--------------------------------------------------------------------------
        */
    
        $query->where('starts_at','>', $now);
    }
    /*
    |--------------------------------------------------------------------------
    | LEVEL FILTER
    |--------------------------------------------------------------------------
    */

    private function applyLevelFilterVariantB($q,string $direction,?int $level): void
    {
        if (is_null($level)) return;

        $hasClassicMin = Schema::hasColumn('events','classic_level_min');
        $hasClassicMax = Schema::hasColumn('events','classic_level_max');
        $hasBeachMin   = Schema::hasColumn('events','beach_level_min');
        $hasBeachMax   = Schema::hasColumn('events','beach_level_max');

        if (!$hasClassicMin && !$hasClassicMax && !$hasBeachMin && !$hasBeachMax) {
            return;
        }

        $applyClassic = function ($qq) use ($level,$hasClassicMin,$hasClassicMax) {
            if ($hasClassicMin) $qq->where('classic_level_min','<=',$level);
            if ($hasClassicMax) $qq->where('classic_level_max','>=',$level);
        };

        $applyBeach = function ($qq) use ($level,$hasBeachMin,$hasBeachMax) {
            if ($hasBeachMin) $qq->where('beach_level_min','<=',$level);
            if ($hasBeachMax) $qq->where('beach_level_max','>=',$level);
        };

        if ($direction === 'beach') {
            $applyBeach($q);
            return;
        }

        if ($direction === 'classic') {
            $applyClassic($q);
            return;
        }

        $q->where(function ($outer) use ($applyClassic,$applyBeach){
            $outer->where(fn($c)=>$applyClassic($c))
                  ->orWhere(fn($b)=>$applyBeach($b));
        });
    }

    /*
    |--------------------------------------------------------------------------
    | JOINED IDS
    |--------------------------------------------------------------------------
    */

    private function joinedIds(string $column): array
    {
        $user = auth()->user();

        if (!$user || !$this->hasRegistrations) {
            return [[],[]];
        }

        $ids = DB::table('event_registrations')
            ->where('user_id', $user->id)
            ->when($this->hasCancelledAt, fn($q) => $q->whereNull('cancelled_at'))
            ->when($this->hasIsCancelled, function ($q) {
                $q->where(function ($w) {
                    $w->whereNull('is_cancelled')
                      ->orWhere('is_cancelled', false);
                });
            })
            ->when($this->hasStatus, fn($q) => $q->where('status', 'confirmed'))
            ->pluck($column)
            ->map(fn($v)=>(int)$v);

        // Добавляем occurrence_id из командных турниров (event_team_members)
        if ($column === 'occurrence_id') {
            $teamIds = DB::table('event_team_members')
                ->join('event_teams', 'event_teams.id', '=', 'event_team_members.event_team_id')
                ->where('event_team_members.user_id', $user->id)
                ->whereIn('event_team_members.confirmation_status', ['confirmed', 'joined'])
                ->whereIn('event_teams.status', ['draft', 'ready', 'pending_members', 'submitted', 'confirmed', 'approved'])
                ->whereNotNull('event_teams.occurrence_id')
                ->pluck('event_teams.occurrence_id')
                ->map(fn($v) => (int)$v);

            $ids = $ids->merge($teamIds);
        }

        return [
            $ids->unique()->values()->all(),
            [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | LOCATION COLUMNS DETECTION
    |--------------------------------------------------------------------------
    */

    private function detectLocationColumns(): array
    {
        $cols = ['id','name','address','city_id'];

        foreach (['lat','lng','latitude','longitude'] as $c) {
            if (Schema::hasColumn('locations',$c)) {
                $cols[] = $c;
            }
        }

        return $cols;
    }
}