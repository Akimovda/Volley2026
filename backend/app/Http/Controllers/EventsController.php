<?php
	
	// app/Http/Controllers/EventsController.php
	
	namespace App\Http\Controllers;
	use App\Services\EventRegistrationGuard;
	use App\Services\EventCancellationGuard;
	use App\Services\EventVisibilityService;
	use	App\Services\EventShowService;
	use App\Services\EventIndexService;
	use App\Services\EventRegistrationGroupService;
	use App\Models\Event;
	use App\Models\EventOccurrence;
	use App\Models\User;
	use Illuminate\Http\Request;
	use Illuminate\Support\Carbon;
	use Illuminate\Support\Facades\DB;
	use Illuminate\Support\Facades\Schema;
	
	
	class EventsController extends Controller
	{
		public function index(EventIndexService $service, Request $request)
        {
            return $service->handle($request);
        }
		
       public function show(Request $request, Event $event)
        {
            $data = app(\App\Services\EventShowService::class)
                ->handle($request, $event);
        
            return view('events.show', $data);
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
					
					$this->visibility->applyPrivateVisibilityNegationScope($restrictedQ, $user);
					
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
					
					$this->visibility->applyPrivateVisibilityNegationScope($restrictedQ, $user, 'e.');
					
					$restrictedOccurrenceIds = $restrictedQ->pluck('eo.id')
                    ->map(fn ($v) => (int) $v)
                    ->unique()
                    ->values()
                    ->all();
				}
			}
			
			return [$joinedOccurrenceIds, $restrictedOccurrenceIds];
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
	
        private EventVisibilityService $visibility;
        private EventRegistrationGroupService $groupService;
        
        public function __construct(
            EventVisibilityService $visibility,
            EventRegistrationGroupService $groupService
        ) {
            $this->visibility = $visibility;
            $this->groupService = $groupService;
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
                $orgId = (int) DB::table('users')
                    ->where('id', $staffUserId)
                    ->value('organizer_id');
        
                return $orgId > 0 ? [$orgId] : [];
            }
        
            return [];
        }
    	public function availabilityOccurrence(
            EventOccurrence $occurrence,
            Request $request
        ) {
            $user = $request->user();
        
            $result = app(EventRegistrationGuard::class)->check(
                $user,
                $occurrence
            );
        
            $freePositions = $result->data['free_positions'] ?? [];
            $meta = $result->data['meta'] ?? [];
            $errors = $result->errors ?? [];
        
            return response()->json([
                'ok' => $result->allowed,
                'allowed' => $result->allowed,
                'message' => !empty($errors) ? implode(' ', $errors) : null,
                'free_positions' => $freePositions,
                'meta' => $meta,
        
                // можно оставить и старую вложенную структуру для совместимости
                'data' => $result->data ?? [],
                'errors' => $errors,
            ]);
        }
		
	}
