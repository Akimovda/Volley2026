<?php
	
	// app/Http/Controllers/EventCreateController.php
	
	namespace App\Http\Controllers;
	
	use App\Services\EventStoreService;
    use App\Services\EventAccessService;
use App\Services\StaffLogService;
    use App\Services\EventRegistrationRules;
    use Illuminate\Http\Request;
    use Illuminate\Support\Arr;
    use Illuminate\Support\Facades\Schema;
    use Illuminate\Validation\ValidationException;
    use App\Models\Event;
    use App\Models\User;
    use App\Models\Location;
    use App\Models\City;
    use Spatie\MediaLibrary\MediaCollections\Models\Media;
    use Carbon\Carbon;
	
	
	class EventCreateController extends Controller
	{
        public function __construct(
            private EventStoreService $storeService,
            private EventAccessService $accessService
        ) {}
        public function store(Request $request)
            {
                $user = $request->user();
            
                if (!$user) {
                    return redirect()->route('login');
                }
            
                $this->accessService->ensureCanCreateEvents($user);
            
                try {
            
                    $data = $request->all();
                    /*
                    |--------------------------------------------------------------------------
                    | LIMIT: event start date (max 1 year ahead)
                    |--------------------------------------------------------------------------
                    */
                    
                    if (!empty($data['starts_at_local'])) {
                    
                        $starts = Carbon::parse($data['starts_at_local']);
                    
                        if ($starts->greaterThan(now()->addYear())) {
                            throw ValidationException::withMessages([
                                'starts_at_local' => ['Дата мероприятия не может быть позже чем через 1 год.']
                            ]);
                        }
                    
                    }
            
                    /*
                    |--------------------------------------------------------------------------
                    | FIX: normalize description (как было в старом контроллере)
                    |--------------------------------------------------------------------------
                    */
            
                    if (array_key_exists('description_html', $data)) {
                        $html = trim((string)($data['description_html'] ?? ''));
            
                        if ($html === '') {
                            $data['description_html'] = null;
                        }
                    }
            
                    /*
                    |--------------------------------------------------------------------------
                    | FIX: уровни допуска по направлению
                    |--------------------------------------------------------------------------
                    */
            
                    if (($data['direction'] ?? null) === 'classic') {
            
                        $data['beach_level_min'] = null;
                        $data['beach_level_max'] = null;
            
                    } elseif (($data['direction'] ?? null) === 'beach') {
            
                        $data['classic_level_min'] = null;
                        $data['classic_level_max'] = null;
            
                    }
                    
                    $direction = (string)($data['direction'] ?? 'classic');
                    $format = (string)($data['format'] ?? 'game');
                    $registrationMode = (string)($data['registration_mode'] ?? 'single');
                    
                    $isTournament = in_array($format, ['tournament', 'tournament_classic', 'tournament_beach'], true);
                    
                    if ($isTournament) {
                        $registrationMode = $direction === 'beach' ? 'team_beach' : 'team_classic';
                    } else {
                        EventRegistrationRules::assertModeAllowed($direction, $registrationMode);
                    }
                    
                    $data['registration_mode'] = $registrationMode;
            
                    /*
                    |--------------------------------------------------------------------------
                    | Store Event
                    |--------------------------------------------------------------------------
                    */
            
                   $request->merge($data);

                   $result = $this->storeService->store($request, $user);
                   $event = $result['event'];

                   // Рекламное мероприятие — оплата через ЮKassa
                   if (!(bool)($event->allow_registration ?? true)) {
                       $platSettings = \App\Models\PlatformPaymentSetting::first();
                       $adPrice = (int)($platSettings?->ad_event_price_rub ?? 0);
                       if ($adPrice > 0) {
                           $event->ad_payment_status     = 'pending';
                           $event->ad_payment_expires_at = now()->addHours(2);
                           $event->ad_price_rub          = $adPrice;
                           $event->save();
                           \App\Jobs\ExpireAdEventJob::dispatch($event->id)->delay(now()->addHours(2));

                           try {
                               $payment = app(\App\Services\YookassaService::class)->createAdPayment($event);
                               $event->update([
                                   'ad_yookassa_payment_id'  => $payment['payment_id'],
                                   'ad_yookassa_payment_url' => $payment['payment_url'],
                               ]);
                               return redirect()->away($payment['payment_url']);
                           } catch (\Throwable $e) {
                               \Illuminate\Support\Facades\Log::error('YooKassa createPayment failed', [
                                   'event_id' => $event->id,
                                   'error'    => $e->getMessage(),
                               ]);
                               // Fallback — показываем страницу события с ошибкой
                               return redirect()->route('events.show', $event)
                                   ->with('error', 'Мероприятие создано, но платёж не удалось создать. Обратитесь к администратору.');
                           }
                       } else {
                           // Бесплатно — сразу публикуем
                           $event->ad_payment_status = 'paid';
                           $event->save();
                       }
                   }

                   // Лог для Staff
                   if ($user->isStaff()) {
                       $orgId = $user->getOrganizerIdForStaff();
                       if ($orgId) {
                           app(StaffLogService::class)->log(
                               $user, $orgId,
                               'create_event', 'event', $event->id,
                               "Создал мероприятие: {$event->title}"
                           );
                       }
                   }

                    $privateLink = null;
                    if ((bool)($event->is_private ?? false) && !empty($event->public_token)) {
                        $privateLink = route('events.public', ['token' => $event->public_token]);
                    }
                    
                    return redirect()
                        ->route('events.show', $event)
                        ->with('success', 'Мероприятие создано.')
                        ->with('clear_event_draft', true)
                        ->with('private_link', $privateLink);
                  
                    $event = $result['event'];
                    
                    $privateLink = null;
                    
                    if ((bool)($event->is_private ?? false) && !empty($event->public_token)) {
                        $privateLink = route('events.public', [
                            'token' => $event->public_token,
                        ]);
                    }
                    
                    return redirect()
                        ->route('events.show', $event)
                        ->with('success', 'Мероприятие создано.')
                        ->with('clear_event_draft', true)
                        ->with('private_link', $privateLink);
                        
                } catch (ValidationException $e) {
                    return $this->backWizard($e->errors());
                
                } catch (\Throwable $e) {
                    report($e);
                
                    return $this->backWizard(
                        ['general' => $e->getMessage() ?: 'Ошибка создания мероприятия'],
                        null,
                        'Ошибка создания мероприятия'
                    );
                }
            }
		
		public function choose(Request $request)
		{
			return $this->create($request);
		}
		
		public function fromTemplate(Request $request)
		{
			$user = $request->user();
			if (!$user) return redirect()->route('login');
			
			$this->accessService->ensureCanCreateEvents($user);
			
			return redirect()->route('events.create.event_management', ['tab' => 'archive']);
		}
		
		public function fromEvent(Request $request, Event $event)
		{
			$user = $request->user();
			if (!$user) return redirect()->route('login');
			
			$this->accessService->ensureCanCreateEvents($user);
			
			$role = (string)($user->role ?? 'user');
			
			// права: admin может всё, organizer только свои, staff только своего organizer
			if ($role !== 'admin') {
				if ($role === 'organizer') {
					if ((int)$event->organizer_id !== (int)$user->id) abort(403);
					} elseif ($role === 'staff') {
			    	$orgId = $this->accessService->resolveOrganizerIdForCreator($user);
					if ((int)$orgId <= 0) abort(403);
					if ((int)$event->organizer_id !== (int)$orgId) abort(403);
					} else {
					abort(403);
				}
			}
			
			return redirect()->to('/events/create?from_event_id=' . (int)$event->id);
		}
		
		/**
			* ⚠️ Legacy entrypoint (если где-то остались ссылки).
		*/
		public function eventManagement(Request $request)
		{
			$user = $request->user();
			if (!$user) return redirect()->route('login');
			
			$this->accessService->ensureCanCreateEvents($user);
			
			$tab = (string)$request->query('tab', 'templates');
			$tab = in_array($tab, ['templates', 'archive', 'mine'], true) ? $tab : 'templates';
			
			$q = Event::query()
            ->with(['location:id,name,address,city_id', 'location.city:id,name,region'])
            ->select('events.*')
            ->orderByDesc('events.id');
			
			if ($tab === 'archive') {
				
				$now = now();
				
				if (
                Schema::hasColumn('events', 'starts_at') &&
                Schema::hasColumn('events', 'duration_sec')
				) {
					
					$q->whereNotNull('events.starts_at')
					->whereNotNull('events.duration_sec')
					->whereRaw(
					"(events.starts_at + (events.duration_sec || ' seconds')::interval) < ?",
					[$now]
					);
					
					} elseif (Schema::hasColumn('events', 'starts_at')) {
					
					// fallback если duration_sec ещё нет
					$q->whereNotNull('events.starts_at')
					->where('events.starts_at', '<', $now);
					
					} else {
					$q->whereRaw('1=0');
				}
			}
			if ($tab === 'mine') {
				$uid = (int)$user->id;
				$ownerCol = null;
				
				foreach (['organizer_id', 'owner_id', 'created_by', 'user_id'] as $c) {
					if (Schema::hasColumn('events', $c)) {
						$ownerCol = $c;
						break;
					}
				}
				
				if ($ownerCol) $q->where("events.$ownerCol", $uid);
				else $q->whereRaw('1=0');
			}
			
			$q->leftJoin('event_game_settings as egs', 'egs.event_id', '=', 'events.id')
            ->leftJoin('event_registrations as er', 'er.event_id', '=', 'events.id')
            ->addSelect([
			DB::raw('COALESCE(egs.max_players, 0) as max_players'),
			DB::raw('COUNT(er.id) as registered_total'),
            ])
            ->groupBy('events.id', 'egs.max_players');
			
			$events = $q->paginate(20)->withQueryString();
			
			$tzGroups = (array)config('event_timezones.groups', []);
			$tzDefault = (string)config('event_timezones.default', 'Europe/Moscow');
			
			return view('events.event_management', compact('tab', 'events', 'tzGroups', 'tzDefault'));
		}
		
		/**
			* /events/create (мастер создания)
		*/
		public function create(Request $request)
        {
            $user = $request->user();
            if (!$user) return redirect()->route('login');
            $this->accessService->ensureCanCreateEvents($user);
			
            $role = (string)($user->role ?? 'user');
            $organizerId = $this->accessService->resolveOrganizerIdForCreator($user);
			
            // city from URL or from user profile
            $cityId = (int)$request->query('city_id', 0);
            
            if ($cityId <= 0) {
                $cityId = (int)($user->city_id ?? 0);
            }
			
            // ✅ locations filtered by city (if chosen)
           $locationsQuery = Location::query()
            ->when($cityId > 0, function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            })
            ->when($cityId <= 0, function ($q) {
                $q->whereRaw('1=0');
            })
            ->orderBy('name');
			
            if ($role !== 'admin') {
                $locationsQuery->whereNull('organizer_id');
			}
			
            $locations = $locationsQuery->get();
			
            // organizers (admin only)
            $organizers = collect();
            if ($role === 'admin') {
                $organizers = User::query()
                    ->select('id', 'first_name', 'last_name',)
                    ->whereIn('role', ['organizer', 'admin'])
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get();
			}
			
            // ✅ Prefill from existing event
            $prefill = [];
            $fromId = (int)$request->query('from_event_id', 0);
            if ($fromId > 0 && !$request->session()->hasOldInput()) {
                $src = Event::with('gameSettings')->find($fromId);
                if ($src) {
                    $prefill = $this->getEventPrefillData($src);
                    $prefill['_prefill_source_event_id'] = $fromId;
				}
			}
			
            $trainerLabel = $this->buildTrainerPrefillLabel($prefill);
			
            $userCovers = Media::query()
			->where('model_type', 'App\\Models\\User')
			->where('model_id', (int)$user->id)
			->orderByDesc('id')
			->limit(60)
			->get(['id', 'file_name', 'disk', 'collection_name', 'created_at']);
			
            $tzGroups = (array)config('event_timezones.groups', []);
            $tzDefault = (string)config('event_timezones.default', 'Europe/Moscow');
			
            return view('events.create', [
			'cityId' => $cityId,
			'userCityId' => $user->city_id ?? null,
			
			'locations' => $locations,
			'organizers' => $organizers,
			'canChooseOrganizer' => $role === 'admin',
			'resolvedOrganizerId' => $organizerId,
			'resolvedOrganizerLabel' => $role === 'admin'
			? null
			: (($role === 'organizer') ? 'Вы создаёте как organizer' : 'Вы создаёте как staff (привязан к organizer)'),
			
			'prefill' => $prefill,
			'userCovers' => $userCovers,
			'trainerPrefillLabel' => $trainerLabel,
			
			'tzGroups' => $tzGroups,
			'tzDefault' => $tzDefault,
            ]);
		}
		// GET /events/create/locations?city_id=123
		public function locationsByCity(Request $request)
		{
			$user = $request->user();
			if (!$user) return response()->json(['ok' => false, 'items' => []], 401);
			$this->accessService->ensureCanCreateEvents($user);
            
			$role = (string)($user->role ?? 'user');
			$cityId = (int)$request->query('city_id', 0);
            
			if ($cityId <= 0) {
				return response()->json(['ok' => true, 'items' => []]);
			}
            
			$q = Location::query()
			->where('city_id', $cityId)
			->orderBy('name');
            
			if ($role !== 'admin') {
				$q->whereNull('organizer_id');
			}
            
			$items = $q
            ->limit(200)
            ->get(['id','name','address','city_id','lat','lng','timezone','tz'])
			->map(fn($l) => [
			'id' => (int)$l->id,
			'name' => (string)$l->name,
			'address' => (string)($l->address ?? ''),
			'city_id' => (int)($l->city_id ?? 0),
			'lat' => $l->lat !== null ? (string)$l->lat : '',
			'lng' => $l->lng !== null ? (string)$l->lng : '',
			'timezone' => (string)($l->timezone ?: ($l->tz ?? '')),
			])
			->values()
			->all();
            
			return response()->json(['ok' => true, 'items' => $items]);
		}
		/**
			* ✅ AJAX поиск пользователей для выбора тренера
		*/
       public function search(Request $request)
{
    $q = trim((string)$request->query('q',''));

    if (mb_strlen($q) < 2) {
        return response()->json([
            'ok' => true,
            'items' => []
        ]);
    }

    $like = '%'.$q.'%';

    $users = User::query()
        ->where(function ($w) use ($like) {

            $w->where('first_name','ILIKE',$like)
              ->orWhere('last_name','ILIKE',$like)
              ->orWhereRaw("(first_name || ' ' || last_name) ILIKE ?", [$like])
              ->orWhere('telegram_username','ILIKE',$like)
              ->orWhere('email','ILIKE',$like);

        })
        ->orderBy('last_name')
        ->orderBy('first_name')
        ->limit(15)
        ->get([
            'id',
            'first_name',
            'last_name',
            'telegram_username',
            'email'
        ]);

    $items = $users->map(function ($u) {

        $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));

        if ($name === '') {
            $name = $u->email;
        }

        return [
            'id' => (int)$u->id,
            'label' => $name,
            'meta' => $u->telegram_username ?? '',
            'sub' => $u->email
        ];

    })->values();

    return response()->json([
        'ok' => true,
        'items' => $items
    ]);
}
        public function searchCities(Request $request)
        {
            $user = $request->user();
            if (!$user) return response()->json(['ok' => false, 'items' => []], 401);
            $this->accessService->ensureCanCreateEvents($user);
			
            $q = trim((string)$request->query('q', ''));
            if (mb_strlen($q) < 2) return response()->json(['ok' => true, 'items' => []]);
			
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
			
            $items = City::query()
			->where('name', 'like', $like)
			->orderBy('name')
			->limit(30)
			->get(['id','name','region','country_code','timezone'])
			->map(fn($c) => [
			'id' => (int)$c->id,
			'name' => (string)$c->name,
			'region' => (string)($c->region ?? ''),
			'country_code' => (string)($c->country_code ?? ''),
			'timezone' => (string)($c->timezone ?? ''),
			])
			->values()
			->all();
			
            return response()->json(['ok' => true, 'items' => $items]);
		}
	
		// ======================================================================
		// Prefill helpers
		// ======================================================================
		private function resolveTimezoneFromLocation(Location $location): ?string
        {
            // 1) Если есть relation city и у неё есть timezone — используем
            if (method_exists($location, 'city')) {
                try {
                    $city = $location->relationLoaded('city') ? $location->city : $location->city()->first();
                    if ($city && !empty($city->timezone)) {
                        return (string)$city->timezone;
					}
                    // иногда timezone могут хранить прямо в city (или похожем поле)
                    if ($city && !empty($city->tz)) {
                        return (string)$city->tz;
					}
                    // fallback по имени города (минимально полезно для Москвы)
                    $cityName = $city ? trim((string)($city->name ?? '')) : '';
                    if ($cityName !== '') {
                        $n = mb_strtolower($cityName);
                        if ($n === 'москва' || $n === 'moscow') return 'Europe/Moscow';
					}
					} catch (\Throwable $e) {
                    // ignore
				}
			}
			
            // 2) Если timezone хранится на самой локации
            if (!empty($location->timezone)) return (string)$location->timezone;
            if (!empty($location->tz)) return (string)$location->tz;
			
            return null;
		}
		private function buildTrainerPrefillLabel(array $prefill): ?string
		{
			$trainerIds = $prefill['trainer_user_ids'] ?? null;
			
			if (is_string($trainerIds)) $trainerIds = [$trainerIds];
			if (is_array($trainerIds)) {
				$trainerIds = array_values(array_unique(array_map('intval', $trainerIds)));
				$trainerIds = array_values(array_filter($trainerIds, fn($id) => $id > 0));
				} else {
				$trainerIds = [];
			}
			
			if (count($trainerIds) > 0) {
				$trainers = User::query()
                ->whereIn('id', $trainerIds)
                ->get(['id','first_name','last_name','name','email'])
                ->keyBy('id');
				
				$labels = [];
				foreach ($trainerIds as $id) {
					if (!isset($trainers[$id])) continue;
					$u = $trainers[$id];
					$labels[] = ($u->name ?: $u->email) . ' (#' . (int)$u->id . ')';
				}
				
				if (count($labels) === 1) return $labels[0];
				if (count($labels) <= 3) return implode(', ', $labels);
				
				return 'Выбрано ' . count($labels) . ' тренеров';
			}
			
			// fallback legacy single trainer
			if (!empty($prefill['trainer_user_id'] ?? null)) {
				$tu = User::query()
                ->whereKey((int)$prefill['trainer_user_id'])
                ->first(['id', 'name', 'email']);
				
				if ($tu) return ($tu->name ?: $tu->email) . ' (#' . (int)$tu->id . ')';
			}
			
			return null;
		}
		
		private function getEventPrefillData(Event $src): array
		{
			$prefill = Arr::only($src->toArray(), [
            'title',
            'direction',
            'format',
            'location_id',
            'timezone',
            'requires_personal_data',
            'classic_level_min',
            'classic_level_max',
            'beach_level_min',
            'beach_level_max',
            'is_paid',
            'price_amount',
            'price_currency',
            'is_private',
            'allow_registration',
            'is_recurring',
            'recurrence_rule',
            'description_html',
            'trainer_user_id',
            'trainer_id',
            'registration_mode',
            'bot_assistant_enabled',
            'bot_assistant_threshold',
            'bot_assistant_max_fill_pct',
			]);
			if (!empty($src->price_minor)) {
                $prefill['price_amount'] = number_format(((int)$src->price_minor) / 100, 2, '.', '');
            }
			
			// ✅ нормализуем trainer в один ключ
			if (empty($prefill['trainer_user_id']) && !empty($prefill['trainer_id'])) {
				$prefill['trainer_user_id'] = $prefill['trainer_id'];
			}
			
			$gs = $src->gameSettings;
			if ($gs) {
				$prefill['game_subtype'] = $gs->subtype;
				$prefill['game_libero_mode'] = $gs->libero_mode;
				$prefill['game_min_players'] = $gs->min_players;
				$prefill['game_max_players'] = $gs->max_players;
				$prefill['game_gender_policy'] = $gs->gender_policy;
				$prefill['game_gender_limited_side'] = $gs->gender_limited_side;
				$prefill['game_gender_limited_max'] = $gs->gender_limited_max;
				$prefill['game_gender_limited_positions'] = is_array($gs->gender_limited_positions) ? $gs->gender_limited_positions : null;
				$prefill['game_allow_girls'] = (bool)($gs->allow_girls ?? true);
				$prefill['game_girls_max'] = $gs->girls_max;
			}
			
			unset($prefill['starts_at'], $prefill['public_token']);
			
			// ✅ multiple trainers prefill (если есть relation)
			if (method_exists($src, 'trainers')) {
				try {
					$prefill['trainer_user_ids'] = $src->trainers()
                    ->pluck('users.id')
                    ->map(fn($v) => (int)$v)
                    ->values()
                    ->all();
					} catch (\Throwable $e) {
					// тихо игнорим
				}
			}
			
			return $prefill;
		}
		
		
		// GET /events/create/city-meta?city_id=123
		public function cityMeta(Request $request)
        {
            $user = $request->user();
            if (!$user) return response()->json(['ok' => false], 401);
            $this->accessService->ensureCanCreateEvents($user);
			
            $cityId = (int)$request->query('city_id', 0);
            if ($cityId <= 0) return response()->json(['ok' => true, 'timezone' => null]);
			
           $city = City::query()
                ->where('id', $cityId)
                ->select('id','name','timezone')
                ->first();
            if (!$city) return response()->json(['ok' => true, 'timezone' => null]);
			
            $tz = $city->timezone ? (string)$city->timezone : null;
			
            if (!$tz) {
                $n = mb_strtolower(trim((string)$city->name));
                if ($n === 'москва' || $n === 'moscow') $tz = 'Europe/Moscow';
			}
			
            return response()->json(['ok' => true, 'timezone' => $tz]);
		}
		
		
		private function wizardStepFromErrors(array $errors): int
		{
			$step1 = [
            'organizer_id',
            'title',
            'direction',
            'format',
            'age_policy',
            'trainer_user_ids',
            'trainer_user_id',
            'game_subtype',
            'game_min_players',
            'game_max_players',
            'game_libero_mode',
            'game_gender_policy',
            'game_gender_limited_side',
            'game_gender_limited_max',
            'game_gender_limited_positions',
            'classic_level_min',
            'classic_level_max',
            'beach_level_min',
            'beach_level_max',
            'allow_registration',
			];
			
			$step2 = [
            'timezone',
            'starts_at_local',
            'duration_sec',
            'city_id',
            'location_id',
            'is_recurring',
            'recurrence_type',
            'recurrence_interval',
            'recurrence_months',
            'recurrence_rule',
            'reg_starts_days_before',
            'reg_ends_minutes_before',
            'cancel_lock_minutes_before',
			];
			
			$step3 = [
            'is_private',
            'is_paid',
            'is_paid',
            'price_minor',
            'price_currency',
            'requires_personal_data',
            'cover_upload',
            'cover_media_id',
            'description_html',
            'remind_registration_enabled',
            'remind_registration_minutes_before',
            'show_participants',
            'bot_assistant_enabled',
            'bot_assistant_threshold',
            'bot_assistant_max_fill_pct',
			];
			
			foreach ($step3 as $f) if (array_key_exists($f, $errors)) return 3;
			foreach ($step2 as $f) if (array_key_exists($f, $errors)) return 2;
			foreach ($step1 as $f) if (array_key_exists($f, $errors)) return 1;
			
			return 1;
		}
		
           private function backWizard(array $fieldErrors, ?int $forcedStep = null, ?string $flashError = null)
            {
                $step = $forcedStep ?? $this->wizardStepFromErrors($fieldErrors);
            
                $bag = [];
            
                foreach ($fieldErrors as $field => $messages) {
                    if (is_array($messages)) {
                        $messages = array_values(array_filter(array_map(
                            fn ($m) => is_scalar($m) || $m === null ? (string) $m : json_encode($m, JSON_UNESCAPED_UNICODE),
                            $messages
                        ), fn ($m) => $m !== ''));
                    } else {
                        $messages = [(string) $messages];
                    }
            
                    if (empty($messages)) {
                        $messages = ['Ошибка в поле ' . $field];
                    }
            
                    $bag[$field] = $messages;
                }
            
                $resp = back()
                    ->with('wizard_initial_step', $step)
                    ->with('wizard_errors', $bag);
            
                if ($flashError) {
                    $resp->with('error', $flashError);
                }
            
                return $resp;
            }
	}
