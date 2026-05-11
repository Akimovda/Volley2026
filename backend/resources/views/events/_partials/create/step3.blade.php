						<div class="ramka" style="z-index: 5" data-show-if="allow_registration=1">
							<h2 class="-mt-05">{{ __('events.access_title') }}</h2>		
							<div class="row">
								<div class="col-md-4">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="is_private" value="0">
											<input type="checkbox" name="is_private" value="1" id="is_private">
											<div class="custom-checkbox"></div>
											<span>{{ __('events.private_label') }}</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>{{ __('events.private_hint') }}</li>
										</ul>											
									</div>
								</div>
                                <div class="col-md-4">
                                    <div class="card">
                                        <label class="checkbox-item">
                                            <input type="hidden" name="is_paid" value="0">
                                            <input
											type="checkbox"
											name="is_paid"
											value="1"
											id="is_paid"
											@checked((bool) old('is_paid', $prefill['is_paid'] ?? false))
                                            >
                                            <div class="custom-checkbox"></div>
                                            <span>{{ __('events.paid_label') }}</span>
										</label>
										
                                        <div class="row mt-2" id="price_wrap">
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('events.price_label') }}</label>
                                                <input
												type="number"
												name="price_amount"
												class="form-input"
												value="{{ old('price_amount', $prefill['price_amount'] ?? '') }}"
												placeholder="{{ __('events.price_ph') }}"
												min="10"
												max="500000"
												step="0.01"
												inputmode="decimal"
                                                >
                                                @error('price_amount')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                @enderror
											</div>
											
                                            <div class="col-md-6">
                                                <label class="form-label">{{ __('events.currency_label') }}</label>
                                                <select name="price_currency" class="form-select">
                                                    @php
													$currencyOptions = [
													'RUB' => __('events.cur_RUB'),
													'USD' => __('events.cur_USD'),
													'EUR' => __('events.cur_EUR'),
													'KZT' => __('events.cur_KZT'),
													'KGS' => __('events.cur_KGS'),
													'BYN' => __('events.cur_BYN'),
													'UZS' => __('events.cur_UZS'),
													'AMD' => __('events.cur_AMD'),
													'AZN' => __('events.cur_AZN'),
													'TJS' => __('events.cur_TJS'),
													'TMT' => __('events.cur_TMT'),
													'GEL' => __('events.cur_GEL'),
													'MDL' => __('events.cur_MDL'),
													];
													
													$selectedCurrency = old('price_currency', $prefill['price_currency'] ?? 'RUB');
                                                    @endphp
													
                                                    @foreach($currencyOptions as $code => $label)
													<option value="{{ $code }}" @selected($selectedCurrency === $code)>
														{{ $label }}
													</option>
                                                    @endforeach
												</select>
                                                @error('price_currency')
												<div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                                @enderror
											</div>
										</div>
										 {{-- СПОСОБ ОПЛАТЫ --}}
                                        <div id="payment_method_wrap" class="mt-2">
                                            <label>{{ __('events.pay_method_label') }}</label>
                                            @php
                                                $pm = old('payment_method', $prefill['payment_method'] ?? 'cash');
                                                $orgPaySettings = auth()->check()
                                                    ? \App\Models\PaymentSetting::where('organizer_id', auth()->id())->first()
                                                    : null;
                                            @endphp
                                            <select name="payment_method" id="payment_method">
                                                <option value="cash" @selected($pm === 'cash')>{{ __('events.pay_method_cash') }}</option>
                                                <option value="tbank_link" @selected($pm === 'tbank_link')>{{ __('events.pay_method_tbank') }}</option>
                                                <option value="sber_link" @selected($pm === 'sber_link')>{{ __('events.pay_method_sber') }}</option>
                                                @if($orgPaySettings?->yoomoney_verified)
                                                <option value="yoomoney" @selected($pm === 'yoomoney')>{{ __('events.pay_method_yoomoney') }}</option>
                                                @endif
                                            </select>

                                            {{-- Ссылка для перевода --}}
                                            <div id="payment_link_wrap" class="mt-1" style="display:none">
                                                <label>{{ __('events.pay_link_label') }}</label>
                                                <input type="url" name="payment_link"
                                                    value="{{ old('payment_link', $prefill['payment_link'] ?? ($pm === 'tbank_link' ? $orgPaySettings?->tbank_link : ($pm === 'sber_link' ? $orgPaySettings?->sber_link : ''))) }}"
                                                    placeholder="https://...">
                                                <ul class="list f-14 mt-1">
                                                    <li>{{ __('events.pay_link_auto') }}</li>
                                                </ul>
                                            </div>

                                            <ul class="list f-14 mt-1" id="payment_method_hint">
                                                <li id="hint_cash">{{ __('events.pay_hint_cash') }}</li>
                                                <li id="hint_link" style="display:none">{{ __('events.pay_hint_link') }}</li>
                                                <li id="hint_yoomoney" style="display:none">{{ __('events.pay_hint_yoomoney', ['n' => $orgPaySettings?->payment_hold_minutes ?? 15]) }}</li>
                                            </ul>
                                        </div>

                                        {{-- РЕЖИМ ОПЛАТЫ ТУРНИРА (только для format=tournament) --}}
                                        <div class="mt-2" id="tournament_payment_mode_wrap" style="display:none">
                                            <label>{{ __('events.tournament_pay_mode') }}</label>
                                            @php
                                                $tpm = old('tournament_payment_mode', $prefill['tournament_payment_mode'] ?? 'team');
                                            @endphp
                                            <select name="tournament_payment_mode" id="tournament_payment_mode">
                                                <option value="team" @selected($tpm === 'team')>{{ __('events.tournament_pay_team') }}</option>
                                                <option value="per_player" @selected($tpm === 'per_player')>{{ __('events.tournament_pay_per') }}</option>
                                            </select>

                                            <ul class="list f-14 mt-1" id="tournament_payment_mode_hints">
                                                <li id="hint_team_pay">{{ __('events.tournament_pay_team_hint') }}</li>
                                                <li id="hint_per_player_pay" style="display:none">{{ __('events.tournament_pay_per_hint') }}</li>
                                            </ul>
                                        </div>

                                        {{-- ПОЛИТИКА ВОЗВРАТА (только для платных) --}}
                                        <div class="mt-2" id="refund_wrap" style="display:none">
                                            <label>{{ __('events.refund_title') }}</label>
                                            <div class="row row2">
                                                <div class="col-4">
                                                    <label class="f-14">{{ __('events.refund_full_hours') }}</label>
                                                    <input type="number" name="refund_hours_full" min="0" max="720"
                                                        value="{{ old('refund_hours_full', $prefill['refund_hours_full'] ?? $orgPaySettings?->refund_hours_full ?? 48) }}">
                                                </div>
                                                <div class="col-4">
                                                    <label class="f-14">{{ __('events.refund_partial_hours') }}</label>
                                                    <input type="number" name="refund_hours_partial" min="0" max="720"
                                                        value="{{ old('refund_hours_partial', $prefill['refund_hours_partial'] ?? $orgPaySettings?->refund_hours_partial ?? 24) }}">
                                                </div>
                                                <div class="col-4">
                                                    <label class="f-14">{{ __('events.refund_partial_pct') }}</label>
                                                    <input type="number" name="refund_partial_pct" min="0" max="100"
                                                        value="{{ old('refund_partial_pct', $prefill['refund_partial_pct'] ?? $orgPaySettings?->refund_partial_pct ?? 50) }}">
                                                </div>
                                            </div>
                                            <ul class="list f-14 mt-1">
                                                <li>{{ __('events.refund_quorum_hint') }}</li>
                                            </ul>
                                        </div>
									</div>

                                       
								</div>
								<div class="col-md-4">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="requires_personal_data" value="0">
											<input type="checkbox" name="requires_personal_data" value="1">
											<div class="custom-checkbox"></div>
											<span class="text-sm font-semibold">{{ __('events.personal_data_label') }}</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>{{ __('events.personal_data_hint') }}</li>
										</ul>										
									</div>
								</div>
							</div>
						</div>
						{{-- ===== Помощник записи 🤖 =====--}}
						
                        <div class="ramka" data-show-if="allow_registration=1" data-hide-if="registration_mode=team,team_classic,team_beach|format=tournament" id="bot_assistant_block">
                            <h2 class="-mt-05">{{ __('events.bot_title') }}</h2>
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="card">
                                        <label class="checkbox-item">
                                            <input type="hidden" name="bot_assistant_enabled" value="0">
                                            <input
											type="checkbox"
											name="bot_assistant_enabled"
											value="1"
											id="bot_assistant_enabled"
											@checked((bool) old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false))
                                            >
                                            <div class="custom-checkbox"></div>
                                            <span>{{ __('events.bot_enable') }}</span>
										</label>
										
                                        <ul class="list f-16 mt-1">
                                            <li>{!! __('events.bot_hint_1') !!}</li>
                                            <li>{{ __('events.bot_hint_2') }}</li>
                                            <li>{{ __('events.bot_hint_3') }}</li>
                                            <li>{{ __('events.bot_hint_4') }}</li>
                                            <li>{{ __('events.bot_hint_5') }}</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_settings" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>{{ __('events.bot_threshold_label') }}</label>
                                        <div class="d-flex fvc gap-2 mt-1">
                                            <input
											type="range"
											name="bot_assistant_threshold"
											id="bot_assistant_threshold"
											min="5"
											max="30"
											step="5"
											value="{{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}"
											style="flex:1"
											oninput="document.getElementById('bot_threshold_val').textContent = this.value + '%'"
                                            >
                                            <strong id="bot_threshold_val" class="cd" style="min-width:3rem; text-align:right">
                                                {{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}%
											</strong>
										</div>
                                        <ul class="list f-16 mt-1">
                                            <li>{!! __('events.bot_threshold_hint', ['id' => 'bot_threshold_hint', 'val' => old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10)]) !!}</li>
                                            <li>{{ __('events.bot_threshold_range') }}</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_fill" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>{{ __('events.bot_fill_label') }}</label>
                                        <div class="d-flex fvc gap-2 mt-1">
                                            <input
											type="range"
											name="bot_assistant_max_fill_pct"
											id="bot_assistant_max_fill_pct"
											min="10"
											max="60"
											step="10"
											value="{{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}"
											style="flex:1"
											oninput="document.getElementById('bot_fill_val').textContent = this.value + '%'"
                                            >
                                            <strong id="bot_fill_val" class="cd" style="min-width:3rem; text-align:right">
                                                {{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}%
											</strong>
										</div>
                                        <ul class="list f-16 mt-1">
                                            <li>{!! __('events.bot_fill_hint', ['id' => 'bot_fill_hint', 'val' => old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40)]) !!}</li>
                                            <li>{{ __('events.bot_fill_safe') }}</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="ramka" data-show-if="allow_registration=1">
							<h2 class="-mt-05">{{ __('events.notify_title') }}</h2>		
							<div class="row">
								
								{{-- ✅ Notifications + participants visibility --}}
								
								
								@php
								$remMin = (int) old('remind_registration_minutes_before', $prefill['remind_registration_minutes_before'] ?? 600);
								if ($remMin < 0) $remMin = 600;
								$showParts = (bool) old('show_participants', $prefill['show_participants'] ?? true);
								@endphp
								
								<div class="col-md-4">
                                    <div class="card">
                                        <label>{{ __('events.remind_label') }}</label>
                                
                                        <label class="checkbox-item">
                                            <input type="hidden" name="remind_registration_enabled" value="0">
                                            <input checked type="checkbox" name="remind_registration_enabled" value="1" id="remind_registration_enabled">
                                            <div class="custom-checkbox"></div>
                                            <span>{{ __('events.remind_enabled') }}</span>
                                        </label>
                                
                                        <div class="mt-2">
                                            <label>{{ __('events.remind_when') }}</label>
                                            <div class="row row2">
                                                <div class="col-6">
                                                    <label>{{ __('events.remind_hours') }}</label>
                                                    <select
                                                        id="remind_hours_input"
                                                        class="w-full rounded-lg border-gray-200"
                                                    >
                                                        @for ($h = 0; $h <= 24; $h++)
                                                            <option value="{{ $h }}" 
                                                                @selected((int) floor($remMin / 60) == $h)>
                                                                {{ $h }}
                                                            </option>
                                                        @endfor
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label>{{ __('events.remind_minutes') }}</label>
                                                    <select
                                                        id="remind_minutes_input"
                                                        class="w-full rounded-lg border-gray-200"
                                                    >
                                                        @foreach ([0,5,10,15,20,25,30,35,40,45,50,55,60] as $m)
                                                            <option value="{{ $m }}" 
                                                                @selected(($remMin % 60) == $m)>
                                                                {{ $m }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                
                                            <input
                                                type="hidden"
                                                name="remind_registration_minutes_before"
                                                id="remind_registration_minutes_before"
                                                value="{{ $remMin }}"
                                            >
                                
                                            <ul class="list f-16 mt-1">
                                                <li id="remind_fire_at_hint_create" class="cd b-600"></li>
                                                <li>{{ __('events.remind_example') }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const hoursInput   = document.getElementById('remind_hours_input');
                                        const minutesInput = document.getElementById('remind_minutes_input');
                                        const hidden       = document.getElementById('remind_registration_minutes_before');
                                        const hint         = document.getElementById('remind_fire_at_hint_create');

                                        function localEventTimeToUTC(startsVal, tzName) {
                                            if (!startsVal || !tzName) return null;
                                            try {
                                                const naiveUTC = new Date(startsVal + ':00Z');
                                                const parts = new Intl.DateTimeFormat('en-CA', {
                                                    timeZone: tzName, hour12: false,
                                                    year: 'numeric', month: '2-digit', day: '2-digit',
                                                    hour: '2-digit', minute: '2-digit'
                                                }).formatToParts(naiveUTC);
                                                const get = t => parts.find(p => p.type === t).value;
                                                const tzMs = new Date(get('year')+'-'+get('month')+'-'+get('day')+'T'+get('hour')+':'+get('minute')+':00Z').getTime();
                                                return new Date(naiveUTC.getTime() - (tzMs - naiveUTC.getTime()));
                                            } catch(e) { return null; }
                                        }

                                        function syncRemind() {
                                            const h = Math.max(0, parseInt(hoursInput?.value || 0, 10));
                                            const m = Math.max(0, Math.min(59, parseInt(minutesInput?.value || 0, 10)));
                                            if (hidden) hidden.value = h * 60 + m;

                                            if (hint) {
                                                const startsVal = document.getElementById('starts_at_local')?.value || '';
                                                const tz = document.getElementById('event_timezone_hidden')?.value || '';
                                                if (startsVal && tz) {
                                                    const startsUTC = localEventTimeToUTC(startsVal, tz);
                                                    if (startsUTC) {
                                                        const fireUTC = new Date(startsUTC.getTime() - (h * 60 + m) * 60000);
                                                        try {
                                                            const fmt = new Intl.DateTimeFormat('ru-RU', {
                                                                timeZone: tz, day: '2-digit', month: '2-digit',
                                                                hour: '2-digit', minute: '2-digit', hour12: false
                                                            });
                                                            hint.textContent = '→ Напоминание придёт ~' + fmt.format(fireUTC) + ' (' + tz + ')';
                                                        } catch(e) { hint.textContent = ''; }
                                                    } else { hint.textContent = ''; }
                                                } else { hint.textContent = ''; }
                                            }
                                        }

                                        hoursInput?.addEventListener('change', syncRemind);
                                        minutesInput?.addEventListener('change', syncRemind);
                                        document.getElementById('starts_at_local')?.addEventListener('change', syncRemind);
                                        document.getElementById('event_timezone_hidden')?.addEventListener('change', syncRemind);
                                        syncRemind();
                                    });
                                </script>
                                {{-- CHANNEL NOTIFICATIONS --}}
                                @php
								$selectedChannels = old('channels', []);
								if (!is_array($selectedChannels)) {
								$selectedChannels = [];
								}
                                
								$channelSilent = (bool) old('channel_silent', false);
								$channelUpdateMessage = (bool) old('channel_update_message', true);
								$channelIncludeImage = (bool) old('channel_include_image', true);
								$channelIncludeRegistered = (bool) old('channel_include_registered', true);
                                
								$selectedOrganizerId = (int) old('organizer_id', $prefill['organizer_id'] ?? auth()->id());
                                
								$userChannels = \App\Models\UserNotificationChannel::query()
								->verified()
								->where('user_id', $selectedOrganizerId)
								->orderBy('platform')
								->orderBy('title')
								->get();
                                @endphp
                                
                                <div class="col-md-4">
                                    <div class="card">
                                        <label>{{ __('events.channels_label') }}</label>
										
                                        <ul class="list f-16 mb-2">
                                            <li>{{ __('events.channels_hint_1') }}</li>
                                            <li>{{ __('events.channels_hint_2') }}</li>
										</ul>
										
                                        @if($userChannels->isEmpty())
										<div class="f-16">
											{{ __('events.channels_none_pre') }}
											<a href="{{ route('profile.notification_channels') }}" class="link">
												{{ __('events.channels_none_link') }}
											</a>
										</div>
                                        @else
										<div class="mt-2">
											@foreach($userChannels as $channel)
											<label class="checkbox-item">
												<input type="checkbox"
												name="channels[]"
												value="{{ $channel->id }}"
												@checked(in_array((string) $channel->id, array_map('strval', $selectedChannels), true))>
												<div class="custom-checkbox"></div>
												<span>
													{{ strtoupper($channel->platform) }} — {{ $channel->title ?: __('events.channels_no_title') }}
													<span class="text-muted">({{ $channel->chat_id }})</span>
												</span>
											</label>
											@endforeach
										</div>
										
										<div class="mt-2">
											<label class="checkbox-item">
												<input type="hidden" name="channel_silent" value="0">
												<input type="checkbox" name="channel_silent" value="1" @checked($channelSilent)>
												<div class="custom-checkbox"></div>
												<span>{{ __('events.channels_silent') }}</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_update_message" value="0">
												<input type="checkbox" name="channel_update_message" value="1" @checked($channelUpdateMessage)>
												<div class="custom-checkbox"></div>
												<span>{{ __('events.channels_update_msg') }}</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_image" value="0">
												<input type="checkbox" name="channel_include_image" value="1" @checked($channelIncludeImage)>
												<div class="custom-checkbox"></div>
												<span>{{ __('events.channels_with_image') }}</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_registered" value="0">
												<input type="checkbox" name="channel_include_registered" value="1" @checked($channelIncludeRegistered)>
												<div class="custom-checkbox"></div>
												<span>{{ __('events.channels_with_players') }}</span>
											</label>
										</div>
                                        @endif
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<label>{{ __('events.show_participants_label') }}</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="1" @checked($showParts)>
											<div class="custom-radio"></div>
											<span>{{ __('events.yes') }}</span>
										</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="0" @checked(!$showParts)>
											<div class="custom-radio"></div>
											<span>{{ __('events.no') }}</span>
										</label>
										
										<ul class="list f-16 mt-1">
											<li>{{ __('events.show_participants_hint') }}</li>
										</ul>											
										
									</div>
								</div>
							</div>
						</div>
						<div class="ramka" style="z-index:6">
							<h2 class="-mt-05">{{ __('events.photo_desc_title') }}</h2>		
							
							<div class="row">
								
								

								
								{{-- ✅ COVER --}}
								
								<div class="col-md-4">
									
										
										{{--
										
										<p>
											Можно загрузить файл или выбрать из вашей галереи. Если загружен файл — он важнее выбора из галереи.
										</p>
										
										
										<label>Загрузить с компьютера</label>
										<input type="file" name="cover_upload" accept="image/*" class="w-full rounded-lg border-gray-200">
										
										<ul class="list f-16 mt-1">
											<li>JPG / PNG / WebP, до 5MB.</li>
										</ul>												
										--}}	
										
										
										
										@php
										$userEventPhotos = auth()->user()->getMedia('event_photos')->sortByDesc('created_at');
										@endphp
										
										@if($userEventPhotos->count() > 0)
										<div class="card">	
										<div>
											<label>{{ __('events.photos_label') }}</label>
											
											
											
											
											<div class="event-photos-selector" 
											data-selected='{{ json_encode(old('event_photos', $eventPhotos ?? [])) }}'>
											
											<div class="swiper eventPhotosSwiper">
											<div class="swiper-wrapper">
											@foreach($userEventPhotos as $photo)
											<div class="swiper-slide">
											<div class="hover-image mb-1">
											<img src="{{ $photo->getUrl('event_thumb') }}" alt="event photo" loading="lazy"/>
										</div>
										<div class="mt-1 d-flex between fvc">
											<label class="checkbox-item mb-0">
												<input type="checkbox" class="photo-select" value="{{ $photo->id }}">
												<div class="custom-checkbox"></div>
												<span>{{ __('events.photo_select') }}</span>
											</label>    
											<div class="photo-order-badge f-16 b-600 cd"></div>
										</div>
									</div>
									@endforeach
								</div>
								<div class="swiper-pagination"></div>
							</div>
							
							<ul class="list f-16 mt-1">
								<li>{{ __('events.photo_select_hint_1') }}</li>
								<li>{{ __('events.photo_select_hint_2_pre') }} <a target="_blank" href="{{ route('user.photos') }}">{{ __('events.photo_select_hint_2_link') }}</a></li>
							</ul>														
							
							<input type="hidden" name="event_photos" id="event_photos_input" value="">
						</div>
					</div>
					<script>
						document.addEventListener('DOMContentLoaded', function() {
							// Инициализация Swiper
							new Swiper('.eventPhotosSwiper', {
								slidesPerView: 1,
								spaceBetween: 15,
								pagination: { el: '.swiper-pagination', clickable: true },
								breakpoints: { 640: { slidesPerView: 1 }, 768: { slidesPerView: 1 }, 1024: { slidesPerView: 1 } }
							});
							
							const container = document.querySelector('.event-photos-selector');
							const savedPhotos = JSON.parse(container.dataset.selected || '[]');
							let selectedPhotos = [...savedPhotos]; // копируем массив
							
							function updateUI() {
								document.querySelectorAll('.photo-select').forEach(checkbox => {
									const id = parseInt(checkbox.value);
									const isSelected = selectedPhotos.includes(id);
									checkbox.checked = isSelected;
									
									const badge = checkbox.closest('.swiper-slide').querySelector('.photo-order-badge');
									if (isSelected) {
										const order = selectedPhotos.indexOf(id) + 1;
										badge.textContent = order === 1 ? @json(__('events.photo_main')) : (@json(__('events.photo_pos_n', ['n' => ''])) + order);
										} else {
										badge.textContent = '';
									}
								});
								
								document.getElementById('event_photos_input').value = JSON.stringify(selectedPhotos);
							}
							
							document.querySelectorAll('.photo-select').forEach(checkbox => {
								checkbox.addEventListener('change', function() {
									const id = parseInt(this.value);
									
									if (this.checked) {
										selectedPhotos.push(id);
										} else {
										const index = selectedPhotos.indexOf(id);
										if (index !== -1) selectedPhotos.splice(index, 1);
									}
									
									updateUI();
								});
							});
							
							updateUI();
						});
					</script>
					</div>
					@else

						<div class="alert alert-info">
							<p>{{ __('events.photo_empty_p1') }}</p> 
							<p>{{ __('events.photo_select_hint_2_pre') }} <a target="_blank" href="{{ route('user.photos') }}">{{ __('events.photo_select_hint_2_link') }}</a></p>
						</div>

					@endif
					
					
					
				
			</div>
			
								{{-- STEP 3: Описание мероприятия --}}
								<div class="col-md-8">
									<div class="card">
										<label>{{ __('events.desc_label') }}</label>
										
										{{-- Важно: hidden input + trix-editor --}}
										<input id="description_html" type="hidden" name="description_html">
										
										<trix-editor input="description_html" class="trix-content"></trix-editor>
<script>
document.addEventListener('trix-paste', function(e) {
    var editor = e.target.editor;
    if (!editor) return;
    // Небольшая задержка чтобы контент вставился
    setTimeout(function() {
        var doc = editor.getDocument();
        var text = doc.toString();
        // Очищаем и вставляем чистый текст
        editor.loadHTML(editor.element.innerHTML.replace(/style="[^"]*"/gi, '').replace(/class="[^"]*"/gi, ''));
    }, 10);
});
</script>
										
										@error('description_html')
										<div class="text-red-600 text-sm mt-2">{{ $message }}</div>
										@enderror
										
									</div>
								</div>							
											
			
			
			
			
		</div>
	</div>
	<div class="ramka text-center">
		<button type="button" class="btn btn-secondary" data-back>
			{{ __('events.btn_back') }}
		</button>
		<button type="submit" class="btn">{{ __('events.btn_create') }}</button>
	</div>							
