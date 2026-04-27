						<div class="ramka" style="z-index:5">
							<h2 class="-mt-05">Выбор локации,времени и ограничений записи</h2>		
							<div class="row">
								
								<div class="col-lg-4">
									<div class="card">
										<label>Начало (локальное)</label>
										@php
										$minDate = now()->format('Y-m-d\TH:i');
										$maxDate = now()->addYear()->format('Y-m-d\TH:i');
										
										// Устанавливаем завтра в 19:00
										$defaultDate = now()->addDay()->setTime(19, 0)->format('Y-m-d\TH:i');
										@endphp
										
										<input type="datetime-local"
										name="starts_at_local" id="starts_at_local"
										value="{{ old('starts_at_local', $defaultDate) }}"
										min="{{ $minDate }}"
										max="{{ $maxDate }}">
										<div class="pb-05"></div>
										@error('starts_at_local')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
										@enderror
									</div>
								</div>							
								
								{{-- ✅ CITY (autocomplete -> hidden city_id) --}}
								<div class="col-lg-8">
									<div class="card">
										<label>Город</label>
										
										<div
										style="max-width: 40rem"
										class="city-autocomplete"
										id="event-city-autocomplete"
										data-search-url="{{ route('cities.search') }}"
										data-city-search-url="{{ route('cities.search') }}"
										data-locations-url="{{ route('ajax.locations.byCity') }}"
										data-city-meta-url="{{ route('ajax.cities.meta') }}"
										>
											
											{{-- Поле для отображения --}}
											<input
											type="text"
											name="city_label"
											id="event_city_q"
											class="w-full rounded-lg border-gray-200 @error('city_id') ring-2 ring-red-500 border-red-500 @enderror"
											value="{{ auth()->user()->city 
											? auth()->user()->city->name.' ('.auth()->user()->city->country_code.', '.auth()->user()->city->region.')' 
											: '' }}"
											placeholder="Начните вводить город…"
											autocomplete="off"
											>
											
											<div
											id="event_city_dropdown"
											class="city-dropdown"
											style="max-height: 28rem; overflow-y: auto; z-index: 60;"
											>
												<div id="event_city_results"></div>
											</div>
										</div>
										
										
										@error('city_id')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
										
										<div class="d-flex between mt-2">
											<label>Локация</label>
											@if($isAdmin)
											<a href="{{ route('admin.locations.create') }}"
											class="text-sm font-semibold text-blue-600 hover:text-blue-700">
												+ Создать локацию
											</a>
											@endif
										</div>
										
										<select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200">
											<option value="">— выбрать локацию —</option>
										</select>
										@error('location_id')
										<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
										{{-- preview --}}
										
										
										<div id="location_preview" class="mt-2 hidden">
											<div class="row fvc">
												
												<div class="col-3 location_preview">
													<img id="location_preview_img" src="" alt="" class="border hidden">
													<div id="location_preview_noimg" class="icon-nophoto"></div>
												</div>
												
												<div class="col-9">
													<p class="cd b-600" id="location_preview_name"></p>
													<p class="mt-1" id="location_preview_meta"></p>
												</div>
												<div class="col-12">
													<div class="border" id="location_preview_map_wrap" style="display:none;">
														<iframe
														id="location_preview_map"
														src=""
														class="w-100"
														style="height: 220px;"
														loading="lazy"
														referrerpolicy="no-referrer-when-downgrade"
														></iframe>
													</div>
												</div>												
												
											</div>
										</div>
										
										@if(!$isAdmin)
										<ul class="list f-16 mt-1">
											<li>Локации создаёт администратор. Если нужной локации нет — напишите админу.</li>
										</ul>											
										@endif
									</div>									
								</div>
								
								
								<div class="col-lg-4">
									<div class="card">
										<label>Длительность мероприятия</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-4">
												<label>Дни:</label>
												<select name="duration_days" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_days', 0) == 0 ? 'selected' : '' }}>0 дн.</option><option value="1" {{ old('duration_days', 0) == 1 ? 'selected' : '' }}>1 дн.</option><option value="2" {{ old('duration_days', 0) == 2 ? 'selected' : '' }}>2 дн.</option><option value="3" {{ old('duration_days', 0) == 3 ? 'selected' : '' }}>3 дн.</option><option value="4" {{ old('duration_days', 0) == 4 ? 'selected' : '' }}>4 дн.</option><option value="5" {{ old('duration_days', 0) == 5 ? 'selected' : '' }}>5 дн.</option><option value="6" {{ old('duration_days', 0) == 6 ? 'selected' : '' }}>6 дн.</option><option value="7" {{ old('duration_days', 0) == 7 ? 'selected' : '' }}>7 дн.</option><option value="8" {{ old('duration_days', 0) == 8 ? 'selected' : '' }}>8 дн.</option><option value="9" {{ old('duration_days', 0) == 9 ? 'selected' : '' }}>9 дн.</option><option value="10" {{ old('duration_days', 0) == 10 ? 'selected' : '' }}>10 дн.</option><option value="11" {{ old('duration_days', 0) == 11 ? 'selected' : '' }}>11 дн.</option><option value="12" {{ old('duration_days', 0) == 12 ? 'selected' : '' }}>12 дн.</option><option value="13" {{ old('duration_days', 0) == 13 ? 'selected' : '' }}>13 дн.</option><option value="14" {{ old('duration_days', 0) == 14 ? 'selected' : '' }}>14 дн.</option><option value="15" {{ old('duration_days', 0) == 15 ? 'selected' : '' }}>15 дн.</option><option value="16" {{ old('duration_days', 0) == 16 ? 'selected' : '' }}>16 дн.</option><option value="17" {{ old('duration_days', 0) == 17 ? 'selected' : '' }}>17 дн.</option><option value="18" {{ old('duration_days', 0) == 18 ? 'selected' : '' }}>18 дн.</option><option value="19" {{ old('duration_days', 0) == 19 ? 'selected' : '' }}>19 дн.</option><option value="20" {{ old('duration_days', 0) == 20 ? 'selected' : '' }}>20 дн.</option><option value="21" {{ old('duration_days', 0) == 21 ? 'selected' : '' }}>21 дн.</option><option value="22" {{ old('duration_days', 0) == 22 ? 'selected' : '' }}>22 дн.</option><option value="23" {{ old('duration_days', 0) == 23 ? 'selected' : '' }}>23 дн.</option><option value="24" {{ old('duration_days', 0) == 24 ? 'selected' : '' }}>24 дн.</option><option value="25" {{ old('duration_days', 0) == 25 ? 'selected' : '' }}>25 дн.</option><option value="26" {{ old('duration_days', 0) == 26 ? 'selected' : '' }}>26 дн.</option><option value="27" {{ old('duration_days', 0) == 27 ? 'selected' : '' }}>27 дн.</option><option value="28" {{ old('duration_days', 0) == 28 ? 'selected' : '' }}>28 дн.</option><option value="29" {{ old('duration_days', 0) == 29 ? 'selected' : '' }}>29 дн.</option><option value="30" {{ old('duration_days', 0) == 30 ? 'selected' : '' }}>30 дн.</option>
												</select>
											</div>

											<div class="col-4">
												<label>Часы:</label>
												<select name="duration_hours" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_hours', 0) == 0 ? 'selected' : '' }}>0 ч.</option><option value="1" {{ old('duration_hours', 0) == 1 ? 'selected' : '' }}>1 ч.</option><option value="2" {{ old('duration_hours', 0) == 2 ? 'selected' : '' }}>2 ч.</option><option value="3" {{ old('duration_hours', 0) == 3 ? 'selected' : '' }}>3 ч.</option><option value="4" {{ old('duration_hours', 0) == 4 ? 'selected' : '' }}>4 ч.</option><option value="5" {{ old('duration_hours', 0) == 5 ? 'selected' : '' }}>5 ч.</option><option value="6" {{ old('duration_hours', 0) == 6 ? 'selected' : '' }}>6 ч.</option><option value="7" {{ old('duration_hours', 0) == 7 ? 'selected' : '' }}>7 ч.</option><option value="8" {{ old('duration_hours', 0) == 8 ? 'selected' : '' }}>8 ч.</option><option value="9" {{ old('duration_hours', 0) == 9 ? 'selected' : '' }}>9 ч.</option><option value="10" {{ old('duration_hours', 0) == 10 ? 'selected' : '' }}>10 ч.</option><option value="11" {{ old('duration_hours', 0) == 11 ? 'selected' : '' }}>11 ч.</option><option value="12" {{ old('duration_hours', 0) == 12 ? 'selected' : '' }}>12 ч.</option><option value="13" {{ old('duration_hours', 0) == 13 ? 'selected' : '' }}>13 ч.</option><option value="14" {{ old('duration_hours', 0) == 14 ? 'selected' : '' }}>14 ч.</option><option value="15" {{ old('duration_hours', 0) == 15 ? 'selected' : '' }}>15 ч.</option><option value="16" {{ old('duration_hours', 0) == 16 ? 'selected' : '' }}>16 ч.</option><option value="17" {{ old('duration_hours', 0) == 17 ? 'selected' : '' }}>17 ч.</option><option value="18" {{ old('duration_hours', 0) == 18 ? 'selected' : '' }}>18 ч.</option><option value="19" {{ old('duration_hours', 0) == 19 ? 'selected' : '' }}>19 ч.</option><option value="20" {{ old('duration_hours', 0) == 20 ? 'selected' : '' }}>20 ч.</option><option value="21" {{ old('duration_hours', 0) == 21 ? 'selected' : '' }}>21 ч.</option><option value="22" {{ old('duration_hours', 0) == 22 ? 'selected' : '' }}>22 ч.</option><option value="23" {{ old('duration_hours', 0) == 23 ? 'selected' : '' }}>23 ч.</option>
												</select>
											</div>

											<div class="col-4">
												<label>Минуты:</label>
												<select name="duration_minutes" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_minutes', 0) == 0 ? 'selected' : '' }}>0 мин.</option><option value="5" {{ old('duration_minutes', 0) == 5 ? 'selected' : '' }}>5 мин.</option><option value="10" {{ old('duration_minutes', 0) == 10 ? 'selected' : '' }}>10 мин.</option><option value="15" {{ old('duration_minutes', 0) == 15 ? 'selected' : '' }}>15 мин.</option><option value="20" {{ old('duration_minutes', 0) == 20 ? 'selected' : '' }}>20 мин.</option><option value="25" {{ old('duration_minutes', 0) == 25 ? 'selected' : '' }}>25 мин.</option><option value="30" {{ old('duration_minutes', 0) == 30 ? 'selected' : '' }}>30 мин.</option><option value="35" {{ old('duration_minutes', 0) == 35 ? 'selected' : '' }}>35 мин.</option><option value="40" {{ old('duration_minutes', 0) == 40 ? 'selected' : '' }}>40 мин.</option><option value="45" {{ old('duration_minutes', 0) == 45 ? 'selected' : '' }}>45 мин.</option><option value="50" {{ old('duration_minutes', 0) == 50 ? 'selected' : '' }}>50 мин.</option><option value="55" {{ old('duration_minutes', 0) == 55 ? 'selected' : '' }}>55 мин.</option>
												</select>
											</div>
											<input type="hidden" name="duration_sec" id="duration_sec" value="0">
											@error('duration_sec')
											<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
											@enderror
										</div>
										
										<ul class="list f-16 mt-1">
											<li>Дни — для кемпов и турниров</li>
											<li>Часы — для тренировок и игр  </li>
											<li>Минуты — точная настройка длительности</li>
										</ul>										
									</div>
								</div>
								<script>
									document.addEventListener('DOMContentLoaded', () => {
										const form = document.querySelector('form[action="{{ route('events.store') }}"]');
										if (!form) return;
										
										function updateDurationSec() {
											const days    = parseInt(form.querySelector('[name="duration_days"]')?.value || 0, 10);
											const hours   = parseInt(form.querySelector('[name="duration_hours"]')?.value || 0, 10);
											const minutes = parseInt(form.querySelector('[name="duration_minutes"]')?.value || 0, 10);
											
											let durationSec = 0;
											durationSec += days * 86400;
											durationSec += hours * 3600;
											durationSec += minutes * 60;
											
											// Без защиты — оставляем как есть, даже если 0
											document.getElementById('duration_sec').value = durationSec;
										}
										
										// Обновляем при изменении полей
										form.addEventListener('change', (e) => {
											if (e.target.name === 'duration_days' || e.target.name === 'duration_hours' || e.target.name === 'duration_minutes') {
												updateDurationSec();
											}
										});
										
										// При загрузке тоже обновляем
										updateDurationSec();
									});
								</script>
								
								
								
								
								{{-- ✅ Registration timings (Step 2) --}}
								<div class="col-lg-8" id="reg_timing_box" data-show-if="allow_registration=1">
									<div class="card">
										<label>Окно регистрации</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-sm-4">
												<label>Начало регистрации</label>
												<input type="hidden" name="reg_starts_days_before" id="reg_starts_days_before" value="{{ $oldRegStartsDaysBefore }}">
										<input type="hidden" name="reg_starts_hours_before" id="reg_starts_hours_before" value="{{ old('reg_starts_hours_before', 0) }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="reg_starts_days_sel" name="reg_starts_d" style="width:auto">
													@for ($d = 0; $d <= 90; $d++)
														<option value="{{ $d }}" @selected($oldRegStartsDaysBefore == $d)>{{ $d }} д</option>
													@endfor
												</select>
												<select id="reg_starts_hours_sel" name="reg_starts_h" style="width:auto">
													@for ($h = 0; $h <= 23; $h++)
														<option value="{{ $h }}" @selected(($oldRegStartsHoursBefore ?? 0) == $h)>{{ $h }} ч</option>
													@endfor
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>До начала мероприятия.</li>
													<li>По умолчанию: 3 дня.</li>
												</ul>
												<div id="reg_starts_hint" class="f-13 mt-1 b-600" style="color:var(--green)"></div>
												<script>
												document.addEventListener('DOMContentLoaded', function() {
													var daysSel = document.getElementById('reg_starts_days_sel');
													var hoursSel = document.getElementById('reg_starts_hours_sel');
													var hidden = document.getElementById('reg_starts_days_before');
													var hint = document.getElementById('reg_starts_hint');
													var startsInput = document.getElementById('starts_at_local');
													function syncRegStarts() {
														var d = parseInt(daysSel.value || 0);
														var h = parseInt(hoursSel.value || 0);
														hidden.value = d;
var hiddenH = document.getElementById('reg_starts_hours_before');
if (hiddenH) hiddenH.value = h;
														if (startsInput && startsInput.value) {
															try {
																var start = new Date(startsInput.value);
																start.setDate(start.getDate() - d);
																start.setHours(start.getHours() - h);
																var days = ['вс','пн','вт','ср','чт','пт','сб'];
																var dd = String(start.getDate()).padStart(2,'0');
																var mm = String(start.getMonth()+1).padStart(2,'0');
																var hh = String(start.getHours()).padStart(2,'0');
																var mi = String(start.getMinutes()).padStart(2,'0');
																hint.textContent = 'Регистрация начнется: ' + dd + '.' + mm + ' ' + days[start.getDay()] + '. в ' + hh + ':' + mi;
															} catch(e) { hint.textContent = ''; }
														}
													}
													daysSel.addEventListener('change', syncRegStarts);
													hoursSel.addEventListener('change', syncRegStarts);
													if (startsInput) startsInput.addEventListener('change', syncRegStarts);
													syncRegStarts();
												});
												</script>
											</div>
											<div class="col-sm-4">
												<label>Окончание регистрации</label>
												<input type="hidden" name="reg_ends_minutes_before" id="reg_ends_minutes_before" value="{{ $oldRegEndsMinutesBefore }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="reg_ends_hours" name="reg_ends_h" style="width:auto">
												@for ($h = 0; $h <= 24; $h++)
													<option value="{{ $h }}" @selected($regEndsHours == $h)>{{ $h }} ч</option>
												@endfor
												</select>
												<select id="reg_ends_mins" name="reg_ends_m" style="width:auto">
												@foreach ([0,10,15,20,30,40,50] as $m)
													<option value="{{ $m }}" @selected($regEndsMinutes == $m)>{{ $m }} мин</option>
												@endforeach
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>До начала мероприятия.</li>
													<li>По умолчанию: 15 минут.</li>
												</ul>
											</div>
											
											<div class="col-sm-4">
												<label>Запрет отмены записи</label>
												<input type="hidden" name="cancel_lock_minutes_before" id="cancel_lock_minutes_before" value="{{ $oldCancelLockMinutesBefore }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="cancel_lock_hours" name="cancel_lock_h" style="width:auto">
												@for ($h = 0; $h <= 24; $h++)
													<option value="{{ $h }}" @selected($cancelLockHours == $h)>{{ $h }} ч</option>
												@endfor
												</select>
												<select id="cancel_lock_mins" name="cancel_lock_m" style="width:auto">
												@foreach ([0,10,15,20,30,40,50] as $m)
													<option value="{{ $m }}" @selected($cancelLockMinutes == $m)>{{ $m }} мин</option>
												@endforeach
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>До начала мероприятия.</li>
													<li>По умолчанию: 1 час.</li>
												</ul>
											</div>
										</div>
										
										<ul class="list f-16 mt-1">
											{{--
											<li>Эти настройки применяются только если в шаге 1 выбрано “Регистрация игроков через сервис: Да”.</li>
											--}}	
											<li>Время считается от <span class="f-600">начала мероприятия</span>.</li>
											<li>Пример: “Запрет отмены 60 минут” → за час до начала кнопка отмены станет недоступной.</li>
										</ul>									

										{{-- Отдельное начало регистрации для ограничиваемого пола --}}
										<div id="gender_limited_reg_box" class="mt-2" style="display:none">
											<hr class="mb-1">
											<div class="row">
												<div class="col-sm-6">
													<label>
														<span id="gender_limited_reg_label">Ограничиваемый пол</span>: начало регистрации
													</label>
													<select name="game_gender_limited_reg_starts_days_before" id="game_gender_limited_reg_starts_days_before">
														<option value="">— не задано —</option>
														@for ($d = 0; $d <= 90; $d++)
															<option value="{{ $d }}" 
																@selected(old('game_gender_limited_reg_starts_days_before', $prefill['game_gender_limited_reg_starts_days_before'] ?? '') == $d)>
																{{ $d }}
															</option>
														@endfor
													</select>
													<ul class="list f-16 mt-1">
														<li class="b-600">Дней до</li>
														<li>Если пусто — действует общее «Начало регистрации».</li>
														<li>Значение должно быть меньше или равно общему.</li>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
								
							</div>
						</div>
						<div class="ramka" data-show-if="allow_registration=1">
							<h2 class="-mt-05">Повторение мероприятия</h2>		
							
							{{-- ✅ Повторение перенесено сюда (Step 2) --}}
							<div id="recurrence_box">
								<div class="mb-1">
									{{-- toggle --}}
									<label class="checkbox-item">
										<input type="hidden" name="is_recurring" value="0">
										<input type="checkbox" name="is_recurring" value="1" id="is_recurring">
										<div class="custom-checkbox"></div>
										<span>Повторяющееся мероприятие</span>
									</label>	
									
									<ul class="list f-16 mt-1" id="recurrence_disabled_hint">
										<li>Повторы доступны только при включённой регистрации игроков.</li>
									</ul>										
									
								</div>
								{{-- fields --}}
								<div class="row mt-2" id="recurrence_fields" style="display:none;">
									
									{{-- type --}}
									<div class="col-md-4">
										<div class="card">
											<label>Тип повторения</label>
											<select name="recurrence_type" id="recurrence_type">
												<option value="">— выбрать —</option>
												<option value="daily">Ежедневно</option>
												<option value="weekly">Еженедельно</option>
												<option value="monthly">Ежемесячно</option>
											</select>
											
											{{-- WEEKDAYS --}}
											<div class="mt-2" id="weekdays_wrap" style="display:none;">
												
												<label>
													Дни недели
												</label>
												
												<div class="row row2">
													@foreach([
													1 => 'Понедельник',
													2 => 'Вторник', 
													3 => 'Среда',
													4 => 'Четверг',
													5 => 'Пятница',
													6 => 'Суббота',
													7 => 'Воскресенье'
													] as $num => $label)
													<div class="col-6">
														<label class="checkbox-item">
															<input type="checkbox"
															name="recurrence_weekdays[]"
															value="{{ $num }}">
															<div class="custom-checkbox"></div>
															<span>{{ $label }}</span>
														</label>
													</div>
													@endforeach
												</div>
												
											</div>
										</div>		
									</div>
									
									{{-- END TYPE --}}
									<div class="col-md-4">
										<div class="card">
											<label>
												Окончание повторов
											</label>
											
											<div class="flex flex-col gap-2">
												<label class="radio-item">
													<input checked type="radio" name="recurrence_end_type" value="none">
													<div class="custom-radio"></div>
													<span>Без окончания</span>
												</label>
												
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="until">
													<div class="custom-radio"></div>
													<span>До даты</span>
												</label>
												<div class="mb-1">
													<input type="date" name="recurrence_end_until">
												</div>
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="count">
													<div class="custom-radio"></div>
													<span>По количеству</span>
												</label>
												
												<input type="number"
												min="1"
												name="recurrence_end_count"
												placeholder="например 10">
												<div class="pb-05"></div>												
											</div>
										</div>									
									</div>
									
									
									{{-- interval --}}
									<div class="col-md-4">
										<div class="card">
											<label>Интервал</label>
											<input type="number"
											min="1" max="365"
											id="recurrence_interval"
											name="recurrence_interval"
											value="1">
											
											<ul class="list f-16 mt-1">
												<li>1 = каждый раз</li>
												<li>2 = через раз</li>
											</ul>										
										</div>
									</div>
									
									
									
									{{-- legacy --}}
									<input type="hidden" name="recurrence_rule"
									value="{{ old('recurrence_rule', $prefill['recurrence_rule'] ?? '') }}">
								</div>
								
								<script>
									document.addEventListener('DOMContentLoaded', () => {
										const isRecurring   = document.getElementById('is_recurring');
										const fields        = document.getElementById('recurrence_fields');
										const typeSelect    = document.getElementById('recurrence_type');
										const weekdaysWrap  = document.getElementById('weekdays_wrap');
										const allowRegRadios = document.querySelectorAll('input[name="allow_registration"]');
										const disabledHint  = document.getElementById('recurrence_disabled_hint');
										
										function allowRegistrationEnabled() {
											return [...allowRegRadios].some(r => r.checked && r.value === '1');
										}
										
										function syncRecurrenceUI() {
											const allowed = allowRegistrationEnabled();
											
											if (!allowed) {
												isRecurring.checked = false;
												isRecurring.disabled = true;
												fields.style.display = 'none';
												disabledHint.classList.remove('hidden');
												return;
											}
											
											isRecurring.disabled = false;
											disabledHint.classList.add('hidden');
											
											fields.style.display = isRecurring.checked ? '' : 'none';
											
											const type = typeSelect.value;
											weekdaysWrap.style.display = (type === 'weekly') ? '' : 'none';
										}
										
										isRecurring.addEventListener('change', syncRecurrenceUI);
										typeSelect.addEventListener('change', syncRecurrenceUI);
										allowRegRadios.forEach(r => r.addEventListener('change', syncRecurrenceUI));

										syncRecurrenceUI();
									});
								</script>
