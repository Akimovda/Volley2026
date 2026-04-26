						
						{{-- Admin organizer --}}
						@if(!empty($canChooseOrganizer))
						<div class="ramka" style="z-index:10">	
							<h2 class="-mt-05">Назначение организатора</h2>	
							<div class="row">	
								<div class="col-md-6">
									<div class="card">
										<select name="organizer_id">
											<option value="" selected>— выбрать организатора —</option>
											@foreach($organizers as $org)
											<option value="{{ $org->id }}"
											@selected(old('organizer_id', $prefill['organizer_id'] ?? '') == $org->id)>
												#{{ $org->id }} — {{ $org->name ?? $org->email }}
											</option>
											@endforeach
										</select>
										<ul class="list f-16 mt-1">
											<li>Можно не выбирать — тогда организатором станет текущий admin.</li>
										</ul>	
									</div>
								</div>
							</div>
						</div>
						@else
						{{--
						{{ $resolvedOrganizerLabel ?? '—' }}
						--}}
						@endif					
						
						
						
						<div class="row row2">	
							<div class="col-md-12">
								<div class="ramka" style="z-index: 9">
									<h2 class="-mt-05">Настройка мероприятия</h2>	
									<div class="row">
										
										<div class="col-md-6">
											<div class="card pb-2">
												<label>Направление</label>
												<select name="direction" id="direction">
													<option value="classic" @selected(old('direction', $prefill['direction'] ?? 'classic')==='classic')>Классический волейбол</option>
													<option value="beach" @selected(old('direction', $prefill['direction'] ?? '')==='beach')>Пляжный волейбол</option>
												</select>
												@error('direction')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
												
												<div class="mt-2">
													<label>Название мероприятия</label>
													<input type="text"
													name="title"
													value="{{ old('title', $prefill['title'] ?? '') }}"
													class="w-full rounded-lg border-gray-200"
													placeholder="Напр. Вечерняя игра 6х6">
													@error('title')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
													@enderror
													@php
													$agePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'adult');
													@endphp													
												</div>
											</div>
										</div>							
										
										<div class="col-md-6">
											<div class="card">
												<label>Тип мероприятия</label>
												<select name="format" id="format" class="w-full rounded-lg border-gray-200">
													@foreach($formats as $k => $label)
													<option value="{{ $k }}" @selected(old('format', $prefill['format'] ?? 'game')===$k)>{{ $label }}</option>
													@endforeach
												</select>
												@error('format')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												<div class="pb-05"></div>
												{{--
												<div class="text-xs text-gray-500 mt-1">
													“Тренер + ученик” доступен только при “Пляжный волейбол”.
												</div>
												--}}
												
												
												{{-- ✅ Климатические условия только для пляжа + "Игра" --}}
												<div id="climate_block" class="mt-1" data-show-if="direction=beach,format=game">
													<label>Климатические условия</label>
													
													<label class="checkbox-item" id="is_snow_wrap">
														<input type="hidden" name="is_snow" value="0">
														<input type="checkbox" name="is_snow" value="1" id="is_snow"
														@checked(old('is_snow', $prefill['is_snow'] ?? false))>
														<div class="custom-checkbox"></div>
														<span>Снег / зима</span>
													</label>
												</div>											
												
												
												{{-- ✅ TRAINER (только training/training_game) --}}
												@php
												$fmt0 = (string)old('format', $prefill['format'] ?? 'game');
												$showTrainer0 = in_array($fmt0, ['training','training_game','training_pro_am','camp','coach_student'], true);
												@endphp
												<div class="mt-1" id="trainer_block" data-show-if="format=training|training_game|camp|coach_student">
													
													<label>Тренеры</label>
													
													<div class="ac-box">
														{{-- chips --}}
														<div id="trainer_chips" class="mb-1">
															@foreach($oldTrainerIds as $tid)
															<div class="d-flex fvc mb-1 between f-16 pl-1 pr-1">
																<span>#{{ (int)$tid }}</span>
																<button type="button" class="btn btn-small btn-secondary trainer-chip-remove" data-id="{{ (int)$tid }}">×</button>
															</div>
															<input type="hidden" name="trainer_user_ids[]" value="{{ (int)$tid }}" data-trainer-hidden="{{ (int)$tid }}">
															@endforeach
														</div>
														
														<input type="text"
														id="trainer_search"
														placeholder="Начни вводить имя или фамилию"
														value=""
														autocomplete="off">
														
														{{-- legacy hidden (первый тренер, чтобы старые места не ломались) --}}
														<input type="hidden" name="trainer_user_id" id="trainer_user_id_legacy" value="{{ $oldTrainerIds[0] ?? '' }}">
														<input type="hidden" name="trainer_user_label" id="trainer_user_label" value="{{ e($oldTrainerLabel) }}">
														
														<div id="trainer_dd" class="form-select-dropdown trainer_dd"></div>
													</div>
													
													<ul class="list f-16 mt-1">
														<li>Можно выбрать несколько тренеров.</li>
														<li><a onclick="return false;" href="#" type="button" id="trainer_clear" class="f-16 blink">Сбросить</a></li>
													</ul>										
													
													{{--
													<div class="text-xs text-gray-500 mt-1">
														Поле показывается только для “Тренировка”, “Тренировка + Игра”, “Тренер + ученик”, “Кемп”.
													</div>
													--}}
												</div>											
											</div>
										</div>											
										
										
										
										<div class="col-md-6" id="game_settings_block" data-hide-if="format=tournament">
											<div class="card">
												<div class="row">
													<div class="col-4">
														
														<label>Подтип</label>
														<select name="game_subtype" id="game_subtype" class="w-full rounded-lg border-gray-200">
															<!-- <option value="">— выбрать —</option> -->
															<option value="4x4" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='4x4')>4×4</option>
															<option value="4x2" @selected(old('game_subtype', $prefill['game_subtype'] ?? '4x2')==='4x2')>4×2</option>
															<option value="5x1" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='5x1')>5×1</option>
														</select>										
														@error('game_subtype')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
														@enderror
													</div>
													<div class="col-4">	
														<label>Команды</label>
														
														<input
														type="number"
														name="teams_count"
														id="teams_count"
														class="form-control"
														value="{{ old('teams_count', 2) }}"
														min="2"
														max="200"
														>		
														
													</div>
													
													
													<div class="col-4">
														<label>Минимум</label>
														<input type="number"
														name="game_min_players"
														id="game_min_players"
														min="0" max="99"
														value="{{ old('game_min_players', $prefill['game_min_players'] ?? 8) }}"
														class="w-full rounded-lg border-gray-200"
														placeholder="">
														@error('game_min_players')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
														@enderror
														<div id="game_min_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
													</div>
													
													
													<div class="hidden">
														<label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
														<input type="number"
														name="game_max_players"
														id="game_max_players"
														max="99"
														value="{{ old('game_max_players', $prefill['game_max_players'] ?? '') }}"
														class="w-full rounded-lg border-gray-200"
														placeholder="например 12">
														<div id="game_max_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
													</div>
												</div>
												
												{{-- reserve_players_max --}}
												<div class="mt-1" data-show-if="direction=classic">
													<label class="pt-05">Запасные игроки</label>
													<select name="game_reserve_players_max" id="game_reserve_players_max" class="w-full rounded-lg border-gray-200">
														<option value="" @selected(!old('game_reserve_players_max', $prefill['game_reserve_players_max'] ?? ''))>Нет</option>
														@for($i = 1; $i <= 10; $i++)
															<option value="{{ $i }}" @selected((int)old('game_reserve_players_max', $prefill['game_reserve_players_max'] ?? 0) === $i)>{{ $i }}</option>
														@endfor
													</select>
												</div>

												{{-- libero_mode --}}
												<div id="libero_mode_block" class="mt-1" data-show-if="direction=classic,game_subtype=5x1">
													<label class="pt-05">Режим либеро</label>
													<select name="game_libero_mode" id="game_libero_mode" class="w-full rounded-lg border-gray-200">
														<option value="with_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? 'with_libero')==='with_libero')>С либеро (отдельная позиция)</option>
														<option value="without_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? '')==='without_libero')>Без либеро</option>
													</select>
													
												</div>											
												<ul class="list f-16 mt-1">
													<li>Максимум <strong class="cd" id="players_preview">0</strong></li>
													<li>Позиции для записи будут расчитано автоматически.</li>
													<li>Если на мероприятие запишется меньше минимума игроков, оно будет автоматически отменено</li>
												</ul>						
											</div>
										</div>										
										
										
										
										<div class="col-md-6" id="registration_mode_block" data-hide-if="format=tournament">
											<div class="card">
												<label>Режим регистрации</label>
												
												<select name="registration_mode" id="registration_mode" class="w-full rounded-lg border-gray-300">
													<option value="single"
													@selected($registrationMode === 'single')
													data-direction="classic beach">
														Одиночная запись игроков
													</option>
													
													<option value="team"
													@selected($registrationMode === 'team')
													data-direction="classic">
														Командная запись
													</option>
													
													<option value="mixed_group"
													@selected($registrationMode === 'mixed_group')
													data-direction="beach">
														Групповая / смешанная запись
													</option>
												</select>
												@error('registration_mode')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												<ul class="list f-16 mt-1">
													<li id="registration_mode_hint_classic" data-show-if="direction=classic">
														Для классики: либо одиночная запись игроков, либо полноценная командная запись.
													</li>
													<li id="registration_mode_hint_beach" data-show-if="direction=beach">
														Для пляжа: можно либо записываться по одному, либо объединять игроков в группы по подтипу игры.
													</li>
												</ul>
											</div>
										</div>
									</div>
								</div>
								
								<div class="ramka" id="tournament_settings_block" data-show-if="format=tournament">
									<h2 class="-mt-05">Настройки турнира</h2>	
									<div class="row">
										<div class="col-md-4">
                                            <div class="card">
												<label>Схема игры</label>
												<select
												name="tournament_game_scheme"
												id="tournament_game_scheme"
												class="w-full rounded-lg border-gray-200"
												>
													<option value="">— выбрать —</option>
													<option value="2x2" @selected((string)$tournamentGameScheme === '2x2')>2x2</option>
													<option value="3x3" @selected((string)$tournamentGameScheme === '3x3')>3x3</option>
													<option value="4x4" @selected((string)$tournamentGameScheme === '4x4')>4x4</option>
													<option value="4x2" @selected((string)$tournamentGameScheme === '4x2')>4x2</option>
													<option value="5x1" @selected((string)$tournamentGameScheme === '5x1')>5x1</option>
													<option value="5x1_libero" @selected((string)$tournamentGameScheme === '5x1_libero')>5x1 с либеро</option>
												</select>
												@error('tournament_game_scheme')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
                                                <div class="mt-2">
                                                    <label for="tournament_teams_count">Кол-во команд в турнире</label>
                                                    <input
													type="number"
													id="tournament_teams_count"
													name="tournament_teams_count"
													min="3"
													max="100"
													step="1"
													value="{{ old('tournament_teams_count', $tournamentTeamsCount ?? 4) }}"
													class="w-full rounded-lg border-gray-200"
                                                    >
													
													<ul class="list f-16 mt-1">
														<li>От 3 до 100, по умолчанию 4.</li>
													</ul>													
													
                                                    @error('tournament_teams_count')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                    @enderror
												</div>										
											</div>
										</div>	
										
										
										
										<div class="col-md-8">
                                            <div class="card">												
												
												<label for="tournament_team_size_min">Настройка состава команды</label>
												<hr class="mb-1">
												<div class="row">
													
													<div class="col-sm-4">
														<label class="b-500">Основной состав</label>
                                                        <input
														type="number"
														name="tournament_team_size_min"
														id="tournament_team_size_min"
														min="1"
														max="50"
														value="{{ $tournamentTeamSizeMin }}"
														class="w-full rounded-lg border-gray-200"
														readonly
                                                        >
														<ul class="list f-16 mt-1">
															<li>Определяется автоматически по выбранной схеме игры.</li>
														</ul>	
                                                        @error('tournament_team_size_min')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
													
                                                    <div class="col-sm-4" id="reserve_players_wrap">
														<label class="b-500">Макс. запасных игроков</label>
                                                        <input
														type="number"
														name="tournament_reserve_players_max"
														id="tournament_reserve_players_max"
														min="0"
														max="20"
														value="{{ $tournamentReservePlayersMax }}"
														class="w-full rounded-lg border-gray-200"
                                                        >
														<ul class="list f-16 mt-1">
															<li>Сколько запасных сверх основного состава можно заявить.</li>
														</ul>														
                                                        @error('tournament_reserve_players_max')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
													
													<div class="col-sm-4" id="total_players_wrap">
                                                        <label class="b-500">Макс. всего игроков</label>
                                                        <input
														type="number"
														name="tournament_total_players_max"
														id="tournament_total_players_max"
														min="1"
														max="50"
														value="{{ $tournamentTotalPlayersMax }}"
														class="w-full rounded-lg border-gray-200"
														readonly
                                                        >
														<ul class="list f-16 mt-1">
															<li>Основной состав + максимум запасных.</li>
														</ul>														
                                                        @error('tournament_total_players_max')
														<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                        @enderror
													</div>
												</div>
												
                                                <div
												class="mt-3"
												id="tournament_rating_sum_wrap"
												data-show-if="direction=beach,format=tournament"
                                                >
                                                    <label>Лимит суммы рейтинга</label>
                                                    <input
													type="number"
													name="tournament_max_rating_sum"
													id="tournament_max_rating_sum"
													min="0"
													max="100000"
													value="{{ $tournamentMaxRatingSum }}"
													class="w-full rounded-lg border-gray-200"
													placeholder="Например 12"
                                                    >
