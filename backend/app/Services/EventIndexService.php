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
                'event.gameSettings:event_id,subtype,libero_mode,max_players',
                'event.media'
            ]);
        
        $this->applyActiveOccurrenceScope($occQ);
        $this->applyDateFilter($occQ);
        
        $occQ->orderBy('starts_at', 'asc');

        $occQ->whereHas('event', function ($q) use ($user,$userId,$isAdmin,$direction,$format,$level,$location) {

            if (!$isAdmin) {

                $q->where(function ($w) use ($userId) {

                    $w->where('allow_registration', true);

                    if ($userId > 0 && Schema::hasColumn('events','organizer_id')) {
                        $w->orWhere('organizer_id', $userId);
                    }

                    foreach (['created_by','creator_user_id','created_user_id'] as $col) {
                        if ($userId > 0 && Schema::hasColumn('events',$col)) {
                            $w->orWhere($col, $userId);
                        }
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

            $occ->join = (object)[
                'allowed' => !$isJoined,
                'message' => $isJoined ? 'Вы уже записаны.' : null,
            ];

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

        return DB::table('event_registrations')
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
            ->map(fn($v)=>(int)$v)
            ->all();
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
            $eventsQ->where('allow_registration',true);
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
            ->map(fn($v)=>(int)$v)
            ->unique()
            ->values()
            ->all();

        return [$ids,[]];
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