<script>
(function(){
    var allowRegRadios = document.querySelectorAll('input[name="allow_registration"]');
    var adModal = document.getElementById('hint-location-modal');
    var adModalShown = false;

    function applyAllowRegShowIf() {
        var isReg = Array.from(allowRegRadios).some(function(r){ return r.checked && r.value === '1'; });
        // Показать/скрыть data-show-if блоки
        document.querySelectorAll('[data-show-if]').forEach(function(el) {
            var cond = el.getAttribute('data-show-if');
            if (cond && cond.indexOf('allow_registration=') !== -1) {
                var match = cond.match(/allow_registration=([01])/);
                if (match) {
                    el.style.display = (isReg === (match[1] === '1')) ? '' : 'none';
                }
            }
        });
        
        // Обработка data-hide-if для registration_type
        document.querySelectorAll('[data-hide-if]').forEach(function(el) {
            var hideCondition = el.getAttribute('data-hide-if');
            if (hideCondition && hideCondition.indexOf('registration_type=') !== -1) {
                var match = hideCondition.match(/registration_type=([a-zA-Z_]+)/);
                if (match) {
                    var regTypeRadio = document.querySelector('input[name="registration_type"]:checked');
                    var currentType = regTypeRadio ? regTypeRadio.value : 'individual';
                    el.style.display = (currentType === match[1]) ? 'none' : '';
                }
            }
        });
        // Показать/скрыть блок стоимости
        var stub = document.getElementById('no_registration_stub');
        if (stub) stub.style.display = isReg ? 'none' : '';
        // Показать модальное окно при выборе "Нет"
        if (!isReg && !adModalShown && adModal) {
            adModal.style.display = 'flex';
            adModalShown = true;
        }
        if (isReg) adModalShown = false;
    }
    allowRegRadios.forEach(function(r){ r.addEventListener('change', applyAllowRegShowIf); });
    applyAllowRegShowIf();
})();
</script>	
								
								
							</div>
							
							
						</div>	

						{{-- ══════════════════════════════════════════════════ --}}
						{{-- СЕРИЯ ТУРНИРОВ (Season / League) --}}
						{{-- ══════════════════════════════════════════════════ --}}
						<div class="ramka" id="season_league_box" style="display:none;">
							<h2 class="-mt-05">Серия турниров</h2>

							<label class="checkbox-item">
								<input type="hidden" name="create_season" value="0">
								<input type="checkbox" name="create_season" value="1" id="create_season" checked>
								<div class="custom-checkbox"></div>
								<span>Создать как серию турниров (Сезон)</span>
							</label>

							<ul class="list f-16 mt-1 mb-2" id="season_hint">
								<li>Команды переносятся между турами, накопительный рейтинг и статистика.</li>
								<li>Каждая дата повторения = отдельный тур серии.</li>
							</ul>

							<div id="season_fields" class="mt-2">

								{{-- Выбор: новая лига или существующая --}}
								<div class="row">
									<div class="col-md-6">
										<div class="card">
											<label>Лига</label>
											<select name="season_league_mode" id="season_league_mode">
												<option value="new">Создать новую лигу</option>
												@if(isset($organizerSeasons) && $organizerSeasons->count())
													<option value="existing">Выбрать существующую</option>
												@endif
											</select>
										</div>
									</div>
								</div>

								{{-- Новая лига --}}
								<div class="row mt-2" id="new_league_fields">
									<div class="col-md-6">
										<div class="card">
											<label for="new_league_name">Название лиги</label>
											<input type="text"
												id="new_league_name"
												name="new_league_name"
												value="{{ old('new_league_name', 'Основная лига') }}"
												placeholder="Например: Лига Hard"
												class="w-full rounded-lg border-gray-200">
										</div>
									</div>
								</div>

								{{-- Существующая лига --}}
								<div class="row mt-2" id="existing_league_fields" style="display:none;">
									<div class="col-md-6">
										<div class="card">
											<label>Сезон</label>
											<select name="existing_season_id" id="existing_season_id">
												<option value="">— выбрать сезон —</option>
												@if(isset($organizerSeasons))
													@foreach($organizerSeasons as $s)
														<option value="{{ $s->id }}">{{ $s->name }}</option>
													@endforeach
												@endif
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<div class="card">
											<label>Лига</label>
											<select name="existing_league_id" id="existing_league_id">
												<option value="">— сначала выберите сезон —</option>
											</select>
										</div>
									</div>
								</div>
							</div>
						</div>

						<script>
						document.addEventListener('DOMContentLoaded', () => {
							const formatSelect   = document.querySelector('select[name="format"], input[name="format"]');
							const isRecurring    = document.getElementById('is_recurring');
							const seasonBox      = document.getElementById('season_league_box');
							const createSeason   = document.getElementById('create_season');
							const seasonFields   = document.getElementById('season_fields');
							const leagueMode     = document.getElementById('season_league_mode');
							const newFields      = document.getElementById('new_league_fields');
							const existingFields = document.getElementById('existing_league_fields');
							const seasonSelect   = document.getElementById('existing_season_id');
							const leagueSelect   = document.getElementById('existing_league_id');

							const seasonsData = @php
							$seasonsJs = isset($organizerSeasons) ? $organizerSeasons->map(function($s) {
								return [
									'id' => $s->id,
									'name' => $s->name,
									'leagues' => $s->leagues->map(function($l) {
										return ['id' => $l->id, 'name' => $l->name];
									})->values()->toArray(),
								];
							})->values()->toArray() : [];
							echo json_encode($seasonsJs);
						@endphp;

							function getFormat() {
								if (!formatSelect) return '';
								if (formatSelect.tagName === 'SELECT') return formatSelect.value;
								const checked = document.querySelector('input[name="format"]:checked');
								return checked ? checked.value : '';
							}

							function syncSeasonBox() {
								const isTournament = getFormat() === 'tournament';
								const isRec = isRecurring && isRecurring.checked;
								seasonBox.style.display = (isTournament && isRec) ? '' : 'none';
							}

							function syncSeasonFields() {
								seasonFields.style.display = createSeason.checked ? '' : 'none';
							}

							function syncLeagueMode() {
								const mode = leagueMode.value;
								newFields.style.display      = (mode === 'new') ? '' : 'none';
								existingFields.style.display = (mode === 'existing') ? '' : 'none';
							}

							function populateLeagues() {
								const sid = parseInt(seasonSelect.value);
								leagueSelect.innerHTML = '<option value="">— выбрать лигу —</option>';
								const season = seasonsData.find(s => s.id === sid);
								if (season) {
									season.leagues.forEach(l => {
										const opt = document.createElement('option');
										opt.value = l.id;
										opt.textContent = l.name;
										leagueSelect.appendChild(opt);
									});
								}
							}

							if (formatSelect) {
								formatSelect.addEventListener('change', syncSeasonBox);
								document.querySelectorAll('input[name="format"]').forEach(r => r.addEventListener('change', syncSeasonBox));
							}
							if (isRecurring)   isRecurring.addEventListener('change', syncSeasonBox);
							if (createSeason)  createSeason.addEventListener('change', syncSeasonFields);
							if (leagueMode)    leagueMode.addEventListener('change', syncLeagueMode);
							if (seasonSelect)  seasonSelect.addEventListener('change', populateLeagues);

							syncSeasonBox();
							syncSeasonFields();
							syncLeagueMode();
						});
						</script>

						<div class="ramka text-center">
							<button type="button" class="btn btn-secondary" data-back>
								Назад
							</button>
							<button type="button" class="btn" data-next>
								Дальше
							</button>
						</div>							
