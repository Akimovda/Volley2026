<?php
	
	namespace App\Services\Validation;
	
	use Illuminate\Http\Request;
	use Illuminate\Support\Facades\Validator;
	use Illuminate\Validation\Rule;
	use Illuminate\Contracts\Validation\Validator as ValidatorContract;
	use App\Services\EventRegistrationRules;
	
	class EventCreateValidator
	{
		/**
			* Создаёт валидатор для создания события
		*/
		public function make(Request $request): ValidatorContract
		{
			$validator = Validator::make(
            $request->all() + $request->files->all(),
            [
			
            /*
				|--------------------------------------------------------------------------
				| BASIC
				|--------------------------------------------------------------------------
			*/
			
            'title' => ['nullable','string','max:255'],
			
            'direction' => [
			'required',
			'in:classic,beach'
            ],
            
			
            'format' => [
			'required',
			'in:game,training,training_game,training_pro_am,coach_student,tournament,camp'
            ],
            'registration_mode' => [
                'nullable',
                'string',
                'max:32'
            ],
			
            'allow_registration' => ['required','boolean'],
            'bot_assistant_enabled'      => ['sometimes', 'boolean'],
            'bot_assistant_threshold'    => ['sometimes', 'integer', 'min:5', 'max:30'],
            'bot_assistant_max_fill_pct' => ['sometimes', 'integer', 'min:10', 'max:60'],
			
            'age_policy' => [
			'nullable',
			'in:adult,child,any'
            ],
			
            /*
				|--------------------------------------------------------------------------
				| TRAINERS
				|--------------------------------------------------------------------------
			*/
			
            'trainer_user_ids' => ['nullable','array'],
			
            'trainer_user_ids.*' => [
			'integer',
			'min:1',
			'distinct'
            ],
			
            'trainer_user_id' => [
			'nullable',
			'integer',
			'min:1'
            ],
           
			
            /*
				|--------------------------------------------------------------------------
				| BEACH FLAGS
				|--------------------------------------------------------------------------
			*/
			
            'is_snow' => ['nullable','boolean'],
            'with_minors' => ['nullable','boolean'],
			
            /*
				|--------------------------------------------------------------------------
				| TIME
				|--------------------------------------------------------------------------
			*/
			
            'timezone' => ['nullable','string','timezone'],
			
            'starts_at_local' => [
			'required',
			'date_format:Y-m-d H:i:s,Y-m-d H:i,Y-m-d\TH:i,Y-m-d\TH:i:s'
            ],
			
            'duration_sec' => [
                'nullable',
                'integer',
                'min:300',
                'max:' . (10 * 24 * 3600)
            ],
            'duration_days' => ['nullable', 'integer', 'min:0', 'max:10'],
            'duration_hours' => ['nullable', 'integer', 'min:0', 'max:23'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:59'],
			
            /*
				|--------------------------------------------------------------------------
				| LOCATION
				|--------------------------------------------------------------------------
			*/
			
            'city_id' => [
			'nullable',
			'integer',
			'exists:cities,id'
            ],
			
            'location_id' => [
			'required',
			'integer',
			Rule::exists('locations','id')
			->where(function ($q) use ($request) {
				
				$cityId = (int)$request->input('city_id',0);
				
				if ($cityId > 0) {
					$q->where('city_id',$cityId);
				}
			})
            ],
			
            /*
				|--------------------------------------------------------------------------
				| LEVELS
				|--------------------------------------------------------------------------
			*/
			
            'classic_level_min' => ['nullable','integer','min:0','max:7'],
            'classic_level_max' => ['nullable','integer','min:0','max:7'],
			
            'beach_level_min' => ['nullable','integer','min:0','max:7'],
            'beach_level_max' => ['nullable','integer','min:0','max:7'],
			
            /*
				|--------------------------------------------------------------------------
				| GAME SETTINGS
				|--------------------------------------------------------------------------
			*/
			
            'game_subtype' => [
			'nullable',
			'in:4x4,4x2,5x1,2x2,3x3'
            ],
			
            'teams_count' => [
			'nullable',
			'integer',
			'min:2',
			'max:200'
            ],
            
            'game_min_players' => [
			'nullable',
			'integer',
			'min:1',
			'max:99'
            ],
			
            'game_max_players' => [
			'nullable',
			'integer',
			'min:1',
			'max:99'
            ],

            'game_reserve_players_max' => [
			'nullable',
			'integer',
			'min:0',
			'max:20'
            ],

            'game_allow_girls' => ['nullable','boolean'],
			
            'game_girls_max' => [
			'nullable',
			'integer',
			'min:0',
			'max:99'
            ],
			
            'game_libero_mode' => [
			'nullable',
			'in:with_libero,without_libero'
            ],
			
            'game_has_libero' => ['nullable','boolean'],
			
            'game_positions' => ['nullable','array'],
			
            'game_positions.*' => [
			'in:setter,outside,opposite,middle,libero'
            ],
			 /*
				|--------------------------------------------------------------------------
				| TOURNAMENT SETTINGS
				|--------------------------------------------------------------------------
			*/

            'tournament_game_scheme' => [
                'nullable',
                'in:4x4,4x2,5x1,5x1_libero,2x2,3x3'
            ],

            'tournament_team_size_min' => [
                'nullable',
                'integer',
                'min:1',
                'max:50'
            ],

            'tournament_reserve_players_max' => [
                'nullable',
                'integer',
                'min:0',
                'max:20'
            ],

            'tournament_total_players_max' => [
                'nullable',
                'integer',
                'min:1',
                'max:50'
            ],

            'tournament_max_rating_sum' => [
                'nullable',
                'integer',
                'min:0',
                'max:100000'
            ],
            
            'tournament_teams_count' => [
                'nullable',
                'integer',
                'min:3',
                'max:100'
            ],
            'child_age_min' => [
                'nullable',
                'integer',
                'min:6',
                'max:17',
            ],
            
            'child_age_max' => [
                'nullable',
                'integer',
                'min:6',
                'max:17',
            ],
            'tournament_captain_confirms_members' => ['nullable', 'boolean'],

            'tournament_auto_submit_when_ready' => ['nullable', 'boolean'],
            'tournament_allow_incomplete_application' => ['nullable', 'boolean'],
            'tournament_application_mode' => ['nullable', 'string', 'in:auto,manual'],

            'tournament_payment_mode' => ['nullable', 'string', 'in:team,per_player'],
            'tournament_seeding_mode' => [
                'nullable',
                'in:manual,random,rating'
            ],
            /*
				|--------------------------------------------------------------------------
				| GENDER POLICY
				|--------------------------------------------------------------------------
			*/
			
            'game_gender_policy' => [
			'nullable',
			'in:only_male,only_female,mixed_open,mixed_limited,mixed_5050'
            ],
			
            'game_gender_limited_side' => [
			'nullable',
			'in:male,female'
            ],
			
            'game_gender_limited_reg_starts_days_before' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],

            'game_gender_limited_max' => [
			'nullable',
			'integer',
			'min:0',
			'max:99'
            ],
			
            'game_gender_limited_positions' => ['nullable','array'],
			
            'game_gender_limited_positions.*' => [
			'in:setter,outside,opposite,middle,libero'
            ],
			
            /*
				|--------------------------------------------------------------------------
				| PRIVACY / PAYMENT
				|--------------------------------------------------------------------------
			*/
			
            'is_private' => ['nullable','boolean'],
            'is_paid' => ['nullable','boolean'],
			
            
            'price_amount' => [
                'nullable',
                'numeric',
                'min:10',
                'max:500000',
            ],
            
            'price_currency' => [
                'nullable',
                'string',
                'size:3',
                'in:RUB,USD,EUR,KZT,KGS,BYN,UZS,AMD,AZN,TJS,TMT,GEL,MDL',
            ],
			

            'payment_method'      => ['nullable', 'string', 'in:cash,tbank_link,sber_link,yoomoney'],
            'payment_link'        => ['nullable', 'url', 'max:500'],
            'refund_hours_full'   => ['nullable', 'integer', 'min:0', 'max:720'],
            'refund_hours_partial'=> ['nullable', 'integer', 'min:0', 'max:720'],
            'refund_partial_pct'  => ['nullable', 'integer', 'min:0', 'max:100'],
            'requires_personal_data' => ['nullable','boolean'],
			
            'organizer_id' => [
			'nullable',
			'integer',
			'min:1'
            ],
			
            /*
				|--------------------------------------------------------------------------
				| RECURRING
				|--------------------------------------------------------------------------
			*/
			
            'is_recurring' => ['nullable','boolean'],
			
            'recurrence_rule' => [
			'nullable',
			'string',
			'max:255'
            ],
			
            'recurrence_type' => [
			'nullable',
			'in:daily,weekly,monthly'
            ],
			
            'recurrence_interval' => [
			'nullable',
			'integer',
			'min:1',
			'max:365'
            ],
			
			'recurrence_weekdays' => ['nullable','array'],
			'recurrence_weekdays.*' => ['integer','min:1','max:7'],		
			
			
			'recurrence_end_type' => [
			'nullable',
			'in:none,until,count'
			],
			'recurrence_end_until' => [
			'nullable',
			'date'
			],
			'recurrence_end_count' => [
			'nullable',
			'integer',
			'min:1',
			'max:365'
			],
			
			
			
            'recurrence_months' => ['nullable','array'],
			
            'recurrence_months.*' => [
			'integer',
			'min:1',
			'max:12'
            ],
			
            /*
				|--------------------------------------------------------------------------
				| REMINDERS
				|--------------------------------------------------------------------------
			*/
			
            'remind_registration_enabled' => ['nullable','boolean'],
			
            'remind_registration_minutes_before' => [
			'nullable',
			'integer',
			'min:0',
			'max:10080'
            ],
			
            'show_participants' => ['nullable','boolean'],
			
            /*
				|--------------------------------------------------------------------------
				| DESCRIPTION
				|--------------------------------------------------------------------------
			*/
			
            'description_html' => [
			'nullable',
			'string',
			'max:500000'
            ],
			
            /*
				|--------------------------------------------------------------------------
				| REGISTRATION WINDOWS
				|--------------------------------------------------------------------------
			*/
			
            'reg_starts_days_before' => [
			'nullable',
			'integer',
			'min:0',
			'max:365'
            ],

            'reg_ends_minutes_before' => [
			'nullable',
			'integer',
			'min:0',
			'max:10080'
            ],

            'cancel_lock_minutes_before' => [
			'nullable',
			'integer',
			'min:0',
			'max:10080'
            ],

            // Named select fields (server-side computation)
            'reg_starts_d' => ['nullable', 'integer', 'min:0', 'max:90'],
            'reg_starts_h' => ['nullable', 'integer', 'min:0', 'max:23'],
            'reg_ends_h'   => ['nullable', 'integer', 'min:0', 'max:24'],
            'reg_ends_m'   => ['nullable', 'integer', 'min:0', 'max:59'],
            'cancel_lock_h' => ['nullable', 'integer', 'min:0', 'max:24'],
            'cancel_lock_m' => ['nullable', 'integer', 'min:0', 'max:59'],
			
			]);
			
			/*
				|--------------------------------------------------------------------------
				| CUSTOM VALIDATION
				|--------------------------------------------------------------------------
			*/
			
            $validator->after(function ($v) use ($request) {
            $data = $v->getData();
        
            $format = (string) ($data['format'] ?? '');
            $direction = (string) ($data['direction'] ?? 'classic');
            $policy = (string) ($data['game_gender_policy'] ?? '');

            // restricted reg start не может быть раньше общего (days_before_restricted <= days_before_general)
            if ($policy === 'mixed_limited') {
                $restrDays = $data['game_gender_limited_reg_starts_days_before'] ?? null;
                $generalDays = $data['reg_starts_days_before'] ?? null;
                if ($restrDays !== null && $restrDays !== '' &&
                    $generalDays !== null && $generalDays !== '' &&
                    (int) $restrDays > (int) $generalDays
                ) {
                    $v->errors()->add('game_gender_limited_reg_starts_days_before',
                        'Начало регистрации для ограничиваемого пола не может быть раньше общего (значение в днях должно быть меньше или равно общему).');
                }
            }

            $agePolicy = (string)($data['age_policy'] ?? 'adult');
            $childAgeMin = $data['child_age_min'] ?? null;
            $childAgeMax = $data['child_age_max'] ?? null;
            
            if ($agePolicy === 'child') {
                if (is_null($childAgeMin) || is_null($childAgeMax)) {
                    $v->errors()->add('child_age_min', 'Укажи допустимый возраст детей.');
                } elseif ((int)$childAgeMin > (int)$childAgeMax) {
                    $v->errors()->add('child_age_min', 'Минимальный возраст не может быть больше максимального.');
                }
            }
        
            $isTournament = in_array($format, ['tournament', 'tournament_classic', 'tournament_beach'], true);
            $durationSec = isset($data['duration_sec']) && $data['duration_sec'] !== ''
                ? (int) $data['duration_sec']
                : null;
            
            $durationDays = (int) ($data['duration_days'] ?? 0);
            $durationHours = (int) ($data['duration_hours'] ?? 0);
            $durationMinutes = (int) ($data['duration_minutes'] ?? 0);
            
            $durationPartsSec = ($durationDays * 86400) + ($durationHours * 3600) + ($durationMinutes * 60);
            
            if (($durationSec ?? 0) <= 0 && $durationPartsSec <= 0) {
                $v->errors()->add('duration_sec', 'Укажи длительность мероприятия.');
            }
            
            if (($durationSec ?? 0) === 0 && $durationPartsSec > 0 && $durationPartsSec < 300) {
                $v->errors()->add('duration_sec', 'Минимальная длительность — 5 минут.');
            }
            
            if (($durationSec ?? 0) === 0 && $durationPartsSec > (10 * 24 * 3600)) {
                $v->errors()->add('duration_sec', 'Слишком большая длительность мероприятия.');
            }
            /*
            |--------------------------------------------------------------------------
            | MAX PLAYERS (game vs tournament)
            |--------------------------------------------------------------------------
            */
        
            $maxRaw = $isTournament
                ? ($data['tournament_total_players_max'] ?? null)
                : ($data['game_max_players'] ?? null);
        
            $maxField = $isTournament
                ? 'tournament_total_players_max'
                : 'game_max_players';
        
            $max = ($maxRaw === null || $maxRaw === '')
                ? 0
                : (int) $maxRaw;
        
            if ($policy === 'mixed_5050' && $max > 0) {
                if ($max < 2) {
                    $v->errors()->add($maxField, 'Для 50/50 минимум 2 участника.');
                } elseif ($max % 2 !== 0) {
                    $v->errors()->add($maxField, 'Для 50/50 макс. участников должен быть чётным.');
                }
            }
        
            /*
            |--------------------------------------------------------------------------
            | TOURNAMENT RULES
            |--------------------------------------------------------------------------
            */
        
            if ($isTournament) {
                $teamMin = (int) ($data['tournament_team_size_min'] ?? 0);
                $totalMax = (int) ($data['tournament_total_players_max'] ?? 0);
                $scheme = (string) ($data['tournament_game_scheme'] ?? '');
                $teamsCount = (int) ($data['tournament_teams_count'] ?? 0);
            
                if ($teamsCount <= 0) {
                    $teamsCount = 4;
                }
            
                if ($teamMin > 0 && $totalMax > 0 && $totalMax < $teamMin) {
                    $v->errors()->add(
                        'tournament_total_players_max',
                        'Максимальный размер команды не может быть меньше минимума игроков.'
                    );
                }
            
                if ($direction === 'classic' && in_array($scheme, ['2x2', '3x3'], true)) {
                    $v->errors()->add(
                        'tournament_game_scheme',
                        'Для классического турнира недоступна пляжная схема.'
                    );
                }
            
                if ($direction === 'beach' && in_array($scheme, ['4x2', '5x1', '5x1_libero'], true)) {
                    $v->errors()->add(
                        'tournament_game_scheme',
                        'Для пляжного турнира недоступна классическая схема.'
                    );
                }
            
                if ($teamsCount < 3 || $teamsCount > 100) {
                    $v->errors()->add(
                        'tournament_teams_count',
                        'Количество команд должно быть от 3 до 100.'
                    );
                }
            }
                    
            /*
            |--------------------------------------------------------------------------
            | REGISTRATION MODE
            |--------------------------------------------------------------------------
            */
        
            $registrationMode = (string) ($data['registration_mode'] ?? 'single');
        
            if ($isTournament) {
                $registrationMode = $direction === 'beach'
                    ? 'team_beach'
                    : 'team_classic';
            }
        
            try {
                EventRegistrationRules::assertModeAllowed($direction, $registrationMode);
            } catch (\DomainException $e) {
                $v->errors()->add('registration_mode', $e->getMessage());
            }
        });
			return $validator;
		}
	}		