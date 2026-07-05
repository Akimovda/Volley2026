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
						var indoorLabel = namesWrap.getAttribute('data-indoor-label');

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
										'<input type="hidden" name="directions[' + directionKey + '][court_indoor][' + (i - 1) + ']" value="0">' +
										'<label class="d-flex fvc gap-1 mt-1 f-14">' +
										'<input type="checkbox" name="directions[' + directionKey + '][court_indoor][' + (i - 1) + ']" value="1">' +
										indoorLabel +
										'</label>' +
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

			// --- Стоимость аренды (правила ценообразования) ---
			(function () {
				var form = document.getElementById('priceRulesForm');
				if (!form) return;

				document.querySelectorAll('.price-rules-rows').forEach(function (wrap) {
					var directionKey = wrap.getAttribute('data-direction');
					var courts = JSON.parse(wrap.getAttribute('data-courts') || '[]');
					var days = JSON.parse(wrap.getAttribute('data-days') || '{}');
					var existing = JSON.parse(wrap.getAttribute('data-existing') || '[]');
					var courtLabel = wrap.getAttribute('data-court-label');
					var allCourtsLabel = wrap.getAttribute('data-all-courts-label');
					var indoorLabel = wrap.getAttribute('data-indoor-label');
					var outdoorLabel = wrap.getAttribute('data-outdoor-label');
					var dayLabel = wrap.getAttribute('data-day-label');
					var allDaysLabel = wrap.getAttribute('data-all-days-label');
					var weekdaysLabel = wrap.getAttribute('data-weekdays-label');
					var weekendLabel = wrap.getAttribute('data-weekend-label');
					var timeLabel = wrap.getAttribute('data-time-label');
					var priceLabel = wrap.getAttribute('data-price-label');
					var removeLabel = wrap.getAttribute('data-remove-label');

					function addRow(data) {
						data = data || {};
						var row = document.createElement('div');
						row.className = 'card mb-1 price-rule-row';

						var courtSelect = document.createElement('select');
						courtSelect.className = 'rule-court-select';
						var allOpt = document.createElement('option');
						allOpt.value = '';
						allOpt.textContent = allCourtsLabel;
						courtSelect.appendChild(allOpt);
						var indoorGroup = document.createElement('optgroup');
						indoorGroup.label = indoorLabel;
						var outdoorGroup = document.createElement('optgroup');
						outdoorGroup.label = outdoorLabel;
						courts.forEach(function (c) {
							var opt = document.createElement('option');
							opt.value = String(c.id);
							opt.textContent = c.name;
							(c.is_indoor ? indoorGroup : outdoorGroup).appendChild(opt);
						});
						if (indoorGroup.children.length) courtSelect.appendChild(indoorGroup);
						if (outdoorGroup.children.length) courtSelect.appendChild(outdoorGroup);
						if (data.court_id) courtSelect.value = String(data.court_id);

						var daySelect = document.createElement('select');
						daySelect.className = 'rule-day-select';
						var allDaysOpt = document.createElement('option');
						allDaysOpt.value = '';
						allDaysOpt.textContent = allDaysLabel;
						daySelect.appendChild(allDaysOpt);
						Object.keys(days).forEach(function (num) {
							var opt = document.createElement('option');
							opt.value = num;
							opt.textContent = days[num];
							daySelect.appendChild(opt);
						});
						var weekdaysOpt = document.createElement('option');
						weekdaysOpt.value = 'weekdays';
						weekdaysOpt.textContent = weekdaysLabel;
						daySelect.appendChild(weekdaysOpt);
						var weekendOpt = document.createElement('option');
						weekendOpt.value = 'weekend';
						weekendOpt.textContent = weekendLabel;
						daySelect.appendChild(weekendOpt);
						if (data.day_of_week !== undefined && data.day_of_week !== null) daySelect.value = String(data.day_of_week);

						var startsInput = document.createElement('input');
						startsInput.type = 'time';
						startsInput.className = 'rule-starts-at';
						if (data.starts_at) startsInput.value = data.starts_at;

						var endsInput = document.createElement('input');
						endsInput.type = 'time';
						endsInput.className = 'rule-ends-at';
						if (data.ends_at) endsInput.value = data.ends_at;

						var priceInput = document.createElement('input');
						priceInput.type = 'number';
						priceInput.min = '1';
						priceInput.step = '0.01';
						priceInput.className = 'rule-price';
						priceInput.placeholder = priceLabel;
						if (data.price) priceInput.value = data.price;

						var removeBtn = document.createElement('button');
						removeBtn.type = 'button';
						removeBtn.className = 'btn btn-small btn-danger rule-remove-btn';
						removeBtn.textContent = removeLabel;
						removeBtn.addEventListener('click', function () { row.remove(); });

						function col(labelText, el, widthClass) {
							var c = document.createElement('div');
							c.className = widthClass;
							var l = document.createElement('label');
							l.className = 'f-13';
							l.textContent = labelText;
							c.appendChild(l);
							c.appendChild(el);
							return c;
						}

						var rowInner = document.createElement('div');
						rowInner.className = 'row';
						rowInner.appendChild(col(courtLabel, courtSelect, 'col-md-3'));
						rowInner.appendChild(col(dayLabel, daySelect, 'col-md-3'));
						var timeWrap = document.createElement('div');
						timeWrap.className = 'col-md-3';
						var timeL = document.createElement('label');
						timeL.className = 'f-13';
						timeL.textContent = timeLabel;
						timeWrap.appendChild(timeL);
						var timeRow = document.createElement('div');
						timeRow.className = 'd-flex gap-1 fvc';
						timeRow.appendChild(startsInput);
						timeRow.appendChild(document.createTextNode('—'));
						timeRow.appendChild(endsInput);
						timeWrap.appendChild(timeRow);
						rowInner.appendChild(timeWrap);
						rowInner.appendChild(col(priceLabel, priceInput, 'col-md-2'));
						var btnWrap = document.createElement('div');
						btnWrap.className = 'col-md-1 d-flex fvc';
						btnWrap.style.alignItems = 'flex-end';
						btnWrap.appendChild(removeBtn);
						rowInner.appendChild(btnWrap);

						row.appendChild(rowInner);
						wrap.appendChild(row);

						// Обычный createElement('select') не превращается в кастомный дропдаун
						// сам по себе — глобальный CSS безусловно прячет ЛЮБОЙ .form select
						// (@media (hover:hover) { .form select { clip: rect(0,0,0,0); ... } }),
						// рассчитывая на то, что рядом появится обёртка createCustomSelect().
						// Без явного вызова здесь селекты корта/дней были бы схлопнуты в 1px.
						if (window.jQuery && typeof window.createCustomSelect === 'function') {
							[courtSelect, daySelect].forEach(function (sel) {
								window.createCustomSelect(jQuery(sel));
								jQuery(sel).data('custom-initialized', true);
							});
						}
					}

					existing.forEach(function (rule) { addRow(rule); });

					var addBtn = document.querySelector('.price-add-rule-btn[data-direction="' + directionKey + '"]');
					if (addBtn) addBtn.addEventListener('click', function () { addRow(); });
				});

				// Перед сабмитом: разворачиваем пресеты Пн-Пт/Сб-Вс в отдельные правила
				// (по одному на день) и строим индексированные hidden-поля для каждого
				// направления — видимые контролы строк не имеют name и сами не отправляются.
				form.addEventListener('submit', function () {
					form.querySelectorAll('.price-rules-hidden-generated').forEach(function (el) { el.remove(); });

					document.querySelectorAll('.price-rules-rows').forEach(function (wrap) {
						var directionKey = wrap.getAttribute('data-direction');
						var idx = 0;

						wrap.querySelectorAll('.price-rule-row').forEach(function (row) {
							var price = row.querySelector('.rule-price').value;
							if (!price) return;

							var courtId = row.querySelector('.rule-court-select').value;
							var dayVal = row.querySelector('.rule-day-select').value;
							var startsAt = row.querySelector('.rule-starts-at').value;
							var endsAt = row.querySelector('.rule-ends-at').value;

							var dayList;
							if (dayVal === 'weekdays') dayList = ['0', '1', '2', '3', '4'];
							else if (dayVal === 'weekend') dayList = ['5', '6'];
							else dayList = [dayVal]; // '' (все) или конкретный день

							dayList.forEach(function (dayOfWeek) {
								var fields = { court_id: courtId, day_of_week: dayOfWeek, starts_at: startsAt, ends_at: endsAt, price: price };
								Object.keys(fields).forEach(function (field) {
									var val = fields[field];
									if (val === '' || val === null || val === undefined) return; // пусто = не отправляем (null на сервере)
									var hidden = document.createElement('input');
									hidden.type = 'hidden';
									hidden.className = 'price-rules-hidden-generated';
									hidden.name = 'directions[' + directionKey + '][rules][' + idx + '][' + field + ']';
									hidden.value = val;
									form.appendChild(hidden);
								});
								idx++;
							});
						});
					});
				});
			})();

			(function () {
				var input   = document.getElementById('trust-ac-input');
				var dd      = document.getElementById('trust-ac-dd');
				var hidden  = document.getElementById('trust-ac-organizer-id');
				var submitBtn = document.getElementById('trust-submit-btn');
				var timer   = null;

				if (!input) return;

				function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }
				function showDd() { dd.classList.add('form-select-dropdown--active'); }
				function hideDd() { dd.classList.remove('form-select-dropdown--active'); }

				function pick(id, label) {
					hidden.value = id;
					input.value = label;
					submitBtn.disabled = false;
					hideDd();
					dd.innerHTML = '';
				}

				function render(items) {
					dd.innerHTML = '';
					if (!items.length) {
						dd.innerHTML = '<div class="city-message">{{ __('ui.not_found') }}</div>';
						showDd();
						return;
					}
					items.forEach(function (item) {
						var div = document.createElement('div');
						div.className = 'trainer-item form-select-option';
						div.innerHTML = '<div class="text-sm text-gray-900">' + esc(item.label || item.name) + '</div>';
						div.addEventListener('click', function () { pick(item.id, item.label || item.name); });
						dd.appendChild(div);
					});
					showDd();
				}

				input.addEventListener('input', function () {
					clearTimeout(timer);
					hidden.value = '';
					submitBtn.disabled = true;
					var q = input.value.trim();
					if (q.length < 2) { hideDd(); return; }

					timer = setTimeout(function () {
						fetch('/ajax/users/search?q=' + encodeURIComponent(q), {
							headers: { 'Accept': 'application/json' },
							credentials: 'same-origin'
						})
						.then(function (r) { return r.json(); })
						.then(function (data) { render(data.items || []); })
						.catch(function () { hideDd(); });
					}, 250);
				});

				document.addEventListener('click', function (e) {
					var wrap = document.getElementById('trust-ac-wrap');
					if (wrap && !wrap.contains(e.target)) hideDd();
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
                $courtIndoor = $dir ? $dir->courts->pluck('is_indoor')->values()->all() : [];
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
                             data-name-label="{{ __('club.court_name_label') }}"
                             data-indoor-label="{{ __('club.court_is_indoor') }}">
                            @for($i = 1; $i <= (int) $courtsCount; $i++)
                            <div class="col-md-4 court-name-item">
                                <div class="card">
                                    <label>{{ __('club.court_name_label') }} {{ $i }}</label>
                                    <input type="text" name="directions[{{ $directionKey }}][court_names][]"
                                           value="{{ old('directions.' . $directionKey . '.court_names.' . ($i - 1), $courtNames[$i - 1] ?? __('club.court_default_name_' . $directionKey, ['n' => $i])) }}"
                                           maxlength="100">
                                    <input type="hidden" name="directions[{{ $directionKey }}][court_indoor][{{ $i - 1 }}]" value="0">
                                    <label class="d-flex fvc gap-1 mt-1 f-14">
                                        <input type="checkbox" name="directions[{{ $directionKey }}][court_indoor][{{ $i - 1 }}]" value="1"
                                               @checked(old('directions.' . $directionKey . '.court_indoor.' . ($i - 1), $courtIndoor[$i - 1] ?? false))>
                                        {{ __('club.court_is_indoor') }}
                                    </label>
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

        {{-- СТОИМОСТЬ АРЕНДЫ --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('club.rental_pricing_title') }}</h2>
            <form method="POST" action="{{ route('admin.locations.price_rules.save', $location) }}" class="form" id="priceRulesForm">
                @csrf

                @foreach($directionMeta as $directionKey => $meta)
                @php
                $dir = $directions->get($directionKey);
                if (!$dir || !$dir->is_active) { continue; }
                $dirRules = $priceRules->get($dir->id, collect());
                $baseRule = $dirRules->first(fn ($r) => $r->court_id === null && $r->day_of_week === null && $r->starts_at === null);
                $customRules = $dirRules->reject(fn ($r) => $baseRule && $r->id === $baseRule->id)->values();
                $existingRulesJson = $customRules->map(fn ($r) => [
                    'court_id'    => $r->court_id,
                    'day_of_week' => $r->day_of_week,
                    'starts_at'   => $r->starts_at ? substr($r->starts_at, 0, 5) : null,
                    'ends_at'     => $r->ends_at ? substr($r->ends_at, 0, 5) : null,
                    'price'       => (float) $r->price_per_hour,
                ])->values();
                $courtsJson = $dir->courts->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'is_indoor' => (bool) $c->is_indoor])->values();
                @endphp
                <div class="card mb-2 price-direction-block" data-direction="{{ $directionKey }}">
                    <div class="b-700 f-18 mb-2">{{ $meta['label'] }}</div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label>{{ __('club.base_price') }}</label>
                                <input type="number" name="directions[{{ $directionKey }}][base_price]" min="1" step="0.01"
                                       value="{{ old('directions.' . $directionKey . '.base_price', $baseRule->price_per_hour ?? '') }}"
                                       placeholder="{{ __('club.base_price_placeholder') }}">
                                <ul class="list f-14 mt-1"><li>{{ __('club.base_price_hint') }}</li></ul>
                            </div>
                        </div>
                    </div>

                    <div class="b-600 mt-2 mb-1">{{ __('club.price_rules_label') }}</div>
                    <div class="price-rules-rows"
                         id="priceRules_{{ $directionKey }}"
                         data-direction="{{ $directionKey }}"
                         data-existing="{{ $existingRulesJson->toJson() }}"
                         data-courts="{{ $courtsJson->toJson() }}"
                         data-court-label="{{ __('club.court_label') }}"
                         data-all-courts-label="{{ __('club.all_courts') }}"
                         data-indoor-label="{{ __('club.indoor') }}"
                         data-outdoor-label="{{ __('club.outdoor') }}"
                         data-day-label="{{ __('club.day_label') }}"
                         data-all-days-label="{{ __('club.all_days') }}"
                         data-weekdays-label="{{ __('club.weekdays') }}"
                         data-weekend-label="{{ __('club.weekend') }}"
                         data-time-label="{{ __('club.time_label') }}"
                         data-price-label="{{ __('club.price_label') }}"
                         data-remove-label="{{ __('club.remove_rule') }}"
                         data-days="{{ json_encode(__('club.days')) }}"
                    ></div>
                    <button type="button" class="btn btn-small btn-secondary mt-1 price-add-rule-btn" data-direction="{{ $directionKey }}">{{ __('club.add_rule') }}</button>
                </div>
                @endforeach

                <div class="mt-2 text-center">
                    <button type="submit" class="btn btn-primary">{{ __('club.save_price_rules') }}</button>
                </div>
            </form>
        </div>

        {{-- ДОВЕРЕННЫЕ ОРГАНИЗАТОРЫ --}}
        <div class="ramka">
            <h2 class="-mt-05">{{ __('club.trust_title') }}</h2>
            <div class="f-14 cd mb-2">{{ __('club.trust_hint') }}</div>

            <form method="POST" action="{{ route('admin.locations.trust.save', $location) }}" class="form d-flex gap-1 fvc" style="flex-wrap:wrap">
                @csrf
                <div style="position:relative;flex:1;min-width:14rem" id="trust-ac-wrap">
                    <input type="text" id="trust-ac-input" autocomplete="off" class="form-control"
                        placeholder="{{ __('club.trust_search_placeholder') }}">
                    <div id="trust-ac-dd" class="form-select-dropdown trainer_dd"></div>
                    <input type="hidden" name="organizer_id" id="trust-ac-organizer-id">
                </div>
                <select name="trust_level" id="trust-level-select" style="min-width:12rem">
                    <option value="{{ \App\Models\ClubOrganizerTrust::LEVEL_PREPAID_ONLY }}">{{ __('club.trust_level_prepaid_only') }}</option>
                    <option value="{{ \App\Models\ClubOrganizerTrust::LEVEL_ALLOW_ON_SITE }}">{{ __('club.trust_level_allow_on_site') }}</option>
                    <option value="{{ \App\Models\ClubOrganizerTrust::LEVEL_TRUSTED }}">{{ __('club.trust_level_trusted') }}</option>
                </select>
                <button type="submit" class="btn btn-primary" id="trust-submit-btn" disabled>{{ __('club.trust_add_btn') }}</button>
            </form>
            @error('organizer_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror

            <div class="mt-2">
                @forelse($trustedOrganizers as $trust)
                <div class="card mb-1 d-flex between fvc" style="flex-wrap:wrap;gap:8px">
                    <div class="b-600">
                        {{ trim(($trust->organizer->last_name ?? '') . ' ' . ($trust->organizer->first_name ?? '')) ?: ($trust->organizer->name ?? '#' . $trust->organizer_id) }}
                        <span class="f-13 cd">— {{ __('club.trust_level_' . $trust->trust_level) }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.locations.trust.destroy', [$location, $trust]) }}"
                        onsubmit="return confirm({!! json_encode(__('club.trust_confirm_remove')) !!})">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-small btn-danger">{{ __('club.trust_remove_btn') }}</button>
                    </form>
                </div>
                @empty
                <div class="f-14 cd">{{ __('club.trust_empty') }}</div>
                @endforelse
            </div>
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