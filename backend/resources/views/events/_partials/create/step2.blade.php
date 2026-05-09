						<div class="ramka" style="z-index:5">
							<h2 class="-mt-05">{{ __('events.step2_title') }}</h2>		
							<div class="row">
								
								<div class="col-lg-4">
									<div class="card">
										<label>{{ __('events.starts_local') }}</label>
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
										<label>{{ __('events.city_label') }}</label>
										
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
											placeholder="{{ __('events.city_search_ph') }}"
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
											<label>{{ __('events.location_label') }}</label>
											@if($isAdmin)
											<a href="{{ route('admin.locations.create') }}"
											class="text-sm font-semibold text-blue-600 hover:text-blue-700">
												{{ __('events.location_create_btn') }}
											</a>
											@endif
										</div>
										
										<select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200">
											<option value="">{{ __('events.location_choose') }}</option>
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
											<li>{{ __('events.location_admin_only_hint') }}</li>
										</ul>											
										@endif
									</div>									
								</div>
								
								
								<div class="col-lg-4">
									<div class="card">
										<label>{{ __('events.duration_label') }}</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-4">
												<label>{{ __('events.duration_days') }}</label>
												<select name="duration_days" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_days', 0) == 0 ? 'selected' : '' }}>0 {{ __('events.dur_d_short') }}</option><option value="1" {{ old('duration_days', 0) == 1 ? 'selected' : '' }}>1 {{ __('events.dur_d_short') }}</option><option value="2" {{ old('duration_days', 0) == 2 ? 'selected' : '' }}>2 {{ __('events.dur_d_short') }}</option><option value="3" {{ old('duration_days', 0) == 3 ? 'selected' : '' }}>3 {{ __('events.dur_d_short') }}</option><option value="4" {{ old('duration_days', 0) == 4 ? 'selected' : '' }}>4 {{ __('events.dur_d_short') }}</option><option value="5" {{ old('duration_days', 0) == 5 ? 'selected' : '' }}>5 {{ __('events.dur_d_short') }}</option><option value="6" {{ old('duration_days', 0) == 6 ? 'selected' : '' }}>6 {{ __('events.dur_d_short') }}</option><option value="7" {{ old('duration_days', 0) == 7 ? 'selected' : '' }}>7 {{ __('events.dur_d_short') }}</option><option value="8" {{ old('duration_days', 0) == 8 ? 'selected' : '' }}>8 {{ __('events.dur_d_short') }}</option><option value="9" {{ old('duration_days', 0) == 9 ? 'selected' : '' }}>9 {{ __('events.dur_d_short') }}</option><option value="10" {{ old('duration_days', 0) == 10 ? 'selected' : '' }}>10 {{ __('events.dur_d_short') }}</option><option value="11" {{ old('duration_days', 0) == 11 ? 'selected' : '' }}>11 {{ __('events.dur_d_short') }}</option><option value="12" {{ old('duration_days', 0) == 12 ? 'selected' : '' }}>12 {{ __('events.dur_d_short') }}</option><option value="13" {{ old('duration_days', 0) == 13 ? 'selected' : '' }}>13 {{ __('events.dur_d_short') }}</option><option value="14" {{ old('duration_days', 0) == 14 ? 'selected' : '' }}>14 {{ __('events.dur_d_short') }}</option><option value="15" {{ old('duration_days', 0) == 15 ? 'selected' : '' }}>15 {{ __('events.dur_d_short') }}</option><option value="16" {{ old('duration_days', 0) == 16 ? 'selected' : '' }}>16 {{ __('events.dur_d_short') }}</option><option value="17" {{ old('duration_days', 0) == 17 ? 'selected' : '' }}>17 {{ __('events.dur_d_short') }}</option><option value="18" {{ old('duration_days', 0) == 18 ? 'selected' : '' }}>18 {{ __('events.dur_d_short') }}</option><option value="19" {{ old('duration_days', 0) == 19 ? 'selected' : '' }}>19 {{ __('events.dur_d_short') }}</option><option value="20" {{ old('duration_days', 0) == 20 ? 'selected' : '' }}>20 {{ __('events.dur_d_short') }}</option><option value="21" {{ old('duration_days', 0) == 21 ? 'selected' : '' }}>21 {{ __('events.dur_d_short') }}</option><option value="22" {{ old('duration_days', 0) == 22 ? 'selected' : '' }}>22 {{ __('events.dur_d_short') }}</option><option value="23" {{ old('duration_days', 0) == 23 ? 'selected' : '' }}>23 {{ __('events.dur_d_short') }}</option><option value="24" {{ old('duration_days', 0) == 24 ? 'selected' : '' }}>24 {{ __('events.dur_d_short') }}</option><option value="25" {{ old('duration_days', 0) == 25 ? 'selected' : '' }}>25 {{ __('events.dur_d_short') }}</option><option value="26" {{ old('duration_days', 0) == 26 ? 'selected' : '' }}>26 {{ __('events.dur_d_short') }}</option><option value="27" {{ old('duration_days', 0) == 27 ? 'selected' : '' }}>27 {{ __('events.dur_d_short') }}</option><option value="28" {{ old('duration_days', 0) == 28 ? 'selected' : '' }}>28 {{ __('events.dur_d_short') }}</option><option value="29" {{ old('duration_days', 0) == 29 ? 'selected' : '' }}>29 {{ __('events.dur_d_short') }}</option><option value="30" {{ old('duration_days', 0) == 30 ? 'selected' : '' }}>30 {{ __('events.dur_d_short') }}</option>
												</select>
											</div>

											<div class="col-4">
												<label>{{ __('events.duration_hours') }}</label>
												<select name="duration_hours" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_hours', 0) == 0 ? 'selected' : '' }}>0 {{ __('events.dur_h_short') }}</option><option value="1" {{ old('duration_hours', 0) == 1 ? 'selected' : '' }}>1 {{ __('events.dur_h_short') }}</option><option value="2" {{ old('duration_hours', 0) == 2 ? 'selected' : '' }}>2 {{ __('events.dur_h_short') }}</option><option value="3" {{ old('duration_hours', 0) == 3 ? 'selected' : '' }}>3 {{ __('events.dur_h_short') }}</option><option value="4" {{ old('duration_hours', 0) == 4 ? 'selected' : '' }}>4 {{ __('events.dur_h_short') }}</option><option value="5" {{ old('duration_hours', 0) == 5 ? 'selected' : '' }}>5 {{ __('events.dur_h_short') }}</option><option value="6" {{ old('duration_hours', 0) == 6 ? 'selected' : '' }}>6 {{ __('events.dur_h_short') }}</option><option value="7" {{ old('duration_hours', 0) == 7 ? 'selected' : '' }}>7 {{ __('events.dur_h_short') }}</option><option value="8" {{ old('duration_hours', 0) == 8 ? 'selected' : '' }}>8 {{ __('events.dur_h_short') }}</option><option value="9" {{ old('duration_hours', 0) == 9 ? 'selected' : '' }}>9 {{ __('events.dur_h_short') }}</option><option value="10" {{ old('duration_hours', 0) == 10 ? 'selected' : '' }}>10 {{ __('events.dur_h_short') }}</option><option value="11" {{ old('duration_hours', 0) == 11 ? 'selected' : '' }}>11 {{ __('events.dur_h_short') }}</option><option value="12" {{ old('duration_hours', 0) == 12 ? 'selected' : '' }}>12 {{ __('events.dur_h_short') }}</option><option value="13" {{ old('duration_hours', 0) == 13 ? 'selected' : '' }}>13 {{ __('events.dur_h_short') }}</option><option value="14" {{ old('duration_hours', 0) == 14 ? 'selected' : '' }}>14 {{ __('events.dur_h_short') }}</option><option value="15" {{ old('duration_hours', 0) == 15 ? 'selected' : '' }}>15 {{ __('events.dur_h_short') }}</option><option value="16" {{ old('duration_hours', 0) == 16 ? 'selected' : '' }}>16 {{ __('events.dur_h_short') }}</option><option value="17" {{ old('duration_hours', 0) == 17 ? 'selected' : '' }}>17 {{ __('events.dur_h_short') }}</option><option value="18" {{ old('duration_hours', 0) == 18 ? 'selected' : '' }}>18 {{ __('events.dur_h_short') }}</option><option value="19" {{ old('duration_hours', 0) == 19 ? 'selected' : '' }}>19 {{ __('events.dur_h_short') }}</option><option value="20" {{ old('duration_hours', 0) == 20 ? 'selected' : '' }}>20 {{ __('events.dur_h_short') }}</option><option value="21" {{ old('duration_hours', 0) == 21 ? 'selected' : '' }}>21 {{ __('events.dur_h_short') }}</option><option value="22" {{ old('duration_hours', 0) == 22 ? 'selected' : '' }}>22 {{ __('events.dur_h_short') }}</option><option value="23" {{ old('duration_hours', 0) == 23 ? 'selected' : '' }}>23 {{ __('events.dur_h_short') }}</option>
												</select>
											</div>

											<div class="col-4">
												<label>{{ __('events.duration_minutes') }}</label>
												<select name="duration_minutes" class="w-full rounded-lg border-gray-200">
												<option value="0" {{ old('duration_minutes', 0) == 0 ? 'selected' : '' }}>0 {{ __('events.dur_m_short') }}</option><option value="5" {{ old('duration_minutes', 0) == 5 ? 'selected' : '' }}>5 {{ __('events.dur_m_short') }}</option><option value="10" {{ old('duration_minutes', 0) == 10 ? 'selected' : '' }}>10 {{ __('events.dur_m_short') }}</option><option value="15" {{ old('duration_minutes', 0) == 15 ? 'selected' : '' }}>15 {{ __('events.dur_m_short') }}</option><option value="20" {{ old('duration_minutes', 0) == 20 ? 'selected' : '' }}>20 {{ __('events.dur_m_short') }}</option><option value="25" {{ old('duration_minutes', 0) == 25 ? 'selected' : '' }}>25 {{ __('events.dur_m_short') }}</option><option value="30" {{ old('duration_minutes', 0) == 30 ? 'selected' : '' }}>30 {{ __('events.dur_m_short') }}</option><option value="35" {{ old('duration_minutes', 0) == 35 ? 'selected' : '' }}>35 {{ __('events.dur_m_short') }}</option><option value="40" {{ old('duration_minutes', 0) == 40 ? 'selected' : '' }}>40 {{ __('events.dur_m_short') }}</option><option value="45" {{ old('duration_minutes', 0) == 45 ? 'selected' : '' }}>45 {{ __('events.dur_m_short') }}</option><option value="50" {{ old('duration_minutes', 0) == 50 ? 'selected' : '' }}>50 {{ __('events.dur_m_short') }}</option><option value="55" {{ old('duration_minutes', 0) == 55 ? 'selected' : '' }}>55 {{ __('events.dur_m_short') }}</option>
												</select>
											</div>
											<input type="hidden" name="duration_sec" id="duration_sec" value="0">
											@error('duration_sec')
											<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
											@enderror
										</div>
										
										<ul class="list f-16 mt-1">
											<li>{{ __('events.duration_hint_camps') }}</li>
											<li>{{ __('events.duration_hint_games') }}</li>
											<li>{{ __('events.duration_hint_min') }}</li>
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
										<label>{{ __('events.reg_window_title') }}</label>
										<hr class="mb-1">
										<div class="row">
											<div class="col-sm-4">
												<label>{{ __('events.reg_starts_label') }}</label>
												<input type="hidden" name="reg_starts_days_before" id="reg_starts_days_before" value="{{ $oldRegStartsDaysBefore }}">
										<input type="hidden" name="reg_starts_hours_before" id="reg_starts_hours_before" value="{{ old('reg_starts_hours_before', 0) }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="reg_starts_days_sel" name="reg_starts_d" style="width:auto">
													@for ($d = 0; $d <= 90; $d++)
														<option value="{{ $d }}" @selected($oldRegStartsDaysBefore == $d)>{{ $d }} {{ __('events.dur_d_short') }}</option>
													@endfor
												</select>
												<select id="reg_starts_hours_sel" name="reg_starts_h" style="width:auto">
													@for ($h = 0; $h <= 23; $h++)
														<option value="{{ $h }}" @selected(($oldRegStartsHoursBefore ?? 0) == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
													@endfor
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>{{ __('events.until_event_start') }}</li>
													<li>{{ __('events.reg_starts_default') }}</li>
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
																@php $jsDows = [__('events.dow_short.7'), __('events.dow_short.1'), __('events.dow_short.2'), __('events.dow_short.3'), __('events.dow_short.4'), __('events.dow_short.5'), __('events.dow_short.6')]; @endphp
																var days = {!! json_encode($jsDows) !!};
																var dd = String(start.getDate()).padStart(2,'0');
																var mm = String(start.getMonth()+1).padStart(2,'0');
																var hh = String(start.getHours()).padStart(2,'0');
																var mi = String(start.getMinutes()).padStart(2,'0');
																hint.textContent = @json(__('events.reg_starts_hint')) + dd + '.' + mm + ' ' + days[start.getDay()] + '. ' + hh + ':' + mi;
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
												<label>{{ __('events.reg_ends_label') }}</label>
												<input type="hidden" name="reg_ends_minutes_before" id="reg_ends_minutes_before" value="{{ $oldRegEndsMinutesBefore }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="reg_ends_hours" name="reg_ends_h" style="width:auto">
												@for ($h = 0; $h <= 24; $h++)
													<option value="{{ $h }}" @selected($regEndsHours == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
												@endfor
												</select>
												<select id="reg_ends_mins" name="reg_ends_m" style="width:auto">
												@foreach ([0,10,15,20,30,40,50] as $m)
													<option value="{{ $m }}" @selected($regEndsMinutes == $m)>{{ $m }} {{ __('events.dur_m_short') }}</option>
												@endforeach
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>{{ __('events.until_event_start') }}</li>
													<li>{{ __('events.reg_ends_default') }}</li>
												</ul>
											</div>
											
											<div class="col-sm-4">
												<label>{{ __('events.cancel_lock_label') }}</label>
												<input type="hidden" name="cancel_lock_minutes_before" id="cancel_lock_minutes_before" value="{{ $oldCancelLockMinutesBefore }}">
												<div class="d-flex" style="gap:.5rem;align-items:center">
												<select id="cancel_lock_hours" name="cancel_lock_h" style="width:auto">
												@for ($h = 0; $h <= 24; $h++)
													<option value="{{ $h }}" @selected($cancelLockHours == $h)>{{ $h }} {{ __('events.dur_h_short') }}</option>
												@endfor
												</select>
												<select id="cancel_lock_mins" name="cancel_lock_m" style="width:auto">
												@foreach ([0,10,15,20,30,40,50] as $m)
													<option value="{{ $m }}" @selected($cancelLockMinutes == $m)>{{ $m }} {{ __('events.dur_m_short') }}</option>
												@endforeach
												</select>
												</div>
												<ul class="list f-16 mt-1">
													<li>{{ __('events.until_event_start') }}</li>
													<li>{{ __('events.cancel_lock_default') }}</li>
												</ul>
											</div>
										</div>
										
										<ul class="list f-16 mt-1">
											{{--
											<li>{{ __('events.step2_extra_hint') }}</li>
											--}}	
											<li>{!! __('events.reg_window_note_1') !!}</li>
											<li>{{ __('events.reg_window_note_2') }}</li>
										</ul>									

										{{-- Отдельное начало регистрации для ограничиваемого пола --}}
										<div id="gender_limited_reg_box" class="mt-2" style="display:none">
											<hr class="mb-1">
											<div class="row">
												<div class="col-sm-6">
													<label>
														<span id="gender_limited_reg_label">{{ __('events.gender_limited_neutral') }}</span>{{ __('events.gender_limited_reg_starts') }}
													</label>
													<select name="game_gender_limited_reg_starts_days_before" id="game_gender_limited_reg_starts_days_before">
														<option value="">{{ __('events.gender_limited_reg_unset') }}</option>
														@for ($d = 0; $d <= 90; $d++)
															<option value="{{ $d }}" 
																@selected(old('game_gender_limited_reg_starts_days_before', $prefill['game_gender_limited_reg_starts_days_before'] ?? '') == $d)>
																{{ $d }}
															</option>
														@endfor
													</select>
													<ul class="list f-16 mt-1">
														<li class="b-600">{{ __('events.gender_limited_days_to') }}</li>
														<li>{{ __('events.gender_limited_reg_hint1') }}</li>
														<li>{{ __('events.gender_limited_reg_hint2') }}</li>
													</ul>
												</div>
											</div>
										</div>
									</div>
								</div>
								
							</div>
						</div>
						<div class="ramka" data-show-if="allow_registration=1">
							<h2 class="-mt-05">{{ __('events.recurrence_title') }}</h2>		
							
							{{-- ✅ Повторение перенесено сюда (Step 2) --}}
							<div id="recurrence_box">
								<div class="mb-1">
									{{-- toggle --}}
									<label class="checkbox-item">
										<input type="hidden" name="is_recurring" value="0">
										<input type="checkbox" name="is_recurring" value="1" id="is_recurring">
										<div class="custom-checkbox"></div>
										<span>{{ __('events.recurrence_toggle') }}</span>
									</label>	
									
									<ul class="list f-16 mt-1" id="recurrence_disabled_hint">
										<li>{{ __('events.recurrence_disabled_hint') }}</li>
									</ul>										
									
								</div>
								{{-- fields --}}
								<div class="row mt-2" id="recurrence_fields" style="display:none;">
									
									{{-- type --}}
									<div class="col-md-4">
										<div class="card">
											<label>{{ __('events.recurrence_type') }}</label>
											<select name="recurrence_type" id="recurrence_type">
												<option value="">{{ __('events.tournament_choose') }}</option>
												<option value="daily">{{ __('events.recurrence_daily') }}</option>
												<option value="weekly">{{ __('events.recurrence_weekly') }}</option>
												<option value="monthly">{{ __('events.recurrence_monthly') }}</option>
											</select>
											
											{{-- WEEKDAYS --}}
											<div class="mt-2" id="weekdays_wrap" style="display:none;">
												
												<label>
													{{ __('events.recurrence_weekdays') }}
												</label>
												
												<div class="row row2">
													@foreach([
													1 => __('events.weekdays.1'),
													2 => __('events.weekdays.2'), 
													3 => __('events.weekdays.3'),
													4 => __('events.weekdays.4'),
													5 => __('events.weekdays.5'),
													6 => __('events.weekdays.6'),
													7 => __('events.weekdays.7')
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
												{{ __('events.recurrence_end') }}
											</label>
											
											<div class="flex flex-col gap-2">
												<label class="radio-item">
													<input checked type="radio" name="recurrence_end_type" value="none">
													<div class="custom-radio"></div>
													<span>{{ __('events.recurrence_end_none') }}</span>
												</label>
												
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="until">
													<div class="custom-radio"></div>
													<span>{{ __('events.recurrence_end_until') }}</span>
												</label>
												<div class="mb-1">
													<input type="date" name="recurrence_end_until">
												</div>
												<label class="radio-item">
													<input type="radio" name="recurrence_end_type" value="count">
													<div class="custom-radio"></div>
													<span>{{ __('events.recurrence_end_count') }}</span>
												</label>
												
												<input type="number"
												min="1"
												name="recurrence_end_count"
												placeholder="{{ __('events.recurrence_count_ph') }}">
												<div class="pb-05"></div>												
											</div>
										</div>									
									</div>
									
									
									{{-- interval --}}
									<div class="col-md-4">
										<div class="card">
											<label>{{ __('events.recurrence_interval') }}</label>
											<input type="number"
											min="1" max="365"
											id="recurrence_interval"
											name="recurrence_interval"
											value="1">
											
											<ul class="list f-16 mt-1">
												<li>{{ __('events.recurrence_int_1') }}</li>
												<li>{{ __('events.recurrence_int_2') }}</li>
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
        
        // Обработка data-hide-if для registration_type (устаревшее поле, сохранено для совместимости)
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

        // Обработка data-hide-if для registration_mode (поддержка через запятую: team,team_classic,team_beach)
        document.querySelectorAll('[data-hide-if]').forEach(function(el) {
            var hideCondition = el.getAttribute('data-hide-if');
            if (hideCondition && hideCondition.indexOf('registration_mode=') !== -1) {
                var match = hideCondition.match(/registration_mode=([a-zA-Z_,]+)/);
                if (match) {
                    var modes = match[1].split(',');
                    var regModeEl = document.getElementById('registration_mode');
                    var currentMode = regModeEl ? regModeEl.value : 'single';
                    el.style.display = modes.indexOf(currentMode) !== -1 ? 'none' : '';
                }
            }
        });

        // Обработка data-hide-if для format (поддержка через запятую: tournament,...)
        document.querySelectorAll('[data-hide-if]').forEach(function(el) {
            var hideCondition = el.getAttribute('data-hide-if');
            if (hideCondition && hideCondition.indexOf('format=') !== -1) {
                var match = hideCondition.match(/format=([a-zA-Z_,]+)/);
                if (match) {
                    var formats = match[1].split(',');
                    var formatEl = document.getElementById('format');
                    var currentFormat = formatEl ? formatEl.value : 'game';
                    if (formats.indexOf(currentFormat) !== -1) {
                        el.style.display = 'none';
                    }
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
    window.applyAllowRegShowIf = applyAllowRegShowIf;
    applyAllowRegShowIf();
})();
</script>	
								
								
							</div>
							
							
						</div>	

						{{-- ══════════════════════════════════════════════════ --}}
						{{-- СЕРИЯ ТУРНИРОВ (Season / League) --}}
						{{-- ══════════════════════════════════════════════════ --}}
						<div class="ramka" id="season_league_box" style="display:none; position:relative; z-index:9; overflow:visible;">
							<h2 class="-mt-05">{{ __('events.season_title') }}</h2>

							<label class="checkbox-item">
								<input type="hidden" name="create_season" value="0">
								<input type="checkbox" name="create_season" value="1" id="create_season">
								<div class="custom-checkbox"></div>
								<span>{{ __('events.season_create') }}</span>
							</label>

							<ul class="list f-16 mt-1 mb-2" id="season_hint">
								<li>{{ __('events.season_hint_1') }}</li>
								<li>{{ __('events.season_hint_2') }}</li>
							</ul>

							<div id="season_fields" class="mt-2">

								{{-- Выбор: новая лига или существующая --}}
								<div class="row">
									<div class="col-md-6">
										<div class="card">
											<label>{{ __('events.season_league_label') }}</label>
											<select name="season_league_mode" id="season_league_mode">
												<option value="new">{{ __('events.season_league_new') }}</option>
												@if(isset($organizerSeasons) && $organizerSeasons->count())
													<option value="existing">{{ __('events.season_league_existing') }}</option>
												@endif
											</select>
										</div>
									</div>
								</div>

								{{-- Новая лига --}}
								<div class="row mt-2" id="new_league_fields">
									<div class="col-md-6">
										<div class="card">
											<label for="new_league_name">{{ __('events.season_league_name') }}</label>
											<input type="text"
												id="new_league_name"
												name="new_league_name"
												value="{{ old('new_league_name', __('events.season_league_default')) }}"
												placeholder="{{ __('events.season_league_ph') }}"
												class="w-full rounded-lg border-gray-200">
										</div>
									</div>
								</div>

								{{-- Существующая лига (иерархия: Лига → Сезон → Дивизион) --}}
								<div class="row mt-2" id="existing_league_fields" style="display:none;">
									<div class="col-md-4">
										<div class="card" style="overflow:visible">
											<label>{{ __('events.season_league_label') }}</label>
											<select id="top_league_select">
												<option value="">{{ __('events.season_league_choose') }}</option>
												@if(isset($organizerSeasons))
													@php
													$topLeaguesForSelect = [];
													foreach($organizerSeasons as $s) {
														$lid = $s->league_id;
														if ($lid && !isset($topLeaguesForSelect[$lid])) {
															$topLeaguesForSelect[$lid] = $s->league?->name ?? (__('events.season_league_n', ['id' => $lid]));
														}
													}
													@endphp
													@foreach($topLeaguesForSelect as $lid => $lname)
														<option value="{{ $lid }}">{{ $lname }}</option>
													@endforeach
												@endif
											</select>
										</div>
									</div>
									{{-- Сезон — показывается после выбора лиги --}}
									<div class="col-md-4" id="season_col" style="display:none;">
										<div class="card" style="overflow:visible">
											<label>{{ __('events.season_label') }}</label>
											<select name="existing_season_id" id="existing_season_id">
												<option value="">{{ __('events.season_choose') }}</option>
											</select>
										</div>
									</div>
									{{-- Дивизион — показывается после выбора сезона --}}
									<div class="col-md-4" id="division_col" style="display:none;">
										<div class="card" style="overflow:visible">
											<label>{{ __('events.division_label') }}</label>
											<select name="existing_league_id" id="existing_league_id">
												<option value="">{{ __('events.division_choose') }}</option>
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
							const topLeagueSelect     = document.getElementById('top_league_select');
							const leagueSelect        = document.getElementById('existing_league_id');
							const seasonCol           = document.getElementById('season_col');
							const divisionCol         = document.getElementById('division_col');
							const seasonSelect        = document.getElementById('existing_season_id');

							const leaguesData = @php
							$leaguesJs = [];
							if (isset($organizerSeasons)) {
								foreach ($organizerSeasons as $s) {
									$lid = $s->league_id;
									if (!$lid) continue;
									if (!isset($leaguesJs[$lid])) {
										$lname = $s->league?->name ?? (__('events.season_league_n', ['id' => $lid]));
										$leaguesJs[$lid] = ['id' => $lid, 'name' => $lname, 'seasons' => []];
									}
									$divs = [];
									foreach ($s->leagues as $div) {
										$divs[] = ['id' => $div->id, 'name' => $div->name];
									}
									$leaguesJs[$lid]['seasons'][] = ['id' => $s->id, 'name' => $s->name, 'divisions' => $divs];
								}
							}
							echo json_encode(array_values($leaguesJs));
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

							// createCustomSelect оборачивает <select>, и после программного добавления
							// <option> кастомный dropdown надо пересобрать. window.customSelect.destroy
							// удаляет только один ближайший wrapper через prev() — если успели
							// накопиться дубли (повторные re-init), остатки попадают в DOM.
							// Поэтому вычищаем ВСЕ соседние .form-select-wrapper перед <select>.
							function rebuildCustomSelect(selectEl) {
								if (!selectEl || !window.jQuery) return;
								const $sel = window.jQuery(selectEl);
								while ($sel.prev('.form-select-wrapper').length) {
									$sel.prev('.form-select-wrapper').remove();
								}
								$sel.removeData('custom-initialized');
								if (typeof window.initCustomSelects === 'function') {
									window.initCustomSelects();
								}
							}

							function populateSeasons() {
								const lid = parseInt(topLeagueSelect ? topLeagueSelect.value : 0);
								seasonSelect.innerHTML = '<option value="">{{ __('events.season_choose') }}</option>';
								leagueSelect.innerHTML = '<option value="">{{ __('events.division_choose') }}</option>';
								divisionCol.style.display = 'none';
								if (!lid) {
									seasonCol.style.display = 'none';
									rebuildCustomSelect(seasonSelect);
									rebuildCustomSelect(leagueSelect);
									return;
								}

								const league = leaguesData.find(l => l.id === lid);
								if (league && league.seasons.length > 0) {
									league.seasons.forEach(s => {
										const opt = document.createElement('option');
										opt.value = s.id;
										opt.textContent = s.name;
										seasonSelect.appendChild(opt);
									});
								}
								seasonCol.style.display = '';
								rebuildCustomSelect(seasonSelect);
								rebuildCustomSelect(leagueSelect);
							}

							function populateDivisions() {
								const lid    = parseInt(topLeagueSelect ? topLeagueSelect.value : 0);
								const sid    = parseInt(seasonSelect.value);
								leagueSelect.innerHTML = '<option value="">{{ __('events.division_choose') }}</option>';
								divisionCol.style.display = 'none';
								if (!lid || !sid) {
									rebuildCustomSelect(leagueSelect);
									return;
								}

								const league  = leaguesData.find(l => l.id === lid);
								const season  = league && league.seasons.find(s => s.id === sid);
								if (season && season.divisions && season.divisions.length > 0) {
									season.divisions.forEach(d => {
										const opt = document.createElement('option');
										opt.value = d.id;
										opt.textContent = d.name;
										leagueSelect.appendChild(opt);
									});
									if (season.divisions.length === 1) leagueSelect.value = season.divisions[0].id;
									divisionCol.style.display = '';
								}
								rebuildCustomSelect(leagueSelect);
							}

							if (formatSelect) {
								formatSelect.addEventListener('change', syncSeasonBox);
								document.querySelectorAll('input[name="format"]').forEach(r => r.addEventListener('change', syncSeasonBox));
							}
							if (isRecurring)   isRecurring.addEventListener('change', syncSeasonBox);
							if (createSeason)  createSeason.addEventListener('change', syncSeasonFields);
							if (leagueMode)    leagueMode.addEventListener('change', syncLeagueMode);
							if (topLeagueSelect) topLeagueSelect.addEventListener('change', populateSeasons);
							if (seasonSelect)    seasonSelect.addEventListener('change', populateDivisions);

							syncSeasonBox();
							syncSeasonFields();
							syncLeagueMode();
						});
						</script>

						<div class="ramka text-center">
							<button type="button" class="btn btn-secondary" data-back>
								{{ __('events.btn_back') }}
							</button>
							<button type="button" class="btn" data-next>
								{{ __('events.btn_next') }}
							</button>
						</div>							
