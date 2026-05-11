<?php
	
	namespace App\Services;
	
	use App\Models\User;
	use App\Models\EventOccurrence;
	use App\Models\OccurrenceWaitlist;
	use App\Services\EventRoleSlotService;
	use Illuminate\Support\Carbon;
	use Illuminate\Support\Facades\Schema;
	use Illuminate\Support\Collection;
	
	final class EventRegistrationGuard
	{
		private EventRoleSlotService $slotService;
		
		public function __construct(EventRoleSlotService $slotService)
		{
			$this->slotService = $slotService;
		}
		
		public function check(
			?User $user,
			EventOccurrence $occurrence,
			array $context = []
		): GuardResult {
			
			$result = GuardResult::allow([
				'free_positions' => [],
				'is_registered'  => false,
				'user_position'  => null,
			]);
			
			$result->meta['is_registered'] = false;
			$result->meta['user_position'] = null;
			
			$position = $this->normalizePosition($context);
			$event = $occurrence->event;
			
			if (!$event) {
				$result->errors[] = 'Событие не найдено.';
				return $result;
			}

			// Командный турнир — прямая запись заблокирована.
			// Сначала проверяем окно регистрации (чтобы UI мог показать «откроется ...» / «закрыта»),
			// затем добавляем неблокирующую подсказку «нужна команда».
			if (in_array((string)($event->registration_mode ?? ''), ['team_classic', 'team_beach'], true)) {
				$nowUtc = Carbon::now('UTC');
				$tz     = (string)($event?->timezone ?? 'Europe/Moscow');

				if (
					$occurrence->starts_at &&
					$nowUtc->greaterThanOrEqualTo(Carbon::parse($occurrence->starts_at, 'UTC'))
				) {
					$result->addError('Мероприятие уже началось.');
					return $result;
				}

				if (!$occurrence->effectiveAllowRegistration()) {
					$result->addError('Регистрация на мероприятие выключена.');
					return $result;
				}

				$regStartsAt = $occurrence->effectiveRegistrationStartsAt();
				if ($regStartsAt && $nowUtc->lessThan($regStartsAt)) {
					$opens = $regStartsAt->copy()->setTimezone($tz)->locale('ru')->translatedFormat('d F в H:i');
					$result->addError('Регистрация ещё не началась — откроется ' . $opens . '.');
					return $result;
				}

				$regEndsAt = $occurrence->effectiveRegistrationEndsAt();
				if ($regEndsAt && $nowUtc->greaterThanOrEqualTo($regEndsAt)) {
					$result->addError('Регистрация уже завершена.');
					return $result;
				}

				// Регистрация открыта — оставляем allowed=true и подсказку про команду.
				$result->errors[] = 'На турнир можно записаться только в составе команды.';

				// Заполняем meta по командам ДО раннего return — иначе JS не получит счётчик
				$teamsMax = (int)($event->tournament_teams_count ?? $event->tournamentSetting?->teams_count ?? 0);
				$teamsReg = \App\Models\EventTeam::where('event_id', $occurrence->event_id)
					->where(fn($q) => $q->where('occurrence_id', $occurrence->id)->orWhereNull('occurrence_id'))
					->whereIn('status', ['ready','pending_members','submitted','confirmed','approved'])
					->count();
				$result->data['meta'] = [
					'tournament_teams_max'        => $teamsMax,
					'tournament_teams_registered' => $teamsReg,
					'tournament_teams_remaining'  => max(0, $teamsMax - $teamsReg),
				];

				return $result;
			}

			$direction = (string) ($event->direction ?? 'classic');
            $isClassic = $direction === 'classic';
            $isBeach = $direction === 'beach';
			
			/*
				|--------------------------------------------------------------------------
				| REGISTRATIONS
				|--------------------------------------------------------------------------
			*/
			
			$registrations = ($occurrence->registrations ?? collect())
				->filter(fn($r) => !$r->is_cancelled && $r->status !== 'cancelled')
				->values();
			if (!$registrations instanceof Collection) {
				$registrations = collect($registrations);
			}
			
			$registrationsByPosition = $registrations->groupBy('position');
			
			$userRegistration = $this->detectUserRegistration(
				$user,
				$registrations,
				$result
			);
			
			/*
				|--------------------------------------------------------------------------
				| MAX PLAYERS
				|--------------------------------------------------------------------------
			*/
			
			$settings = $event->gameSettings ?? null;
			
			$maxPlayers = (int)(
				$occurrence->max_players
				?? ($settings?->max_players ?? 0)
			);
			
			/*
				|--------------------------------------------------------------------------
				| AGE POLICY
				|--------------------------------------------------------------------------
			*/
			
			$agePolicy = $occurrence->age_policy
				?? $event->age_policy
				?? 'any';
			
			/*
				|--------------------------------------------------------------------------
				| ПОСЛЕДОВАТЕЛЬНОСТЬ ПРОВЕРОК (ИСПРАВЛЕНО)
				|--------------------------------------------------------------------------
			*/
			
			// 1. Проверка авторизации и окна регистрации
			$this->checkAuthAndWindow($user, $occurrence, $event, $result);

			// 1.5. Проверка заполненности профиля (requires_personal_data)
			$this->checkPersonalData($user, $occurrence, $event, $result);

			// 2. Проверка возрастной политики (исправлено)
			$this->checkAgePolicy($user, $occurrence, $event, $agePolicy, $result);
			
			// 3. Проверка гендерной политики
			[$genderBlocked, $policy] = $this->applyGenderPolicy(
                $user,
                $settings,
                $registrations,
                $maxPlayers,
                $result
            );
			
			// 4. Проверка уровня игрока
			$this->checkLevelPolicy($user, $occurrence, $event, $result);
			
			// 5. Расчет свободных позиций (только после всех проверок)
			$freePositions = $this->calculatePositions(
				$event,
				$settings,
				$registrationsByPosition,
				$userRegistration,
				$position,
				$user
			);
			
			// 6. Проверка доступности мест / позиций
            $this->checkCapacityAndPositions(
                $occurrence,
                $registrations,
                $maxPlayers,
                $freePositions,
                $position,
                $isClassic,
                $isBeach,
                $result
            );
			
			/*
				|--------------------------------------------------------------------------
				| SNAPSHOT
				|--------------------------------------------------------------------------
			*/
			
			$this->buildAvailabilitySnapshot(
				$occurrence,
				$registrations,
				$maxPlayers,
				$result,
				$freePositions,
				$agePolicy,
				$policy,
				$genderBlocked
			);

			// Гейт листа ожидания: если есть кто-то в очереди (помимо текущего user) —
			// основная запись закрыта, доступна только запись в waitlist.
			// Турниры пропускаем — у них своя ветка (notifyNext без автозаписи).
			$this->checkWaitlistGate($user, $occurrence, $event, $result);

			if (!empty($result->errors)) {
				$result->allowed = false;
			}

			return $result;
		}

		/*
			|--------------------------------------------------------------------------
			| WAITLIST GATE
			|--------------------------------------------------------------------------
			| Если на мероприятии уже есть кто-то в листе ожидания (помимо текущего
			| пользователя) — обычная запись в основной состав запрещается.
			| Освободившееся место будет автоматически отдано первому подходящему
			| из очереди через WaitlistService::autoBookNext().
		*/
		private function checkWaitlistGate(
			?User $user,
			EventOccurrence $occurrence,
			$event,
			GuardResult $result
		): void {
			if ((string) ($event->format ?? '') === 'tournament') {
				return;
			}

			$query = OccurrenceWaitlist::query()
				->where('occurrence_id', $occurrence->id);
			if ($user) {
				$query->where('user_id', '!=', $user->id);
			}

			$hasOthers = $query->exists();
			$result->meta['waitlist_has_others'] = $hasOthers;

			if ($hasOthers) {
				// Проверяем: есть ли свободные запасные места (reserve)?
				$freeReserveEntry = collect($result->data['free_positions'] ?? [])
					->firstWhere('key', 'reserve');

				if ($freeReserveEntry && ($freeReserveEntry['free'] ?? 0) > 0) {
					// Есть свободные запасные — не блокируем регистрацию.
					// reserve — отдельная роль (скамейка), не обход очереди на основные позиции.
					// waitlist_only НЕ ставим, чтобы UI показал кнопку «Запасной».
					$result->meta['waitlist_has_others'] = true;
				} else {
					// Запасных нет — стандартная блокировка через лист ожидания.
					$result->meta['waitlist_only'] = true;
					$result->errors[] = 'На мероприятии есть лист ожидания. Запись закрыта — доступна только запись в лист ожидания.';
				}
			}
		}
		
		/*
			|--------------------------------------------------------------------------
			| USER REGISTRATION
			|--------------------------------------------------------------------------
		*/
		
		private function detectUserRegistration(
			?User $user,
			Collection $registrations,
			GuardResult $result
		) {
			
			if (!$user) {
				return null;
			}
			
			$userRegistration = $registrations->firstWhere('user_id', $user->id);
			
			if ($userRegistration) {
				$result->meta['is_registered'] = true;
				$result->meta['user_position'] = $userRegistration->position;
			}
			
			return $userRegistration;
		}
		/*
			|--------------------------------------------------------------------------
			| USER REGISTRATION для главной страницы EVENTS
			|--------------------------------------------------------------------------
		*/
		public function quickCheck(?User $user, EventOccurrence $occurrence): object
        {
            if (!$user) {
                return (object)['allowed' => true, 'code' => null, 'message' => null];
            }

            $event    = $occurrence->event;
            $dir      = (string)($event?->direction ?? 'classic');
            $agePolicy = (string)($occurrence->age_policy ?? $event?->age_policy ?? 'any');

            // Командный турнир — прямая запись заблокирована.
            // Если окно регистрации ещё не открыто/уже закрыто/мероприятие началось — отдаём
            // allowed=false БЕЗ кода 'team_only', чтобы карточка показала корректный alert
            // («Регистрация откроется …» / «Регистрация закрыта» / «Мероприятие уже началось»).
            if (in_array((string)($event?->registration_mode ?? ''), ['team_classic', 'team_beach'], true)) {
                $nowUtc = Carbon::now('UTC');

                if (
                    $occurrence->starts_at &&
                    $nowUtc->greaterThanOrEqualTo(Carbon::parse($occurrence->starts_at, 'UTC'))
                ) {
                    return (object)['allowed' => false, 'code' => null, 'message' => null];
                }

                if (!$occurrence->effectiveAllowRegistration()) {
                    return (object)['allowed' => false, 'code' => null, 'message' => null];
                }

                $regStartsAt = $occurrence->effectiveRegistrationStartsAt();
                if ($regStartsAt && $nowUtc->lessThan($regStartsAt)) {
                    return (object)['allowed' => false, 'code' => null, 'message' => null];
                }

                $regEndsAt = $occurrence->effectiveRegistrationEndsAt();
                if ($regEndsAt && $nowUtc->greaterThanOrEqualTo($regEndsAt)) {
                    return (object)['allowed' => false, 'code' => null, 'message' => null];
                }

                // Регистрация открыта — нужна команда
                return (object)['allowed' => false, 'code' => 'team_only',
                    'message' => 'Запись только в составе команды'];
            }

            // --- Возраст ---
            if ($agePolicy === 'adult' || $agePolicy === 'child') {
                $birthDate = $user->birth_date ?? null;
                if (!$birthDate) {
                    return (object)['allowed' => false, 'code' => 'age_blocked',
                        'message' => '🔞 Вы не проходите по возрасту'];
                }
                $age = \Illuminate\Support\Carbon::parse($birthDate)
                         ->diffInYears(\Illuminate\Support\Carbon::parse($occurrence->starts_at, 'UTC'));
                if ($agePolicy === 'adult') {
                    if ($age < 18) {
                        return (object)['allowed' => false, 'code' => 'age_blocked',
                            'message' => '🔞 Это мероприятие только для взрослых (18+)'];
                    }
                } else {
                    $ageMin = (int)($event?->child_age_min ?? 0);
                    $ageMax = (int)($event?->child_age_max ?? 0);
                    if ($ageMax > 0 && $age > $ageMax) {
                        return (object)['allowed' => false, 'code' => 'age_blocked',
                            'message' => '🔞 Вы не проходите по возрасту'];
                    }
                    if ($ageMin > 0 && $age < $ageMin) {
                        return (object)['allowed' => false, 'code' => 'age_blocked',
                            'message' => '🔞 Вы не проходите по возрасту'];
                    }
                }
            }
        
            // --- Уровень ---
            if ($dir === 'beach') {
                $userLevel = $user->beach_level;
                $lvMin = $occurrence->effectiveBeachLevelMin();
                $lvMax = $occurrence->effectiveBeachLevelMax();
            } else {
                $userLevel = $user->classic_level;
                $lvMin = $occurrence->effectiveClassicLevelMin();
                $lvMax = $occurrence->effectiveClassicLevelMax();
            }
        
            $hasRestriction = !is_null($lvMin) || !is_null($lvMax);
        
            if ($hasRestriction && !is_null($userLevel)) {
                if (!is_null($lvMax) && $userLevel > $lvMax) {
                    return (object)['allowed' => false, 'code' => 'level_too_high',
                        'message' => '😎 Вы слишком крут(а) для этого мероприятия!'];
                }
               if (!is_null($lvMin) && $userLevel < $lvMin) {
                return (object)['allowed' => false, 'code' => 'level_too_low',
                    'message' => '😥 Вы ещё не готовы для этого мероприятия!'];
            }
        }

        // --- Гендерное окно регистрации ---
        $gs = $event?->gameSettings ?? null;
        if (
            $gs &&
            $gs->gender_policy === 'mixed_limited' &&
            $gs->gender_limited_side &&
            $gs->gender_limited_reg_starts_days_before !== null &&
            $occurrence->starts_at
        ) {
            $viewerGender = strtolower((string) $user->gender);
            $side = $gs->gender_limited_side;
            $targetGender = $side === 'male' ? 'm' : ($side === 'female' ? 'f' : null);

            if ($targetGender && $viewerGender !== '' && $viewerGender[0] === $targetGender) {
                $nowUtc = Carbon::now('UTC');
                $restrictedOpensAt = Carbon::parse($occurrence->starts_at, 'UTC')
                    ->subDays((int) $gs->gender_limited_reg_starts_days_before);

                if ($nowUtc->lessThan($restrictedOpensAt)) {
                    $label = $side === 'female' ? 'девушек' : 'мужчин';
                    $tz = $occurrence->event?->timezone ?? 'Europe/Moscow';
                    $opensFormatted = $restrictedOpensAt->copy()->setTimezone($tz)->format('d.m.Y H:i');
                    return (object)[
                        'allowed' => false,
                        'code'    => 'gender_reg_not_started',
                        'message' => 'Регистрация для ' . $label . ' ещё не началась — откроется ' . $opensFormatted . '.',
                    ];
                }
            }
        }

        return (object)['allowed' => true, 'code' => null, 'message' => null];
    }

    /*
        |--------------------------------------------------------------------------
        | POSITIONS
        |--------------------------------------------------------------------------
    */
		
		private function calculatePositions(
			$event,
			$settings,
			Collection $registrationsByPosition,
			$userRegistration,
			?string $position,
			?User $user
		): array {
			
			$freePositions = [];
			$slots = $this->slotService->getSlots($event);
			
			/*
				|--------------------------------------------------------------------------
				| REAL TAKEN PER POSITION
				|--------------------------------------------------------------------------
			*/
			
			$takenPerRole = [];
			
			foreach ($slots as $slot) {
				
				$taken = $registrationsByPosition
					->get($slot->role)?->count() ?? 0;
				
				if (
					($userRegistration)
					&& $userRegistration->position === $slot->role
				) {
					$taken--;
				}
				
				$takenPerRole[$slot->role] = $taken;
			}
			
			/*
				|--------------------------------------------------------------------------
				| NORMAL PLAYERS (REAL SLOTS)
				|--------------------------------------------------------------------------
			*/
			
			foreach ($slots as $slot) {
				
				$taken = $takenPerRole[$slot->role] ?? 0;
				$free  = $slot->max_slots - $taken;
				
				// Проверяем наличие свободных мест на позиции
				if ($free <= 0) {
					continue;
				}
				
				/*
					|--------------------------------------------------------------------------
					| GENDER LIMITED FILTER
					|--------------------------------------------------------------------------
				*/
				
				if ($settings && $settings->gender_policy === 'mixed_limited' && $user) {
					
					$viewerGender = strtolower((string)$user->gender);
					
					$side = $settings->gender_limited_side;
					$positions = $settings->gender_limited_positions ?? [];
					
					if (is_string($positions)) {
						$positions = json_decode($positions, true) ?: [];
					}
					
					$targetGender = null;
					
					if ($side === 'male')   $targetGender = 'm';
					if ($side === 'female') $targetGender = 'f';
					
					if (
						$targetGender &&
						$viewerGender &&
						$viewerGender[0] === $targetGender &&
						!in_array($slot->role, $positions, true)
					) {
						continue;
					}
					
				}
				
				$freePositions[] = [
					'key'   => $slot->role,
					'free'  => $free,
					'limit' => $slot->max_slots
				];
			}

			/*
				|--------------------------------------------------------------------------
				| RESERVE SLOTS
				| Открываются только когда ВСЕ основные позиции заняты глобально.
				| Проверяем по всем слотам без гендерного фильтра — чтобы мужчина
				| с заполненным setter-слотом не видел reserve, пока есть свободные
				| outside-места (доступные другому полу).
				|
				| При mixed_limited: если 'reserve' не входит в gender_limited_positions,
				| ограничиваемый пол не видит запасные места.
				|--------------------------------------------------------------------------
			*/
			$reserveMax = (int) ($settings?->reserve_players_max ?? 0);
			if ($reserveMax > 0 && empty($freePositions)) {
				// Все слоты глобально заняты (без гендерного фильтра)
				$allMainFull = collect($slots)->every(
					fn($s) => ($s->max_slots - ($takenPerRole[$s->role] ?? 0)) <= 0
				);
				if ($allMainFull) {
					// Гендерный фильтр для reserve
					$genderBlockReserve = false;
					if ($settings && $settings->gender_policy === 'mixed_limited' && $user) {
						$rvg = strtolower((string)($user->gender ?? ''));
						$rSide = $settings->gender_limited_side;
						$rPos = $settings->gender_limited_positions ?? [];
						if (is_string($rPos)) $rPos = json_decode($rPos, true) ?: [];
						$rTg = match($rSide) { 'male' => 'm', 'female' => 'f', default => null };
						if ($rTg && $rvg && $rvg[0] === $rTg && !in_array('reserve', $rPos, true)) {
							$genderBlockReserve = true;
						}
					}

					if (!$genderBlockReserve) {
						$reserveCount = $registrationsByPosition->get('reserve')?->count() ?? 0;
						// Не считаем текущую запись пользователя (если он уже запасной)
						if ($userRegistration && $userRegistration->position === 'reserve') {
							$reserveCount = max(0, $reserveCount - 1);
						}
						$freeReserve = max(0, $reserveMax - $reserveCount);
						if ($freeReserve > 0) {
							$freePositions[] = [
								'key'   => 'reserve',
								'free'  => $freeReserve,
								'limit' => $reserveMax,
							];
						}
					}
				}
			}

			return $freePositions;
		}
		
		/*
			|--------------------------------------------------------------------------
			| PERSONAL DATA
			|--------------------------------------------------------------------------
		*/

		private function checkPersonalData(
			?User $user,
			EventOccurrence $occurrence,
			$event,
			GuardResult $result
		): void {
			if (!$user) return;

			$required = !is_null($occurrence->requires_personal_data)
				? (bool) $occurrence->requires_personal_data
				: (bool) ($event->requires_personal_data ?? false);

			if (!$required) return;

			$requirements = app(\App\Services\EventRegistrationRequirements::class);
			$missing = $requirements->missing($user, $event);

			// Уровень обрабатывает checkLevelPolicy — здесь проверяем только личные данные
			$personalMissing = array_filter($missing, fn($m) => !in_array($m, ['classic_level', 'beach_level']));

			if (!empty($personalMissing)) {
				$result->errors[] = 'Для записи на это мероприятие необходимо заполнить личные данные в профиле.';
				$result->meta['profile_required'] = true;
			}
		}

		/*
			|--------------------------------------------------------------------------
			| AUTH + REGISTRATION WINDOW
			|--------------------------------------------------------------------------
		*/

		private function checkAuthAndWindow(
			?User $user,
			EventOccurrence $occurrence,
			$event,
			GuardResult $result
		): void {
			
			if (!$user) {
				$result->errors[] = 'Для записи необходимо войти в аккаунт.';
			}
			
			$nowUtc = Carbon::now('UTC');
			
			if (
				$occurrence->starts_at &&
				$nowUtc->greaterThanOrEqualTo(
					Carbon::parse($occurrence->starts_at, 'UTC')
				)
			) {
				$result->errors[] = 'Мероприятие уже началось.';
			}
			
            if (!$occurrence->effectiveAllowRegistration()) {
                $result->errors[] = 'Регистрация на мероприятие выключена.';
            }
			
			if (
				$occurrence->registration_starts_at &&
				$nowUtc->lessThan(
					Carbon::parse($occurrence->registration_starts_at, 'UTC')
				)
			) {
				$result->errors[] = 'Регистрация ещё не началась.';
			}

			// ОГРАНИЧИВАЕМЫЙ ПОЛ: отдельное окно регистрации
			$gs = $occurrence->event?->gameSettings ?? null;
			if (
				$gs &&
				$gs->gender_policy === 'mixed_limited' &&
				$gs->gender_limited_side &&
				$gs->gender_limited_reg_starts_days_before !== null &&
				$user &&
				$occurrence->starts_at
			) {
				$viewerGender = strtolower((string) $user->gender);
				$side = $gs->gender_limited_side;
				$targetGender = $side === 'male' ? 'm' : ($side === 'female' ? 'f' : null);

				if ($targetGender && $viewerGender !== '' && $viewerGender[0] === $targetGender) {
					$restrictedOpensAt = Carbon::parse($occurrence->starts_at, 'UTC')
						->subDays((int) $gs->gender_limited_reg_starts_days_before);

					if ($nowUtc->lessThan($restrictedOpensAt)) {
						$label = $side === 'female' ? 'девушек' : 'мужчин';
						$result->errors[] = 'Регистрация для ' . $label . ' ещё не началась — откроется ' .
							$restrictedOpensAt->copy()->setTimezone($occurrence->event?->timezone ?? 'Europe/Moscow')
								->format('d.m.Y H:i') . '.';
					}
				}
			}
			
			if (
				$occurrence->registration_ends_at &&
				$nowUtc->greaterThanOrEqualTo(
					Carbon::parse($occurrence->registration_ends_at, 'UTC')
				)
			) {
				$result->errors[] = 'Регистрация уже завершена.';
			}
		}
		
		/*
			|--------------------------------------------------------------------------
			| AGE (ИСПРАВЛЕНО)
			|--------------------------------------------------------------------------
		*/
		
		private function checkAgePolicy(
			?User $user,
			EventOccurrence $occurrence,
			$event,
			string $policy,
			GuardResult $result
		): void {
			if ($policy === 'any') {
				return;
			}

			if (!$user) {
				$result->errors[] = $policy === 'adult'
					? 'Для записи на мероприятие необходимо войти в аккаунт.'
					: 'Для записи на детское мероприятие необходимо войти в аккаунт.';
				return;
			}

			if (!Schema::hasColumn('users', 'birth_date') || !$user->birth_date) {
				$result->errors[] = $policy === 'adult'
					? 'Для записи на это мероприятие укажи дату рождения в профиле.'
					: 'Для записи на детское мероприятие укажи дату рождения в профиле.';
				return;
			}

			$eventDate = $occurrence->starts_at
				? Carbon::parse($occurrence->starts_at, 'UTC')
				: ($event->starts_at ? Carbon::parse($event->starts_at, 'UTC') : null);

			if (!$eventDate) {
				$result->errors[] = 'Дата мероприятия не указана.';
				return;
			}

			$age = Carbon::parse($user->birth_date)->diffInYears($eventDate);

			if ($policy === 'adult') {
				if ($age < 18) {
					$result->errors[] = 'Это мероприятие только для взрослых (18+).';
				}
				return;
			}

			// policy === 'child'
			$min = (int)($event->child_age_min ?? 0);
			$max = (int)($event->child_age_max ?? 0);

			if ($min > 0 && $age < $min) {
				$result->errors[] = "Возраст участника меньше допустимого для этого мероприятия (нужно от {$min} лет).";
				return;
			}

			if ($max > 0 && $age > $max) {
				$result->errors[] = "Возраст участника больше допустимого для этого мероприятия (нужно до {$max} лет).";
				return;
			}
		}

		/**
		 * Проверяет право участника записаться/встать в резерв без проверки мест.
		 * Используется в листе ожидания.
		 */
		public function checkEligibility(?User $user, EventOccurrence $occurrence): GuardResult
		{
			$result = GuardResult::allow();
			$event  = $occurrence->event;

			if (!$event) {
				$result->addError('Событие не найдено.');
				return $result;
			}

			$agePolicy = $occurrence->age_policy ?? $event->age_policy ?? 'any';

			$this->checkAuthAndWindow($user, $occurrence, $event, $result);
			$this->checkPersonalData($user, $occurrence, $event, $result);
			$this->checkAgePolicy($user, $occurrence, $event, $agePolicy, $result);
			$this->checkLevelPolicy($user, $occurrence, $event, $result);

			// Гендерная политика — нужна чтобы autoBookNext не записал неподходящий пол
			if ($user && empty($result->errors)) {
				$settings   = $event->gameSettings;
				$maxPlayers = (int) ($settings?->max_players ?? 0);
				$registrations = \App\Models\EventRegistration::with('user')
					->where('occurrence_id', $occurrence->id)
					->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
					->whereRaw("(status IS NULL OR status != 'cancelled')")
					->get();
				$this->applyGenderPolicy($user, $settings, $registrations, $maxPlayers, $result);
			}

			return $result;
		}
		
		/*
			|--------------------------------------------------------------------------
			| LEVEL POLICY
			|--------------------------------------------------------------------------
		*/
		
		private function checkLevelPolicy(
			?User $user,
			EventOccurrence $occurrence,
			$event,
			GuardResult $result
		): void {
			if (!$user) {
				return;
			}
			
			$direction = (string) ($event->direction ?? 'classic');
			
			if ($direction === 'beach') {
				$userLevel = $user->beach_level;
				$min = $occurrence->effectiveBeachLevelMin();
				$max = $occurrence->effectiveBeachLevelMax();
			} else {
				$userLevel = $user->classic_level;
				$min = $occurrence->effectiveClassicLevelMin();
				$max = $occurrence->effectiveClassicLevelMax();
			}
			
			$hasRestriction = !is_null($min) || !is_null($max);
			
			if ($hasRestriction && is_null($userLevel)) {
				$result->errors[] = 'Для записи необходимо указать игровой уровень.';
				return;
			}
			
			if (!is_null($min) && $userLevel < $min) {
				$result->errors[] = 'Ваш уровень ниже допустимого для этого мероприятия.';
			}
			
			if (!is_null($max) && $userLevel > $max) {
				$result->errors[] = 'Ваш уровень выше допустимого для этого мероприятия.';
			}
		}
		
		/*
			|--------------------------------------------------------------------------
			| CAPACITY AND POSITIONS (НОВЫЙ МЕТОД)
			|--------------------------------------------------------------------------
		*/
		
		private function checkCapacityAndPositions(
			EventOccurrence $occurrence,
			Collection $registrations,
			int $maxPlayers,
			array $freePositions,
			?string $position,
			bool $isClassic,
			bool $isBeach,
			GuardResult $result
		): void {
			$registeredTotal = $occurrence->registrations_count ?? $registrations->count();
			$remainingTotal = max(0, $maxPlayers - $registeredTotal);
			
			// Пляжка: проверяем только общий лимит мест
			if ($isBeach) {
				if ($maxPlayers > 0 && $remainingTotal <= 0) {
					$result->errors[] = 'Свободных мест на мероприятие больше нет.';
				}
				return;
			}
			
			// Классика: если вообще нет свободных позиций — запись невозможна
			if ($isClassic) {
                if ($maxPlayers > 0 && empty($freePositions)) {
                    $registeredTotal = $occurrence->registrations_count ?? $registrations->count();
                    if ($registeredTotal >= $maxPlayers) {
                        $result->errors[] = 'Свободных мест на мероприятие больше нет.';
                    } else {
                        $result->errors[] = 'Нет доступных позиций для записи.';
                    }
                    return;
                }
            
                if ($maxPlayers <= 0 && empty($freePositions)) {
                    $result->errors[] = 'Для классического волейбола не настроены позиции для записи.';
                    return;
                }
            
                if ($position !== null) {
                    $selected = collect($freePositions)->firstWhere('key', $position);
            
                    if (!$selected || (int) ($selected['free'] ?? 0) <= 0) {
                        $result->errors[] = 'На выбранную позицию мест больше нет.';
                    }
                }
            }
		}
		
		/*
			|--------------------------------------------------------------------------
			| GENDER POLICY
			|--------------------------------------------------------------------------
		*/
		
		private function applyGenderPolicy(
            ?User $user,
            $settings,
            Collection $registrations,
            int $maxPlayers,
            GuardResult $result
        ): array {
			
			$policy = (string)($settings?->gender_policy ?? 'mixed_open');
			
			$viewerGender = null;
			
			if ($user && Schema::hasColumn('users', 'gender')) {
				
				$g = strtolower(trim((string)$user->gender));
				
				if (in_array($g, ['m','male'], true)) $viewerGender = 'm';
				if (in_array($g, ['f','female'], true)) $viewerGender = 'f';
			}
			
			$male = 0;
			$female = 0;
			
			foreach ($registrations as $r) {
				
				$g = strtolower(trim((string)($r->user->gender ?? '')));
				
				if (in_array($g, ['m','male'], true)) $male++;
				if (in_array($g, ['f','female'], true)) $female++;
			}
			
			$genderBlocked = false;
			
			if ($viewerGender && $policy === 'only_male' && $viewerGender !== 'm') {
				
				$genderBlocked = true;
				$result->errors[] = 'Запись доступна только для мужчин.';
			}
			
			if ($viewerGender && $policy === 'only_female' && $viewerGender !== 'f') {
				
				$genderBlocked = true;
				$result->errors[] = 'Запись доступна только для женщин.';
			}
			
			if ($viewerGender && $policy === 'mixed_5050') {
                if ($maxPlayers > 0) {
                    $perGenderLimit = intdiv($maxPlayers, 2);
            
                    if ($viewerGender === 'm' && $male >= $perGenderLimit) {
                        $genderBlocked = true;
                        $result->errors[] = 'Достигнут лимит мужских мест для формата 50/50.';
                    }
            
                    if ($viewerGender === 'f' && $female >= $perGenderLimit) {
                        $genderBlocked = true;
                        $result->errors[] = 'Достигнут лимит женских мест для формата 50/50.';
                    }
                }
            }
			/*
				|--------------------------------------------------------------------------
				| MIXED LIMITED
				|--------------------------------------------------------------------------
			*/
			if ($viewerGender && $policy === 'mixed_limited' && $settings) {
				
				$side = $settings->gender_limited_side;
				$limit = (int)($settings->gender_limited_max ?? 0);
				
				$positions = $settings->gender_limited_positions ?? [];
				
				if (is_string($positions)) {
					$positions = json_decode($positions, true) ?: [];
				}
				
				$targetGender = null;
				
				if ($side === 'male')   $targetGender = 'm';
				if ($side === 'female') $targetGender = 'f';
				
				if ($targetGender && $viewerGender === $targetGender) {
					
					$count = 0;
					
					foreach ($registrations as $r) {
						
						$g = strtolower((string)($r->user->gender ?? ''));
						$pos = $r->position ?? null;
						
						if (
							$g &&
							$g[0] === $targetGender &&
							in_array($pos, $positions, true)
						) {
							$count++;
						}
						
					}
					
					if ($count >= $limit) {
						
						$genderBlocked = true;
						
						$result->errors[] = 'Все места для вашего пола уже заняты.';
						
					}
				}
			}
			return [$genderBlocked, $policy];
		}
		
		/*
			|--------------------------------------------------------------------------
			| SNAPSHOT
			|--------------------------------------------------------------------------
		*/
		
		private function buildAvailabilitySnapshot(
			EventOccurrence $occurrence,
			Collection $registrations,
			int $maxPlayers,
			GuardResult $result,
			array $freePositions,
			string $agePolicy,
			string $policy,
			bool $genderBlocked
		): void {
			
			$registeredTotal = $occurrence->registrations_count ?? $registrations->count();

			$reserveMax = (int) ($occurrence->event->gameSettings?->reserve_players_max ?? 0);
			$totalCapacity = $maxPlayers + $reserveMax;
			$remainingTotal = max(0, $totalCapacity - $registeredTotal);

			$result->data['free_positions'] = $freePositions;

			$meta = [
				'max_players'          => $maxPlayers,
				'reserve_players_max'  => $reserveMax,
				'total_capacity'       => $totalCapacity,
				'registered_total'     => $registeredTotal,
				'remaining_total'      => $remainingTotal,
				'is_registered'    => $result->meta['is_registered'] ?? false,
				'user_position'    => $result->meta['user_position'] ?? null,
				'age_policy'       => $agePolicy,
				'age_blocked'      => false,
				'need_birthdate'   => false,
				'gender_policy'    => $policy,
				'gender_blocked'   => $genderBlocked
			];

			// Данные команд для турнира
			if ((string)($occurrence->event->format ?? '') === 'tournament') {
				$teamsMax  = (int)($occurrence->event->tournament_teams_count ?? 0);
				$regMode   = (string)($occurrence->event->registration_mode ?? '');
				$gsSubtype = (string)($occurrence->event->gameSettings?->subtype ?? '');
				$teamSize  = preg_match('/^(\d+)x\d+$/i', $gsSubtype, $m) ? (int)$m[1] : 2;

				// Для командного турнира (team_beach / team_classic) регистрации
				// хранятся в event_teams, а не в event_registrations.
				if (in_array($regMode, ['team_beach', 'team_classic', 'team'], true)) {
					$teamsRegistered = \App\Models\EventTeam::where('event_id', $occurrence->event_id)
						->where(fn($q) => $q->where('occurrence_id', $occurrence->id)
							->orWhereNull('occurrence_id'))
						->whereIn('status', ['ready','pending_members','submitted','confirmed','approved'])
						->count();
				} else {
					// Обычный турнир — считаем через group_key в регистрациях
					$byGroup = \Illuminate\Support\Facades\DB::table('event_registrations')
						->where('occurrence_id', $occurrence->id)
						->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
						->whereNotNull('group_key')
						->distinct('group_key')
						->count('group_key');
					$teamsRegistered = $byGroup > 0
						? $byGroup
						: (int) ceil($registeredTotal / max(1, $teamSize));
				}

				$meta['tournament_teams_max']        = $teamsMax;
				$meta['tournament_teams_registered'] = $teamsRegistered;
				$meta['tournament_teams_remaining']  = max(0, $teamsMax - $teamsRegistered);
			}

			$result->data['meta'] = $meta;
		}
		
		/*
			|--------------------------------------------------------------------------
			| HELPERS
			|--------------------------------------------------------------------------
		*/
		
		private function normalizePosition(array $context): ?string
		{
			
			if (!array_key_exists('position', $context) || !is_string($context['position'])) {
				return null;
			}
			
			$p = trim($context['position']);
			
			if ($p === '' || mb_strlen($p) > 32) {
				return null;
			}
			
			return $p;
		}
	}