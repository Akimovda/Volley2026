						<div class="ramka" style="z-index: 5" data-show-if="allow_registration=1">
							<h2 class="-mt-05">Доступность</h2>		
							<div class="row">
								<div class="col-md-4">
									<div class="card">
										<label class="checkbox-item">
											<input type="hidden" name="is_private" value="0">
											<input type="checkbox" name="is_private" value="1" id="is_private">
											<div class="custom-checkbox"></div>
											<span>Приватное (доступно только по ссылке)</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>Будет сгенерирован токен ссылки (public_token) для приватного.</li>
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
                                            <span>Платное</span>
										</label>
										
                                        <div class="row mt-2" id="price_wrap">
                                            <div class="col-md-6">
                                                <label class="form-label">Стоимость</label>
                                                <input
												type="number"
												name="price_amount"
												class="form-input"
												value="{{ old('price_amount', $prefill['price_amount'] ?? '') }}"
												placeholder="Например: 134"
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
                                                <label class="form-label">Валюта</label>
                                                <select name="price_currency" class="form-select">
                                                    @php
													$currencyOptions = [
													'RUB' => 'RUB — Российский рубль (₽)',
													'USD' => 'USD — Доллар США ($)',
													'EUR' => 'EUR — Евро (€)',
													'KZT' => 'KZT — Тенге (₸)',
													'KGS' => 'KGS — Киргизский сом',
													'BYN' => 'BYN — Белорусский рубль',
													'UZS' => 'UZS — Узбекский сум',
													'AMD' => 'AMD — Армянский драм (֏)',
													'AZN' => 'AZN — Азербайджанский манат (₼)',
													'TJS' => 'TJS — Сомони',
													'TMT' => 'TMT — Туркменский манат',
													'GEL' => 'GEL — Лари (₾)',
													'MDL' => 'MDL — Молдавский лей',
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
                                            <label>Способ оплаты</label>
                                            @php
                                                $pm = old('payment_method', $prefill['payment_method'] ?? 'cash');
                                                $orgPaySettings = auth()->check()
                                                    ? \App\Models\PaymentSetting::where('organizer_id', auth()->id())->first()
                                                    : null;
                                            @endphp
                                            <select name="payment_method" id="payment_method">
                                                <option value="cash" @selected($pm === 'cash')>💵 Наличные (на месте)</option>
                                                <option value="tbank_link" @selected($pm === 'tbank_link')>🏦 Перевод Т-Банк (по ссылке)</option>
                                                <option value="sber_link" @selected($pm === 'sber_link')>💚 Перевод Сбер (по ссылке)</option>
                                                @if($orgPaySettings?->yoomoney_verified)
                                                <option value="yoomoney" @selected($pm === 'yoomoney')>🟡 ЮМани (автооплата)</option>
                                                @endif
                                            </select>

                                            {{-- Ссылка для перевода --}}
                                            <div id="payment_link_wrap" class="mt-1" style="display:none">
                                                <label>Ссылка для перевода</label>
                                                <input type="url" name="payment_link"
                                                    value="{{ old('payment_link', $prefill['payment_link'] ?? ($pm === 'tbank_link' ? $orgPaySettings?->tbank_link : ($pm === 'sber_link' ? $orgPaySettings?->sber_link : ''))) }}"
                                                    placeholder="https://...">
                                                <ul class="list f-14 mt-1">
                                                    <li>Из настроек профиля подставится автоматически</li>
                                                </ul>
                                            </div>

                                            <ul class="list f-14 mt-1" id="payment_method_hint">
                                                <li id="hint_cash">Игроки платят на месте, запись без ограничений</li>
                                                <li id="hint_link" style="display:none">Игрок нажимает "Я оплатил", вы подтверждаете вручную</li>
                                                <li id="hint_yoomoney" style="display:none">Место резервируется на {{ $orgPaySettings?->payment_hold_minutes ?? 15 }} мин. Запись подтверждается после оплаты автоматически</li>
                                            </ul>
                                        </div>

                                        {{-- РЕЖИМ ОПЛАТЫ ТУРНИРА (только для format=tournament) --}}
                                        <div class="mt-2" id="tournament_payment_mode_wrap" style="display:none">
                                            <label>Кто оплачивает участие</label>
                                            @php
                                                $tpm = old('tournament_payment_mode', $prefill['tournament_payment_mode'] ?? 'team');
                                            @endphp
                                            <select name="tournament_payment_mode" id="tournament_payment_mode">
                                                <option value="team" @selected($tpm === 'team')>👑 Капитан за всю команду</option>
                                                <option value="per_player" @selected($tpm === 'per_player')>👤 Каждый игрок сам за себя</option>
                                            </select>

                                            <ul class="list f-14 mt-1" id="tournament_payment_mode_hints">
                                                <li id="hint_team_pay">Капитан оплачивает участие команды целиком. Команда допускается к турниру после оплаты.</li>
                                                <li id="hint_per_player_pay" style="display:none">Каждый участник команды оплачивает своё участие отдельно. Команда допускается когда все оплатили.</li>
                                            </ul>
                                        </div>

                                        {{-- ПОЛИТИКА ВОЗВРАТА (только для платных) --}}
                                        <div class="mt-2" id="refund_wrap" style="display:none">
                                            <label>Политика возврата</label>
                                            <div class="row row2">
                                                <div class="col-4">
                                                    <label class="f-14">100% за (часов)</label>
                                                    <input type="number" name="refund_hours_full" min="0" max="720"
                                                        value="{{ old('refund_hours_full', $prefill['refund_hours_full'] ?? $orgPaySettings?->refund_hours_full ?? 48) }}">
                                                </div>
                                                <div class="col-4">
                                                    <label class="f-14">Частично за (часов)</label>
                                                    <input type="number" name="refund_hours_partial" min="0" max="720"
                                                        value="{{ old('refund_hours_partial', $prefill['refund_hours_partial'] ?? $orgPaySettings?->refund_hours_partial ?? 24) }}">
                                                </div>
                                                <div class="col-4">
                                                    <label class="f-14">Частичный %</label>
                                                    <input type="number" name="refund_partial_pct" min="0" max="100"
                                                        value="{{ old('refund_partial_pct', $prefill['refund_partial_pct'] ?? $orgPaySettings?->refund_partial_pct ?? 50) }}">
                                                </div>
                                            </div>
                                            <ul class="list f-14 mt-1">
                                                <li>При отмене по кворуму — всегда 100% на виртуальный счёт</li>
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
											<span class="text-sm font-semibold">Требовать персональные данные</span>
										</label>
										<ul class="list f-16 mt-1">
											<li>Если включено — при записи будем просить дополнительные данные.</li>
										</ul>										
									</div>
								</div>
							</div>
						</div>
						{{-- ===== Помощник записи 🤖 =====--}}
						
                        <div class="ramka" data-show-if="allow_registration=1" data-hide-if="registration_type=team" id="bot_assistant_block">
                            <h2 class="-mt-05">Помощник записи 🤖</h2>
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
                                            <span>Включить помощника записи</span>
										</label>
										
                                        <ul class="list f-16 mt-1">
                                            <li>Если за первые сутки после открытия записи зарегистрировалось меньше <strong>порога</strong> — боты начнут постепенно занимать места.</li>
                                            <li>По мере прихода живых игроков боты уходят и освобождают места.</li>
                                            <li>Видно только организатору и администратору.</li>
                                            <li>Боты не занимают последнее свободное место.</li>
                                            <li>Активность ботов замораживается за 3 часа до начала.</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_settings" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>Порог запуска (%)</label>
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
                                            <li>Если через сутки записалось меньше <strong id="bot_threshold_hint">{{ old('bot_assistant_threshold', $prefill['bot_assistant_threshold'] ?? 10) }}%</strong> от максимума — боты включаются.</li>
                                            <li>Диапазон: 5–30%.</li>
										</ul>
									</div>
								</div>
								
                                <div class="col-md-6" id="bot_assistant_fill" @if(!old('bot_assistant_enabled', $prefill['bot_assistant_enabled'] ?? false)) style="display:none" @endif>
                                    <div class="card">
                                        <label>Макс. заполнение ботами (%)</label>
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
                                            <li>Боты не займут больше <strong id="bot_fill_hint">{{ old('bot_assistant_max_fill_pct', $prefill['bot_assistant_max_fill_pct'] ?? 40) }}%</strong> мест одновременно.</li>
                                            <li>Минимум 2 места всегда остаются свободными для живых игроков.</li>
										</ul>
									</div>
								</div>
							</div>
						</div>
						<div class="ramka" data-show-if="allow_registration=1">
							<h2 class="-mt-05">Уведомления и видимость</h2>		
							<div class="row">
								
								{{-- ✅ Notifications + participants visibility --}}
								
								
								@php
								$remMin = (int) old('remind_registration_minutes_before', $prefill['remind_registration_minutes_before'] ?? 600);
								if ($remMin < 0) $remMin = 600;
								$showParts = (bool) old('show_participants', $prefill['show_participants'] ?? true);
								@endphp
								
								<div class="col-md-4">
                                    <div class="card">
                                        <label>Напоминание игроку о записи</label>
                                
                                        <label class="checkbox-item">
                                            <input type="hidden" name="remind_registration_enabled" value="0">
                                            <input checked type="checkbox" name="remind_registration_enabled" value="1" id="remind_registration_enabled">
                                            <div class="custom-checkbox"></div>
                                            <span>Включено</span>
                                        </label>
                                
                                        <div class="mt-2">
                                            <label>За сколько до начала</label>
                                            <div class="row row2">
                                                <div class="col-6">
                                                    <label>Часы</label>
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
                                                    <label>Минуты</label>
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
                                                <li>Пример: 10 часов 0 минут = напоминание за 10 часов до начала.</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        const hoursInput   = document.getElementById('remind_hours_input');
                                        const minutesInput = document.getElementById('remind_minutes_input');
                                        const hidden       = document.getElementById('remind_registration_minutes_before');
                                
                                        function syncRemind() {
                                            const h = Math.max(0, parseInt(hoursInput.value || 0, 10));
                                            const m = Math.max(0, Math.min(59, parseInt(minutesInput.value || 0, 10)));
                                            hidden.value = h * 60 + m;
                                        }
                                
                                        hoursInput.addEventListener('input', syncRemind);
                                        minutesInput.addEventListener('input', syncRemind);
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
                                        <label>Анонс в каналы</label>
										
                                        <ul class="list f-16 mb-2">
                                            <li>При открытии регистрации сообщение отправится в выбранные каналы</li>
                                            <li>Для повторяющихся мероприятий анонс будет отправляться для каждой новой даты</li>
										</ul>
										
                                        @if($userChannels->isEmpty())
										<div class="f-16">
											Нет подключенных каналов —
											<a href="{{ route('profile.notification_channels') }}" class="link">
												подключить
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
													{{ strtoupper($channel->platform) }} — {{ $channel->title ?: 'Без названия' }}
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
												<span>Тихое обновление</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_update_message" value="0">
												<input type="checkbox" name="channel_update_message" value="1" @checked($channelUpdateMessage)>
												<div class="custom-checkbox"></div>
												<span>Обновлять сообщение</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_image" value="0">
												<input type="checkbox" name="channel_include_image" value="1" @checked($channelIncludeImage)>
												<div class="custom-checkbox"></div>
												<span>Добавлять картинку</span>
											</label>
											
											<label class="checkbox-item">
												<input type="hidden" name="channel_include_registered" value="0">
												<input type="checkbox" name="channel_include_registered" value="1" @checked($channelIncludeRegistered)>
												<div class="custom-checkbox"></div>
												<span>Показывать список игроков</span>
											</label>
										</div>
                                        @endif
									</div>
								</div>
								<div class="col-md-4">
									<div class="card">
										<label>Показывать список записавшихся</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="1" @checked($showParts)>
											<div class="custom-radio"></div>
											<span>Да</span>
										</label>
										<label class="radio-item">
											<input type="radio" name="show_participants" value="0" @checked(!$showParts)>
											<div class="custom-radio"></div>
											<span>Нет</span>
										</label>
										
										<ul class="list f-16 mt-1">
											<li>Если “Нет” — на странице события список участников не показываем.</li>
										</ul>											
										
									</div>
								</div>
							</div>
						</div>
						<div class="ramka" style="z-index:6">
							<h2 class="-mt-05">Фото и описание</h2>		
							
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
											<label>Фотографии для мероприятия</label>
											
											
											
											
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
												<span>Выбрать</span>
											</label>    
											<div class="photo-order-badge f-16 b-600 cd"></div>
										</div>
									</div>
									@endforeach
								</div>
								<div class="swiper-pagination"></div>
							</div>
							
							<ul class="list f-16 mt-1">
								<li>Выберите фото для мероприятия. Первое отмеченное фото будет главным.</li>
								<li>Фотографии можно добавить (с галочкой "Для мероприятий") в разделе <a target="_blank" href="{{ route('user.photos') }}">Ваши фотографии</a></li>
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
										badge.textContent = order === 1 ? '★ Главное' : `Фото: ${order}`;
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
							<p>У вас нет фото для мероприятий.</p> 
							<p>Фотографии можно добавить (с галочкой "Для мероприятий") в разделе <a target="_blank" href="{{ route('user.photos') }}">Ваши фотографии</a></p>
						</div>

					@endif
					
					
					
				
			</div>
			
								{{-- STEP 3: Описание мероприятия --}}
								<div class="col-md-8">
									<div class="card">
										<label>Описание мероприятия</label>
										
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
			Назад
		</button>
		<button type="submit" class="btn">Создать</button>
	</div>							