<ul class="list f-16 mt-1"><li>Максимальная сумма рейтингов всех игроков команды. Ограничивает сильные составы для честной игры. Оставьте пустым если не нужно.</li></ul>
                                                    @error('tournament_max_rating_sum')
													<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                    @enderror
												</div>
											</div>
										</div>
										<div class="col-md-4">
											<div class="card">
												<label>Уведомления</label>
												<label class="checkbox-item">
													<input type="hidden" name="tournament_captain_confirms_members" value="0">
													<input
													type="checkbox"
													name="tournament_captain_confirms_members"
													value="1"
													@checked($tournamentCaptainConfirmsMembers)
													>
													<div class="custom-checkbox"></div>
													<span>Капитан подтверждает участников</span>
												</label>
												
												<label class="checkbox-item">
													<input type="hidden" name="tournament_auto_submit_when_ready" value="0">
													<input
													type="checkbox"
													name="tournament_auto_submit_when_ready"
													value="1"
													@checked($tournamentAutoSubmitWhenReady)
													>
													<div class="custom-checkbox"></div>
													<span>Автоматически подавать заявку, когда состав готов</span>
												</label>
											</div>
										</div>
									</div>	
								</div>
							</div>
							<div class="col-md-12">
								<div class="ramka" style="z-index: 8">
									<h2 class="-mt-05">Ограничения</h2>	
									<div class="row">
										
										<div class="col-md-6" id="age_policy_block">
											<div class="card">
												<label>Возрастные ограничения</label>
												
												<label class="radio-item">
													<input checked type="radio" name="age_policy" value="adult">
													<div class="custom-radio"></div>
													<span>Для взрослых</span>
												</label>
												
												<label class="radio-item">
													<input type="radio" name="age_policy" value="child">
													<div class="custom-radio"></div>
													<span>Для детей</span>
												</label>
												
												<div id="child_age_wrap" class="{{ old('age_policy', $prefill['age_policy'] ?? 'adult') === 'child' ? '' : 'hidden' }}">
													<div class="row mt-1">
														<div class="col-md-6">
															<label class="form-label">Возраст от</label>
															<input
															type="number"
															name="child_age_min"
															class="form-input"
															min="6"
															max="17"
															step="1"
															value="{{ old('child_age_min', $prefill['child_age_min'] ?? 6) }}"
															placeholder="Например: 8"
															>
															@error('child_age_min')
															<div class="text-danger small mt-1">{{ $message }}</div>
															@enderror
														</div>
														
														<div class="col-md-6">
															<label class="form-label">Возраст до</label>
															<input
															type="number"
															name="child_age_max"
															class="form-input"
															min="6"
															max="17"
															step="1"
															value="{{ old('child_age_max', $prefill['child_age_max'] ?? 17) }}"
															placeholder="Например: 14"
															>
															@error('child_age_max')
															<div class="text-danger small mt-1">{{ $message }}</div>
															@enderror
														</div>
													</div>
													
													<ul class="list f-16 mt-1 mb-2">
														<li>Допустимый возраст участников: от 6 до 17 лет.</li>
													</ul>											
													
												</div>												
												
												
												
												<label class="radio-item">
													<input type="radio" name="age_policy" value="any">
													<div class="custom-radio"></div>
													<span>Без ограничений</span>
												</label>
												
											</div>	
										</div>
										
										
										
										{{-- Game config --}}
										<div class="col-md-6">
											<div class="card">
												{{--
												<div class="text-sm font-semibold text-gray-800">Игровые настройки</div>
												<div class="text-xs text-gray-500 mt-1" id="game_defaults_hint">
													Количество игроков рассчитывается автоматически на основе выбранного формата команды.
												</div>
												
												<div class="text-xs text-gray-500 mt-1" id="game_players_hint"></div>
												<div class="text-xs text-gray-500 mt-1">
													Доступно для классического волейбола при подтипе 5×1.
												</div>
												--}}	
												
												
												
												{{-- Gender policy --}}
												{{--
												<div class="text-sm font-semibold text-gray-800">Гендерные ограничения</div>
												
												<div class="text-xs text-gray-500 mt-1">
													Лимит по мероприятию главный: <span class="font-semibold">max_players</span>. Гендерные лимиты — дополнительные.
												</div>
												--}}
												
												
												<label>Гендерные ограничения</label>
												<select name="game_gender_policy" id="game_gender_policy" class="w-full rounded-lg border-gray-200">
													<option value="mixed_open" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? 'mixed_open')==='mixed_open')>
														М/Ж (без ограничений)
													</option>
													{{-- ✅ 50/50 (ТОЛЬКО ДЛЯ BEACH, но можно показывать всегда и скрывать JS-ом) --}}
													<option value="mixed_5050" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='mixed_5050')>
														Микс 50/50
													</option>
													
													<option value="only_male" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_male')>Только М</option>
													<option value="only_female" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_female')>Только Ж</option>
													{{-- ✅ limited имеет смысл ТОЛЬКО для classic --}}
													<option value="mixed_limited" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='mixed_limited')>
														М/Ж (с ограничениями)
													</option>
												</select>
												<div class="pb-05"></div>
												<div id="gender_5050_hint" class="text-sm text-gray-500 mt-2 hidden"></div>
												
												
												<div id="gender_limited_side_wrap" class="mt-1 hidden">
													<label>Кого ограничиваем</label>
													@php
													$sideVal = old('game_gender_limited_side', $prefill['game_gender_limited_side'] ?? 'female');
													@endphp
													<div class="d-flex mt-1">
														<label class="radio-item">
															<input type="radio" name="game_gender_limited_side" value="female" @checked($sideVal==='female')>
															<div class="custom-radio"></div>
															<span class="text-sm font-semibold">Женщин</span>
														</label>
														<label class="radio-item ml-2">
															<input type="radio" name="game_gender_limited_side" value="male" @checked($sideVal==='male')>
															<div class="custom-radio"></div>
															<span class="text-sm font-semibold">Мужчин</span>
														</label>
													</div>
													{{--
													<div class="mt-1">
														Ограничиваемый пол получает лимит мест (ниже).
													</div>
													--}}
												</div>
												
												<div id="gender_limited_max_wrap" class="mt-1 hidden">
													<label>Макс. мест для ограничиваемых</label>
													<div class="row">
														<div class="col-sm-6">
															
															<input type="number"
															name="game_gender_limited_max"
															id="game_gender_limited_max"
															value="{{ old('game_gender_limited_max', $prefill['game_gender_limited_max'] ?? '') }}"
															class="w-full rounded-lg border-gray-200"
															min="0" max="99"
															placeholder="например, 2">
														</div>
													</div>
												</div>
												
												
												<div id="gender_limited_positions_wrap" class="mt-1 hidden">
													<label>Позиции, доступные ограничиваемому полу</label>
													
													
													
													<div id="gender_positions_box" class=""></div>
													
													@php
													$oldLimitedPositions = old('game_gender_limited_positions', $prefill['game_gender_limited_positions'] ?? []);
													if (is_string($oldLimitedPositions)) $oldLimitedPositions = [$oldLimitedPositions];
													if (!is_array($oldLimitedPositions)) $oldLimitedPositions = [];
													@endphp
													<input type="hidden" id="gender_positions_old_json" value="{{ e(json_encode(array_values($oldLimitedPositions))) }}">
													
													<ul class="list f-16 mt-1">
														<li><a onclick="return false;" href="#" type="button" id="gender_positions_clear" class="f-16 blink">Сбросить</a></li>
													</ul>													
													
													
												</div>
												
												{{-- legacy hidden (compat) --}}
												<input type="hidden" name="game_allow_girls" id="game_allow_girls_legacy" value="{{ old('game_allow_girls', $prefill['game_allow_girls'] ?? 1) ? 1 : 0 }}">
												<input type="hidden" name="game_girls_max" id="game_girls_max_legacy" value="{{ old('game_girls_max', $prefill['game_girls_max'] ?? '') }}">
												
												
												
												
												
											</div>
										</div>										
										
										{{-- Levels --}}
										@php
										$classicMin = old('classic_level_min', $prefill['classic_level_min'] ?? null);
										$classicMax = old('classic_level_max', $prefill['classic_level_max'] ?? null);
										$beachMin   = old('beach_level_min',   $prefill['beach_level_min'] ?? null);
										$beachMax   = old('beach_level_max',   $prefill['beach_level_max'] ?? null);
										@endphp
										
										<div class="col-md-6">
											<div class="card">
												
												<div id="levels_classic" data-show-if="direction=classic">
													<label>Уровень допуска (Классический волейбол)</label>
													<hr class="mb-1">
													<div class="row">
														<div class="col-6">
															<label>От </label>
															<select name="classic_level_min" class="w-full rounded-lg border-gray-200">
																<option value="">—</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$classicMin === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
														</div>
														
														<div class="col-6">
															<label>До </label>
															<select name="classic_level_max" class="w-full rounded-lg border-gray-200">
																<option value="">—</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$classicMax === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
														</div>
													</div>
												</div>
												
												
												<div id="levels_beach" data-show-if="direction=beach">
													<label>Уровень допуска (Пляжный волейбол)</label>
													<hr class="mb-1">
													<div class="row">
														<div class="col-6">
															<label>От </label>
															<select name="beach_level_min" class="w-full rounded-lg border-gray-200">
																<option value="">-</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$beachMin === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
															@error('beach_level_min')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
														</div>
														
														<div class="col-6">
															<label>До </label>
															<select name="beach_level_max" class="w-full rounded-lg border-gray-200">
																<option value="">-</option>
																@for ($i = 1; $i <= 7; $i++)
																<option value="{{ $i }}" @selected((string)$beachMax === (string)$i)>{{ $i }} - {{ level_name($i) }}</option>
																@endfor
															</select>
															@error('beach_level_max')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
														</div>
													</div>
												</div>
												@error('direction')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
												@enderror
												
												<ul class="list f-16 mt-1">
													<li>Если выбраны оба — диапазона “от и до”. Если заполнено одно — ограничение будет по нему.</li>
												</ul>											
												
											</div>
										</div>								
										
										{{-- allow_registration --}}
										<div class="col-md-6">
											<div class="card">
																								<label>Тип мероприятия
													<button type="button" class="btn btn-small btn-secondary" style="font-size:.8rem;padding:.1rem .5rem;margin-left:.5rem;" id="hint-location-btn">❓</button>
												</label>
												@php
												$allowRegVal = old('allow_registration', $prefill['allow_registration'] ?? 1);
												@endphp

												<label class="radio-item">
													<input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal==='1')>
													<div class="custom-radio"></div>
													<span>Да — с регистрацией игроков (доступно повторение)</span>
												</label>
												<label class="radio-item">
													<input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal==='0')>
													<div class="custom-radio"></div>
													<span>Нет — рекламное мероприятие (без регистрации)</span>
												</label>
												
												<div id="no_registration_stub" class="mt-2" style="display:none;">
