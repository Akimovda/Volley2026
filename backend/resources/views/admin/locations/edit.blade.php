{{-- resources/views/admin/locations/edit.blade.php --}}
<x-voll-layout body_class="admin-page admin-locations-edit">
    <x-slot name="title">
        {{ __('admin.loc_edit_title') }}
	</x-slot>
	
    <x-slot name="description">
        {{ __('admin.loc_edit_title') }}
	</x-slot>
	
    <x-slot name="canonical">
        {{-- Здесь каноническая ссылка не нужна --}}
	</x-slot>
	
    <x-slot name="h1">
        {{ __('admin.loc_edit_title') }}
	</x-slot>
	
    <x-slot name="h2">
        {{ __('admin.breadcrumb_dashboard') }}
	</x-slot>
	
    <x-slot name="t_description">
        {{ __('admin.loc_edit_t_description') }}
	</x-slot>
	
    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.dashboard') }}" itemprop="item"><span itemprop="name">{{ __('admin.breadcrumb_dashboard') }}</span></a>
            <meta itemprop="position" content="1">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('admin.locations.index') }}" itemprop="item"><span itemprop="name">{{ __('admin.loc_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
		</li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ $location->name }}</span>
            <meta itemprop="position" content="3">
		</li>
	</x-slot>
	
    <x-slot name="style">
    <link rel="stylesheet" type="text/css" href="@asset_v('assets/trix.css')">
        <link href="/assets/org.css" rel="stylesheet">
	</x-slot>	
	
    <x-slot name="script">
    <script src="@asset_v('assets/trix.js')"></script>
		<script src="/assets/city.js"></script>  
		<script src="/assets/org.js?v=2"></script>     
        <script>
            (function () {
                // --- trix: запрет вложений
                document.addEventListener('trix-file-accept', function (event) {
                    event.preventDefault();
				});

                document.addEventListener('trix-paste', function(e) {
                    var paste = e.paste;
                    if (paste.html) {
                        var clean = paste.html.replace(/<(?!\/?(br|p|b|i|u|strong|em|a |ul|ol|li))[^>]+>/gi, '');
                        e.preventDefault();
                        e.target.editor.insertHTML(clean);
                    }
                });
				
                // --- photos reorder
                const grid = document.getElementById('photos_grid');
                if (grid) {
                    const hint = document.getElementById('photos_hint');
                    const saveBtn = document.getElementById('photos_save_btn');
                    const reorderUrl = @json(route('admin.locations.photos.reorder', $location));
                    const csrf = @json(csrf_token());
					
                    function currentOrderIds() {
                        return Array.from(grid.querySelectorAll('[data-photo-id]'))
						.map(el => Number(el.getAttribute('data-photo-id')))
						.filter(n => Number.isFinite(n));
					}
					
                    async function saveOrder() {
                        const photo_ids = currentOrderIds();
                        if (!photo_ids.length) return;
						
                        if (hint) hint.textContent = @json(__('admin.loc_photos_save_progress'));
                        try {
                            const res = await fetch(reorderUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
								},
                                body: JSON.stringify({ photo_ids }),
							});
							
                            if (!res.ok) {
                                if (hint) hint.textContent = @json(__('admin.loc_photos_save_http_err')).replace(':status', res.status);
                                return;
							}
                            if (hint) hint.textContent = @json(__('admin.loc_photos_save_done'));
							} catch (e) {
                            if (hint) hint.textContent = @json(__('admin.loc_photos_save_net_err'));
						}
					}
					
                    new Sortable(grid, { animation: 150, ghostClass: 'opacity-50' });
                    saveBtn?.addEventListener('click', saveOrder);
				}
			})();

			// --- Направления и корты
			(function () {
				document.querySelectorAll('.direction-block').forEach(function (block) {
					var toggle = block.querySelector('.direction-toggle');
					var fields = block.querySelector('.direction-fields');
					if (toggle && fields) {
						toggle.addEventListener('change', function () {
							fields.style.display = toggle.checked ? '' : 'none';
						});
					}

					var countSelect = block.querySelector('.courts-count-select');
					var namesWrap = block.querySelector('.court-names-wrap');
					if (countSelect && namesWrap) {
						var directionKey = block.getAttribute('data-direction');
						var defaultLabelTpl = namesWrap.getAttribute('data-default-label');
						var nameLabel = namesWrap.getAttribute('data-name-label');

						countSelect.addEventListener('change', function () {
							var target = parseInt(countSelect.value, 10) || 1;
							var current = namesWrap.querySelectorAll('.court-name-item').length;

							if (target > current) {
								for (var i = current + 1; i <= target; i++) {
									var col = document.createElement('div');
									col.className = 'col-md-4 court-name-item';
									var defaultName = defaultLabelTpl.replace('__N__', i);
									col.innerHTML = '<div class="card">' +
										'<label>' + nameLabel + ' ' + i + '</label>' +
										'<input type="text" name="directions[' + directionKey + '][court_names][]" value="' + defaultName + '" maxlength="100">' +
										'</div>';
									namesWrap.appendChild(col);
								}
							} else if (target < current) {
								var items = namesWrap.querySelectorAll('.court-name-item');
								for (var j = items.length - 1; j >= target; j--) {
									items[j].remove();
								}
							}
						});
					}

					block.querySelectorAll('.day-off-toggle').forEach(function (dayOff) {
						var row = dayOff.closest('tr');
						var timeInputs = row ? row.querySelectorAll('input[type="time"]') : [];
						dayOff.addEventListener('change', function () {
							timeInputs.forEach(function (inp) { inp.disabled = dayOff.checked; });
						});
					});
				});
			})();
		</script>
	</x-slot>
	
	
	
    <div class="container">
		
        @if (session('status'))
        <div class="ramka">
            <div class="alert alert-success">
                {{ session('status') }}
			</div>
		</div>
        @endif
		
        @if (session('error'))
        <div class="ramka">
            <div class="alert alert-danger">
                {{ session('error') }}
			</div>
		</div>
        @endif
		
        @if ($errors->any())
        <div class="ramka">
            <div class="alert alert-danger">
                <div class="font-semibold mb-2">{{ __('admin.errors_title') }}</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                    @endforeach
				</ul>
			</div>
		</div>
        @endif
		
		
		@php
		// Конфигурация отображения города (все true)
		$cityDisplayConfig = [
        'showCountry' => true,
        'showRegion' => true,
        'inputShowCountry' => true,
        'inputShowRegion' => true,
		];
		
		// Подготавливаем метку для города
		$selectedCityId = old('city_id', $location->city_id);
		$selectedCityLabel = '';
		
		// Формируем метку для инпута: Город (Страна, Регион)
		if (!empty($selectedCityId) && ($location->city ?? null)) {
        $city = $location->city;
        $cityName = $city->name ?? '';
        $details = [];
		
        if ($cityDisplayConfig['inputShowCountry'] && !empty($city->country_code)) {
		$details[] = $city->country_code;
        }
        if ($cityDisplayConfig['inputShowRegion'] && !empty($city->region)) {
		$details[] = $city->region;
        }
		
        if (!empty($details)) {
		$selectedCityLabel = $cityName . ' (' . implode(', ', $details) . ')';
        } else {
		$selectedCityLabel = $cityName;
        }
		}
		@endphp
		
		
		
        <div class="ramka">
		<h2 class="-mt-05">{{ __('admin.loc_form_title') }}</h2>
            <form method="POST" action="{{ route('admin.locations.update', $location) }}" enctype="multipart/form-data" class="form">
                @csrf
                @method('PUT')
				
                <div class="row">
                    {{-- NAME --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_name') }} <span class="text-danger">*</span></label>
                            <input
							type="text"
							name="name"
							class="@error('name') is-invalid @enderror"
							value="{{ old('name', $location->name) }}"
							required
                            >
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- CITY --}}
					<div class="col-md-4">
						<div class="card">
							<label>{{ __('admin.loc_label_city') }} *</label>
							
							{{-- То, что реально сохраняем --}}
							<input type="hidden" name="city_id" id="city_id" value="{{ old('city_id', $location->city_id) }}" required>
							
							{{-- UI input (поиск) --}}
							<div class="city-autocomplete" id="city-autocomplete" data-search-url="{{ route('cities.search') }}">
								<input type="text"
								id="city_search"
								placeholder="{{ __('admin.loc_search_city_ph') }}"
								value="{{ old('city_label', $selectedCityLabel) }}"
								autocomplete="off"
								@error('city_id') class="error" @enderror>
								
								{{-- dropdown --}}
								<div id="city_dropdown" class="city-dropdown">
									<div id="city_results"></div>
								</div>
							</div>
							@error('city_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- ADDRESS --}}
                    <div class="col-md-8">
                        <div class="card">
                            <label>{{ __('admin.loc_label_address') }}</label>
                            <input
							type="text"
							name="address"
							class="@error('address') is-invalid @enderror"
							value="{{ old('address', $location->address) }}"
                            >
                            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- SHORT_TEXT --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_short_text') }}</label>
                            <input
							type="text"
							name="short_text"
							class="@error('short_text') is-invalid @enderror"
							value="{{ old('short_text', $location->short_text) }}"
                            >
                            @error('short_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- LONG_TEXT (Trix) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_short_text_preview') }}</label>
                            <input id="long_text" type="hidden" name="long_text" value="{{ old('long_text', $location->long_text) }}">
                            <trix-editor
							input="long_text"
							class="trix-content"
                            ></trix-editor>
                            @error('long_text')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- LONG_TEXT_FULL (Trix) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_long_text') }}</label>
                            <input id="long_text_full" type="hidden" name="long_text_full" value="{{ old('long_text_full', $location->long_text_full) }}">
                            <trix-editor
							input="long_text_full"
							class="trix-content"
                            ></trix-editor>
                            @error('long_text_full')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- COORDS --}}
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('admin.loc_label_lat') }} (lat)</label>
                            <input
							type="number"
							name="lat"
							step="any"
							class="@error('lat') is-invalid @enderror"
							value="{{ old('lat', $location->lat) }}"
                            >
                            @error('lat')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('admin.loc_label_lng') }} (lng)</label>
                            <input
							type="number"
							name="lng"
							step="any"
							class="@error('lng') is-invalid @enderror"
							value="{{ old('lng', $location->lng) }}"
                            >
                            @error('lng')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- PHOTOS (новые) --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_photos_new_5') }}</label>
                            <input
							id="loc_photos"
							type="file"
							name="photos[]"
							multiple
							accept="image/*"
							class="@error('photos') is-invalid @enderror"
                            >
                            <div class="f-16 b-500 mt-1">
                                {{ __('admin.loc_photos_hint') }}
							</div>
                            @error('photos')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            @error('photos.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
					
                    {{-- NOTE --}}
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('admin.loc_label_note') }}</label>
                            <input
							type="text"
							name="note"
							class="@error('note') is-invalid @enderror"
							value="{{ old('note', $location->note) }}"
                            >
                            @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
						</div>
					</div>

                    {{-- OWNER (только для админа) --}}
                    @if(auth()->user()?->isAdmin())
                    <div class="col-12">
                        <div class="card">
                            <label>{{ __('club.location_owner') }}</label>
                            <select name="owner_id">
                                <option value="">{{ __('club.no_owner_option') }}</option>
                                @foreach($clubManagers as $manager)
                                <option value="{{ $manager->id }}" @selected((int) old('owner_id', $location->owner_id) === $manager->id)>
                                    {{ trim($manager->last_name . ' ' . $manager->first_name) ?: $manager->name }}
                                </option>
                                @endforeach
                            </select>
                            @error('owner_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
						</div>
					</div>
                    @endif
				</div>
				
				<div class="mt-2 text-center">
					<button type="submit" class="btn btn-primary">{{ __('admin.btn_save_changes') }}</button>
				</div>
			</form>
			{{-- 
			<div class="row">
				<div class="col-6 text-end">
					<div class="mt-3">
						<form method="POST"
						action="{{ route('admin.locations.destroy', $location) }}"
						onsubmit="return confirm({!! json_encode(__('admin.loc_confirm_delete_full')) !!})"
						style="display: inline;">
							@csrf
							@method('DELETE')
							<button class="btn btn-danger" type="submit">{{ __('admin.loc_btn_delete_loc') }}</button>
						</form>
					</div>
				</div>			
			</div>
			--}}
		</div>

        {{-- НАПРАВЛЕНИЯ И КОРТЫ --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('club.directions_title') }}</h2>
            <form method="POST" action="{{ route('admin.locations.directions.save', $location) }}" class="form" id="directionsForm">
                @csrf

                @php
                $directionMeta = [
                    'classic' => ['label' => __('club.direction_classic'), 'countLabel' => __('club.courts_count_classic')],
                    'beach'   => ['label' => __('club.direction_beach'), 'countLabel' => __('club.courts_count_beach')],
                ];
                @endphp

                @foreach($directionMeta as $directionKey => $meta)
                @php
                $dir = $directions->get($directionKey);
                $isEnabled = old("directions.$directionKey.enabled", $dir?->is_active ? '1' : '') ? true : false;
                $courtsCount = old("directions.$directionKey.courts_count", $dir->courts_count ?? 1);
                $courtNames = $dir ? $dir->courts->pluck('name')->values()->all() : [];
                $hoursByDay = $dir ? $dir->workingHours->keyBy('day_of_week') : collect();
                @endphp
                <div class="card mb-2 direction-block" data-direction="{{ $directionKey }}">
                    <label class="d-flex fvc gap-1 mb-2">
                        <input type="checkbox" class="direction-toggle" name="directions[{{ $directionKey }}][enabled]" value="1" @checked($isEnabled)>
                        <span class="b-700 f-18">{{ $meta['label'] }}</span>
                    </label>

                    <div class="direction-fields" style="{{ $isEnabled ? '' : 'display:none' }}">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card">
                                    <label>{{ $meta['countLabel'] }}</label>
                                    <select name="directions[{{ $directionKey }}][courts_count]" class="courts-count-select">
                                        @for($n = 1; $n <= 20; $n++)
                                        <option value="{{ $n }}" @selected((int) $courtsCount === $n)>{{ $n }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="court-names-wrap row mt-1"
                             data-default-label="{{ __('club.court_default_name_' . $directionKey, ['n' => '__N__']) }}"
                             data-name-label="{{ __('club.court_name_label') }}">
                            @for($i = 1; $i <= (int) $courtsCount; $i++)
                            <div class="col-md-4 court-name-item">
                                <div class="card">
                                    <label>{{ __('club.court_name_label') }} {{ $i }}</label>
                                    <input type="text" name="directions[{{ $directionKey }}][court_names][]"
                                           value="{{ old('directions.' . $directionKey . '.court_names.' . ($i - 1), $courtNames[$i - 1] ?? __('club.court_default_name_' . $directionKey, ['n' => $i])) }}"
                                           maxlength="100">
                                </div>
                            </div>
                            @endfor
                        </div>

                        <div class="mt-2">
                            <div class="b-600 mb-1">{{ __('club.working_hours') }}</div>
                            <div class="table-scrollable">
                                <table class="table f-13">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left"></th>
                                            <th style="text-align:center">{{ __('club.opens_at') }}</th>
                                            <th style="text-align:center">{{ __('club.closes_at') }}</th>
                                            <th style="text-align:center">{{ __('club.day_off') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($day = 0; $day <= 6; $day++)
                                        @php
                                        $wh = $hoursByDay->get($day);
                                        $isDayOff = old("directions.$directionKey.hours.$day.is_day_off", $wh->is_day_off ?? false) ? true : false;
                                        $opensAt = old("directions.$directionKey.hours.$day.opens_at", $wh?->opens_at ? \Carbon\Carbon::parse($wh->opens_at)->format('H:i') : '08:00');
                                        $closesAt = old("directions.$directionKey.hours.$day.closes_at", $wh?->closes_at ? \Carbon\Carbon::parse($wh->closes_at)->format('H:i') : '23:00');
                                        @endphp
                                        <tr>
                                            <td>{{ __('club.days.' . $day) }}</td>
                                            <td style="text-align:center">
                                                <input type="time" name="directions[{{ $directionKey }}][hours][{{ $day }}][opens_at]" value="{{ $opensAt }}" @disabled($isDayOff)>
                                            </td>
                                            <td style="text-align:center">
                                                <input type="time" name="directions[{{ $directionKey }}][hours][{{ $day }}][closes_at]" value="{{ $closesAt }}" @disabled($isDayOff)>
                                            </td>
                                            <td style="text-align:center">
                                                <input type="checkbox" class="day-off-toggle" name="directions[{{ $directionKey }}][hours][{{ $day }}][is_day_off]" value="1" @checked($isDayOff)>
                                            </td>
                                        </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach

                <div class="mt-2 text-center">
                    <button type="submit" class="btn btn-primary">{{ __('club.save_directions') }}</button>
                </div>
            </form>
        </div>

        {{-- PHOTOS (D&D SORT) --}}
        @if(!$photos->isEmpty())
        <div class="ramka">
			<h2 class="-mt-05">{{ __('admin.loc_uploaded_section') }}</h2>
			<div class="f-16 b-500"> {{ __('admin.loc_drag_hint') }}</div>
			
			
			
			
			
			
            <div id="photos_grid" class="row mt-2">
                @foreach($photos as $m)
				<div class="col-md-3 col-6">
					<div class="card cursor-move"
					data-photo-id="{{ $m->id }}">
						@php
						$u = $m->getUrl('thumb');
						if (empty($u)) $u = $m->getUrl();
						@endphp
						<img src="{{ $u }}"
						class="w-full h-32 object-cover rounded-lg"
						alt="">
						<div class="mt-1 d-flex between fvc">
							<span class="b-600 cd">#{{ $m->id }}</span>
							<form method="POST"
							action="{{ route('admin.locations.photos.destroy', [$location, $m]) }}"
							onsubmit="return confirm({!! json_encode(__('admin.loc_confirm_delete_photo')) !!})">
								@csrf
								@method('DELETE')
								<button type="submit" 
								class="btn btn-small btn-danger btn-alert"
								data-title="{{ __('admin.loc_confirm_delete_photo') }}"
								data-icon="warning"
								data-confirm-text="{{ __('admin.btn_delete') }}"
								data-cancel-text="{{ __('admin.btn_cancel') }}">
									{{ __('admin.btn_delete') }}
								</button>								
								
							</form>
						</div>
					</div>
				</div>
                @endforeach
			</div>
			<div class="mt-2 text-center">
				<button type="button" id="photos_save_btn" class="btn">
					{{ __('admin.loc_photos_save_order') }}
				</button>
			</div>
			
		</div>
        @endif
	</div>
</x-voll-layout>