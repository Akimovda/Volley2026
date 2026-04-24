@php
    // ========== ДЛИТЕЛЬНОСТЬ ==========
    $durationSec = $occurrence->duration_sec ?: ($event->duration_sec ?: 7200);
    $durH = (int) floor($durationSec / 3600);
    $durM = (int) floor(($durationSec % 3600) / 60);

    // ========== РЕГИСТРАЦИЯ ==========
    $allowReg = !is_null($occurrence->allow_registration) ? (bool) $occurrence->allow_registration : (bool) ($event->allow_registration ?? false);
    $maxPlayers = $occurrence->max_players ?? $event->gameSettings?->max_players ?? 0;

    $regStartsDays = 3;
    $regEndsMin = 15;
    $cancelMin = 60;

    $occStarts = $occurrence->starts_at ? \Carbon\Carbon::parse($occurrence->starts_at) : null;

    if ($occStarts && $occurrence->registration_starts_at) {
        $regStartsDays = (int) \Carbon\Carbon::parse($occurrence->registration_starts_at)->diffInDays($occStarts);
    } elseif ($event->starts_at && $event->registration_starts_at) {
        $regStartsDays = (int) \Carbon\Carbon::parse($event->registration_starts_at)->diffInDays(\Carbon\Carbon::parse($event->starts_at));
    }

    if ($occStarts && $occurrence->registration_ends_at) {
        $regEndsMin = (int) \Carbon\Carbon::parse($occurrence->registration_ends_at)->diffInMinutes($occStarts);
    } elseif ($event->starts_at && $event->registration_ends_at) {
        $regEndsMin = (int) \Carbon\Carbon::parse($event->registration_ends_at)->diffInMinutes(\Carbon\Carbon::parse($event->starts_at));
    }

    if ($occStarts && $occurrence->cancel_self_until) {
        $cancelMin = (int) \Carbon\Carbon::parse($occurrence->cancel_self_until)->diffInMinutes($occStarts);
    } elseif ($event->starts_at && $event->cancel_self_until) {
        $cancelMin = (int) \Carbon\Carbon::parse($event->cancel_self_until)->diffInMinutes(\Carbon\Carbon::parse($event->starts_at));
    }

    $regEndsHours = (int) floor($regEndsMin / 60);
    $regEndsMins = $regEndsMin % 60;
    $cancelHours = (int) floor($cancelMin / 60);
    $cancelMins = $cancelMin % 60;

    $remEnabled = !is_null($occurrence->remind_registration_enabled) ? (bool) $occurrence->remind_registration_enabled : (bool) ($event->remind_registration_enabled ?? false);
    $remMin = (int) ($occurrence->remind_registration_minutes_before ?? $event->remind_registration_minutes_before ?? 600);
    $remH = (int) floor($remMin / 60);
    $remM = $remMin % 60;

    $showParts = !is_null($occurrence->show_participants) ? (bool) $occurrence->show_participants : (bool) ($event->show_participants ?? true);

    // ========== НАЗВАНИЕ И ОПИСАНИЕ ==========
    $titleVal = $occurrence->title ?? $event->title;
    $descVal  = $occurrence->description_html ?? $event->description_html ?? '';

    // ========== ОПЛАТА ==========
    $isPaid = !is_null($occurrence->is_paid) ? (bool) $occurrence->is_paid : (bool) ($event->is_paid ?? false);
    $priceMinor    = $occurrence->price_minor    ?? $event->price_minor    ?? null;
    $priceCurrency = $occurrence->price_currency ?? $event->price_currency ?? 'RUB';
    $priceText     = $occurrence->price_text     ?? $event->price_text     ?? '';
    $paymentMethod = $occurrence->payment_method ?? $event->payment_method ?? '';
    $paymentLink   = $occurrence->payment_link   ?? $event->payment_link   ?? '';

    $priceRub = $priceMinor ? ($priceMinor / 100) : '';

    // ========== ВОЗВРАТ ==========
    $refundFull    = $occurrence->refund_hours_full    ?? $event->refund_hours_full    ?? null;
    $refundPartial = $occurrence->refund_hours_partial ?? $event->refund_hours_partial ?? null;
    $refundPct     = $occurrence->refund_partial_pct   ?? $event->refund_partial_pct   ?? null;

    // ========== ТРЕНЕР ==========
    $trainerId = $occurrence->trainer_user_id ?? $event->trainer_user_id ?? null;
    $trainerName = '';
    if ($trainerId) {
        $trainerUser = \App\Models\User::find($trainerId);
        if ($trainerUser) {
            $trainerName = trim(($trainerUser->first_name ?? '') . ' ' . ($trainerUser->last_name ?? '')) ?: ($trainerUser->name ?? '');
        }
    }

    // ========== ПЕРСОНАЛЬНЫЕ ДАННЫЕ ==========
    $reqPersonal = !is_null($occurrence->requires_personal_data) ? (bool) $occurrence->requires_personal_data : (bool) ($event->requires_personal_data ?? false);

    // ========== ВОЗРАСТ ДЕТЕЙ ==========
    $agePolicy = $occurrence->age_policy ?? $event->age_policy ?? 'adult';
    $childMin  = $occurrence->child_age_min ?? $event->child_age_min ?? null;
    $childMax  = $occurrence->child_age_max ?? $event->child_age_max ?? null;

    // ========== ИГРОВАЯ СХЕМА (из effectiveGameSettings) ==========
    $subtypeVal = $gs->subtype ?? null;
    $teamsCountVal = $gs->teams_count ?? 2;
    $minPlayersVal = $gs->min_players ?? null;
    $genderPolicyVal = $gs->gender_policy ?? 'any';
    $genderLimitedSideVal = $gs->gender_limited_side ?? null;
    $genderLimitedMaxVal = $gs->gender_limited_max ?? null;
    $girlsMaxVal = $gs->girls_max ?? null;
    $allowGirlsVal = (bool) ($gs->allow_girls ?? false);