@php
    $adminPaySettings = \App\Models\PlatformPaymentSetting::first();
    $adPrice = $adminPaySettings?->ad_event_price_rub ?? 0;
@endphp
@if($adPrice > 0)
<div class="alert alert-info mt-1">
    💰 Стоимость размещения рекламного мероприятия: <strong>{{ $adPrice }} ₽</strong><br>
    <span class="f-13" style="opacity:.7">Оплата потребуется после создания мероприятия.</span>
</div>
@else
<div class="alert alert-info mt-1">
    📣 Рекламное мероприятие — свяжитесь с администратором для уточнения стоимости размещения.
</div>
@endif
</div>
											</div>
										</div>										

									</div>									
								</div>								
							</div>
						</div>
						<div class="ramka text-center">
							<button type="button" class="btn" data-next>
								Дальше
							</button>
						</div>

{{-- Модальная подсказка про локации --}}
<div id="hint-location-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9999;align-items:center;justify-content:center;">
    <div class="ramka" style="max-width:480px;margin:2rem auto;position:relative;">
        <button type="button" id="hint-location-close" style="position:absolute;top:.75rem;right:.75rem;background:none;border:none;font-size:1.5rem;cursor:pointer;line-height:1;">×</button>
        <h2 class="-mt-05">📍 Важно про локации</h2>
        <p>Убедитесь, что на сайте в разделе <a href="{{ route('locations.index') }}" target="_blank" class="blink">Локации</a> есть необходимый адрес — без него создать мероприятие не получится.</p>
        <p>Если локации нет — свяжитесь с администратором, он всё подготовит на платформе!</p>
        <div class="text-center mt-2">
            <button type="button" id="hint-location-close2" class="btn">Понятно</button>
        </div>
    </div>
</div>
<script>
(function(){
    var btn = document.getElementById('hint-location-btn');
    var modal = document.getElementById('hint-location-modal');
    var close1 = document.getElementById('hint-location-close');
    var close2 = document.getElementById('hint-location-close2');
    if (!btn || !modal) return;
    function show(){ modal.style.display = 'flex'; }
    function hide(){ modal.style.display = 'none'; }
    btn.addEventListener('click', show);
    close1.addEventListener('click', hide);
    close2.addEventListener('click', hide);
    modal.addEventListener('click', function(e){ if(e.target === modal) hide(); });
})();
</script>							