@endphp

<x-voll-layout body_class="occurrence-edit-page">
    <x-slot name="title">Редактирование даты #{{ $occurrence->id }}</x-slot>
    <x-slot name="h1">Редактирование даты</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">Управление</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.event_management.occurrences', $event) }}" itemprop="item">
                <span itemprop="name">{{ $event->title }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">#{{ $occurrence->id }}</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="h2">#{{ $occurrence->id }} · {{ $event->title }}</x-slot>
    <x-slot name="t_description">Изменения применяются только к этой дате. Поля предзаполнены значениями из серии — при сохранении они становятся override.</x-slot>

    <x-slot name="style">
    <link rel="stylesheet" type="text/css" href="/assets/trix.css?v={{ time() }}">
    </x-slot>

    <div class="container form">
        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
        <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        <form action="{{ route('events.occurrences.update', [$event, $occurrence]) }}" method="POST">
            @csrf @method('PUT')

            {{-- ============ ПЛАШКА: УНАСЛЕДОВАНО ОТ СЕРИИ ============ --}}
            <div class="ramka" style="opacity:.7">
                <h2 class="-mt-05">Серия мероприятия</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Направление</label>
                            <input type="text" value="{{ direction_name($event->direction) }}" disabled>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Тип мероприятия</label>
                            <input type="text" value="{{ format_name($event->format) }}" disabled>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Игровая схема</label>
                            <input type="text" value="{{ $subtypeVal ?? '—' }}" disabled>
                        </div>
                    </div>
                </div>
                <div class="f-13" style="margin-top:.5rem">Эти параметры задаются на уровне серии и не редактируются per-дату. <a href="{{ route('events.event_management.edit', $event) }}">Изменить в настройках серии →</a></div>
            </div>

            {{-- ============ НАЗВАНИЕ И ОПИСАНИЕ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Название и описание</h2>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <label>Название (для этой даты)</label>
                            <input type="text" name="title" maxlength="255" value="{{ old('title', $titleVal) }}">
                            @error('title') <div class="f-13" style="margin-top:4px">{{ $message }}</div> @enderror
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <label>Описание</label>
                            <input id="occ_desc_input" type="hidden" name="description_html" value="{{ old('description_html', $descVal) }}">
                            <trix-editor input="occ_desc_input"></trix-editor>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ДАТА И ВРЕМЯ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Дата и время</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Начало ({{ $tz }})</label>
                            <input type="datetime-local" name="starts_at_local" value="{{ old('starts_at_local', $startsLocal) }}" required>
                            @error('starts_at_local') <div class="f-13" style="margin-top:4px">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Длительность (ч)</label>
                            <select name="duration_hours">
                                @for($h = 0; $h <= 12; $h++)
                                    <option value="{{ $h }}" @selected(old('duration_hours', $durH) == $h)>{{ $h }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Минуты</label>
                            <select name="duration_minutes">
                                @foreach([0,10,15,20,30,40,45,50] as $m)
                                    <option value="{{ $m }}" @selected(old('duration_minutes', $durM) == $m)>{{ $m }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ЛОКАЦИЯ И УЧАСТНИКИ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Локация и участники</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Локация</label>
                            <select name="location_id">
                                @foreach($locations as $loc)
                                    <option value="{{ $loc->id }}" @selected(old('location_id', $occurrence->location_id ?? $event->location_id) == $loc->id)>
                                        {{ $loc->name }} — {{ $loc->address }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Мин. игроков (для отмены)</label>
                            <input type="number" name="min_players" min="0" value="{{ old('min_players', $minPlayersVal) }}" placeholder="—">
                            <div class="f-13" style="margin-top:.25rem">Если не наберётся — игра отменится</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Показывать участников</label>
                            <input type="hidden" name="show_participants" value="0">
                            <label class="checkbox-item">
                                <input type="checkbox" name="show_participants" value="1" @checked(old('show_participants', $showParts))>
                                <div class="custom-checkbox"></div>
                                <span>Да</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ КОМАНДЫ И ИГРОВАЯ СХЕМА ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Команды и игровая схема</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Количество команд</label>
                            <select name="teams_count">
                                @for($t = 2; $t <= 16; $t++)
                                    <option value="{{ $t }}" @selected(old('teams_count', $teamsCountVal) == $t)>{{ $t }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card">
                            <label>Игровая схема (подтип)</label>
                            <div class="f-13" style="margin-bottom:.5rem">Доступные схемы для {{ direction_name($event->direction) }}:</div>
                            <div class="row">
                                @foreach($subtypes as $st)
                                <div class="col-md-4">
                                    <label class="radio-item">
                                        <input type="radio" name="subtype" value="{{ $st }}" @checked(old('subtype', $subtypeVal) === $st)>
                                        <div class="custom-radio"></div>
                                        <span>{{ $st }}</span>
                                    </label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ УРОВЕНЬ И ВОЗРАСТ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Уровень и возраст</h2>
                <div class="row">
                    @if($event->direction === 'beach')
                    <div class="col-md-3">
                        <div class="card">
                            <label>Мин. уровень (пляж)</label>
                            <select name="beach_level_min">
                                <option value="">—</option>
                                @for($l = 1; $l <= 10; $l++)
                                    <option value="{{ $l }}" @selected(old('beach_level_min', $occurrence->beach_level_min ?? $event->beach_level_min) == $l)>{{ $l }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Макс. уровень (пляж)</label>
                            <select name="beach_level_max">
                                <option value="">—</option>
                                @for($l = 1; $l <= 10; $l++)
                                    <option value="{{ $l }}" @selected(old('beach_level_max', $occurrence->beach_level_max ?? $event->beach_level_max) == $l)>{{ $l }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    @else
                    <div class="col-md-3">
                        <div class="card">
                            <label>Мин. уровень (классика)</label>
                            <select name="classic_level_min">
                                <option value="">—</option>
                                @for($l = 1; $l <= 10; $l++)
                                    <option value="{{ $l }}" @selected(old('classic_level_min', $occurrence->classic_level_min ?? $event->classic_level_min) == $l)>{{ $l }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Макс. уровень (классика)</label>
                            <select name="classic_level_max">
                                <option value="">—</option>
                                @for($l = 1; $l <= 10; $l++)
                                    <option value="{{ $l }}" @selected(old('classic_level_max', $occurrence->classic_level_max ?? $event->classic_level_max) == $l)>{{ $l }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    @endif

                    <div class="col-md-3">
                        <div class="card">
                            <label>Возрастная политика</label>
                            <select name="age_policy" id="occ_age_policy">
                                <option value="adult" @selected(old('age_policy', $agePolicy) === 'adult')>Взрослые</option>
                                <option value="child" @selected(old('age_policy', $agePolicy) === 'child')>Дети</option>
                                <option value="any" @selected(old('age_policy', $agePolicy) === 'any')>Все</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Возраст детей (только если age_policy = child) --}}
                <div class="row" id="occ_child_age_row" style="{{ old('age_policy', $agePolicy) === 'child' ? '' : 'display:none' }}">
                    <div class="col-md-3">
                        <div class="card">
                            <label>Возраст детей: от</label>
                            <input type="number" name="child_age_min" min="3" max="18" value="{{ old('child_age_min', $childMin) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>до</label>
                            <input type="number" name="child_age_max" min="3" max="18" value="{{ old('child_age_max', $childMax) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ГЕНДЕРНЫЕ ОГРАНИЧЕНИЯ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Гендерные ограничения</h2>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <label>Политика</label>
                            <label class="radio-item">
                                <input type="radio" name="gender_policy" value="any" id="gp_any" @checked(old('gender_policy', $genderPolicyVal) === 'any')>
                                <div class="custom-radio"></div>
                                <span>Без ограничений</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="gender_policy" value="men_only" @checked(old('gender_policy', $genderPolicyVal) === 'men_only')>
                                <div class="custom-radio"></div>
                                <span>Только мужчины</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="gender_policy" value="women_only" @checked(old('gender_policy', $genderPolicyVal) === 'women_only')>
                                <div class="custom-radio"></div>
                                <span>Только женщины</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="gender_policy" value="women_limited" id="gp_limited" @checked(old('gender_policy', $genderPolicyVal) === 'women_limited')>
                                <div class="custom-radio"></div>
                                <span>Ограниченное число девушек</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="row" id="gender_limited_wrap" style="{{ old('gender_policy', $genderPolicyVal) === 'women_limited' ? '' : 'display:none' }}">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Макс. девушек</label>
                            <input type="number" name="girls_max" min="0" max="20" value="{{ old('girls_max', $girlsMaxVal) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Лимит на сторону</label>
                            <input type="number" name="gender_limited_max" min="0" max="10" value="{{ old('gender_limited_max', $genderLimitedMaxVal) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Сторона лимита</label>
                            <select name="gender_limited_side">
                                <option value="" @selected(old('gender_limited_side', $genderLimitedSideVal) === '')>—</option>
                                <option value="women" @selected(old('gender_limited_side', $genderLimitedSideVal) === 'women')>Женщины</option>
                                <option value="men" @selected(old('gender_limited_side', $genderLimitedSideVal) === 'men')>Мужчины</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ОПЛАТА ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Оплата</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Платное мероприятие</label>
                            <input type="hidden" name="is_paid" value="0">
                            <label class="checkbox-item">
                                <input type="checkbox" name="is_paid" value="1" id="occ_is_paid" @checked(old('is_paid', $isPaid))>
                                <div class="custom-checkbox"></div>
                                <span>Да</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div id="occ_payment_block" style="{{ old('is_paid', $isPaid) ? '' : 'display:none' }}">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card">
                                <label>Цена (₽)</label>
                                <input type="number" name="price_rub" min="0" step="1" value="{{ old('price_rub', $priceRub) }}">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card">
                                <label>Валюта</label>
                                <select name="price_currency">
                                    @foreach(['RUB','USD','EUR','BYN','KZT'] as $cur)
                                        <option value="{{ $cur }}" @selected(old('price_currency', $priceCurrency) === $cur)>{{ $cur }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <label>Примечание к цене</label>
                                <input type="text" name="price_text" maxlength="255" value="{{ old('price_text', $priceText) }}" placeholder="Напр.: Оплата на месте">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card">
                                <label>Способ оплаты</label>
                                <select name="payment_method">
                                    <option value="" @selected(old('payment_method', $paymentMethod) === '')>—</option>
                                    <option value="onsite" @selected(old('payment_method', $paymentMethod) === 'onsite')>На месте</option>
                                    <option value="transfer" @selected(old('payment_method', $paymentMethod) === 'transfer')>Перевод</option>
                                    <option value="online" @selected(old('payment_method', $paymentMethod) === 'online')>Онлайн</option>
                                    <option value="yookassa" @selected(old('payment_method', $paymentMethod) === 'yookassa')>ЮKassa</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="card">
                                <label>Ссылка на оплату (опционально)</label>
                                <input type="url" name="payment_link" maxlength="500" value="{{ old('payment_link', $paymentLink) }}" placeholder="https://...">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ВОЗВРАТ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Условия возврата</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Полный возврат (часов до начала)</label>
                            <input type="number" name="refund_hours_full" min="0" max="720" value="{{ old('refund_hours_full', $refundFull) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Частичный возврат (часов до начала)</label>
                            <input type="number" name="refund_hours_partial" min="0" max="720" value="{{ old('refund_hours_partial', $refundPartial) }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Процент частичного возврата</label>
                            <input type="number" name="refund_partial_pct" min="0" max="100" value="{{ old('refund_partial_pct', $refundPct) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ТРЕНЕР ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Тренер</h2>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <label>Тренер занятия</label>
                            <input type="hidden" name="trainer_user_id" id="occ_trainer_id" value="{{ old('trainer_user_id', $trainerId) }}">
                            <input type="text" id="occ_trainer_search" value="{{ $trainerName }}" placeholder="Поиск по имени...">
                            <div id="occ_trainer_results" style="position:relative"></div>
                            <div class="f-13" style="margin-top:.5rem">Начните вводить имя — выберите из списка. Чтобы очистить, сотрите текст.</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ ПЕРСОНАЛЬНЫЕ ДАННЫЕ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Персональные данные</h2>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <input type="hidden" name="requires_personal_data" value="0">
                            <label class="checkbox-item">
                                <input type="checkbox" name="requires_personal_data" value="1" @checked(old('requires_personal_data', $reqPersonal))>
                                <div class="custom-checkbox"></div>
                                <span>Требовать согласие на обработку ПД при записи</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ РЕГИСТРАЦИЯ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Регистрация</h2>
                <div class="row">
                    <div class="col-md-3">
                        <div class="card">
                            <label>Регистрация</label>
                            <input type="hidden" name="allow_registration" value="0">
                            <label class="checkbox-item">
                                <input type="checkbox" name="allow_registration" value="1" @checked(old('allow_registration', $allowReg))>
                                <div class="custom-checkbox"></div>
                                <span>Включена</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Начало рег. (дней до)</label>
                            <select name="reg_starts_days_before">
                                @for($d = 0; $d <= 90; $d++)
                                    <option value="{{ $d }}" @selected(old('reg_starts_days_before', $regStartsDays) == $d)>{{ $d }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Конец рег. (до начала)</label>
                            <input type="hidden" name="reg_ends_minutes_before" id="occ_reg_ends_min" value="{{ old('reg_ends_minutes_before', $regEndsMin) }}">
                            <div class="d-flex" style="gap:.5rem">
                                <select id="occ_reg_ends_h" style="width:auto">
                                    @for($h = 0; $h <= 24; $h++)
                                        <option value="{{ $h }}" @selected($regEndsHours == $h)>{{ $h }} ч</option>
                                    @endfor
                                </select>
                                <select id="occ_reg_ends_m" style="width:auto">
                                    @foreach([0,10,15,20,30,40,50] as $m)
                                        <option value="{{ $m }}" @selected($regEndsMins == $m)>{{ $m }} м</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Запрет отмены (до начала)</label>
                            <input type="hidden" name="cancel_lock_minutes_before" id="occ_cancel_min" value="{{ old('cancel_lock_minutes_before', $cancelMin) }}">
                            <div class="d-flex" style="gap:.5rem">
                                <select id="occ_cancel_h" style="width:auto">
                                    @for($h = 0; $h <= 24; $h++)
                                        <option value="{{ $h }}" @selected($cancelHours == $h)>{{ $h }} ч</option>
                                    @endfor
                                </select>
                                <select id="occ_cancel_m" style="width:auto">
                                    @foreach([0,10,15,20,30,40,50] as $m)
                                        <option value="{{ $m }}" @selected($cancelMins == $m)>{{ $m }} м</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ НАПОМИНАНИЕ ============ --}}
            <div class="ramka">
                <h2 class="-mt-05">Напоминание</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <input type="hidden" name="remind_registration_enabled" value="0">
                            <label class="checkbox-item">
                                <input type="checkbox" name="remind_registration_enabled" value="1" @checked(old('remind_registration_enabled', $remEnabled))>
                                <div class="custom-checkbox"></div>
                                <span>Напоминание включено</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>За сколько до начала</label>
                            <input type="hidden" name="remind_registration_minutes_before" id="occ_rem_min" value="{{ old('remind_registration_minutes_before', $remMin) }}">
                            <div class="d-flex" style="gap:.5rem">
                                <select id="occ_rem_h" style="width:auto">
                                    @for($h = 0; $h <= 24; $h++)
                                        <option value="{{ $h }}" @selected($remH == $h)>{{ $h }} ч</option>
                                    @endfor
                                </select>
                                <select id="occ_rem_m" style="width:auto">
                                    @foreach([0,5,10,15,20,30,45] as $m)
                                        <option value="{{ $m }}" @selected($remM == $m)>{{ $m }} м</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ============ КНОПКИ ============ --}}
            <div class="ramka">
                <div class="d-flex" style="gap:1rem;justify-content:center;flex-wrap:wrap">
                    <button type="submit" class="btn">Сохранить</button>
                    <a href="{{ route('events.event_management.occurrences', $event) }}" class="btn btn-secondary">Отмена</a>
                    <a href="{{ route('events.event_management.edit', $event) }}" class="btn btn-secondary">Остальные настройки (серия)</a>
                </div>
            </div>
        </form>
    </div>

    <x-slot name="script">
    <script src="/assets/trix.js?v={{ time() }}"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // ===== Часы/минуты → скрытое поле минут =====
        function syncHM(hSel, mSel, hidden) {
            hidden.value = parseInt(hSel.value||0)*60 + parseInt(mSel.value||0);
        }
        [
            ['occ_reg_ends_h','occ_reg_ends_m','occ_reg_ends_min'],
            ['occ_cancel_h','occ_cancel_m','occ_cancel_min'],
            ['occ_rem_h','occ_rem_m','occ_rem_min'],
        ].forEach(function(ids) {
            var h = document.getElementById(ids[0]);
            var m = document.getElementById(ids[1]);
            var hid = document.getElementById(ids[2]);
            if (h && m && hid) {
                h.addEventListener('change', function() { syncHM(h,m,hid); });
                m.addEventListener('change', function() { syncHM(h,m,hid); });
            }
        });

        // ===== Платное/бесплатное — показ блока оплаты =====
        var isPaid = document.getElementById('occ_is_paid');
        var payBlock = document.getElementById('occ_payment_block');
        if (isPaid && payBlock) {
            isPaid.addEventListener('change', function() {
                payBlock.style.display = this.checked ? '' : 'none';
            });
        }

        // ===== Возрастная политика — показ полей возраста детей =====
        var agePol = document.getElementById('occ_age_policy');
        var childRow = document.getElementById('occ_child_age_row');
        if (agePol && childRow) {
            agePol.addEventListener('change', function() {
                childRow.style.display = this.value === 'child' ? '' : 'none';
            });
        }

        // ===== Гендерная политика — показ блока лимитов =====
        var genderLimitedWrap = document.getElementById('gender_limited_wrap');
        document.querySelectorAll('input[name="gender_policy"]').forEach(function(r) {
            r.addEventListener('change', function() {
                if (genderLimitedWrap) {
                    genderLimitedWrap.style.display = this.value === 'women_limited' ? '' : 'none';
                }
            });
        });

        // ===== Trix — очистка стилей при вставке =====
        document.addEventListener('trix-paste', function(e) {
            var editor = e.target.editor;
            if (!editor) return;
            setTimeout(function() {
                var html = editor.getDocument().toString();
                // можно добавить стриппинг тут если нужно
            }, 10);
        });

        // ===== Поиск тренера (reuse /api/users/search если есть) =====
        var trainerInput = document.getElementById('occ_trainer_search');
        var trainerId = document.getElementById('occ_trainer_id');
        var trainerResults = document.getElementById('occ_trainer_results');
        var searchTimer = null;

        if (trainerInput && trainerId && trainerResults) {
            trainerInput.addEventListener('input', function() {
                var q = this.value.trim();
                clearTimeout(searchTimer);

                if (q === '') {
                    trainerId.value = '';
                    trainerResults.innerHTML = '';
                    return;
                }
                if (q.length < 2) return;

                searchTimer = setTimeout(function() {
                    fetch('/api/users/search?q=' + encodeURIComponent(q), {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var list = Array.isArray(data) ? data : (data.data || []);
                        if (!list.length) {
                            trainerResults.innerHTML = '<div class="card f-13" style="margin-top:.5rem">Ничего не найдено</div>';
                            return;
                        }
                        var html = '<div class="card" style="margin-top:.5rem;max-height:250px;overflow:auto">';
                        list.slice(0,10).forEach(function(u) {
                            var nm = (u.first_name||'') + ' ' + (u.last_name||'');
                            nm = nm.trim() || u.name || ('#'+u.id);
                            html += '<div class="occ-trainer-opt" data-id="'+u.id+'" data-name="'+nm.replace(/"/g,'&quot;')+'" style="padding:.5rem;cursor:pointer;border-bottom:1px solid rgba(0,0,0,.05)">'+nm+'</div>';
                        });
                        html += '</div>';
                        trainerResults.innerHTML = html;

                        trainerResults.querySelectorAll('.occ-trainer-opt').forEach(function(el) {
                            el.addEventListener('click', function() {
                                trainerId.value = this.dataset.id;
                                trainerInput.value = this.dataset.name;
                                trainerResults.innerHTML = '';
                            });
                        });
                    })
                    .catch(function() {});
                }, 300);
            });
        }
    });
    </script>
    </x-slot>
</x-voll-layout>
