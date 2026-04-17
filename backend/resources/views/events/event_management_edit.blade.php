{{-- resources/views/events/event_management_edit.blade.php --}}
@php
    $isAdmin = (auth()->user()?->role ?? null) === 'admin';
    $remMin = (int) old('remind_registration_minutes_before', $event->remind_registration_minutes_before ?? 600);
    if ($remMin < 0) $remMin = 600;
@endphp

<x-voll-layout>

    <x-slot name="title">Редактирование мероприятия #{{ (int)$event->id }}</x-slot>
    <x-slot name="h1">Редактирование мероприятия</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.create.event_management') }}" itemprop="item">
                <span itemprop="name">Управление мероприятиями</span>
            </a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ url('/events/' . (int)$event->id) }}" itemprop="item">
                <span itemprop="name">#{{ (int)$event->id }} {{ $event->title }}</span>
            </a>
            <meta itemprop="position" content="3">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">Редактировать</span>
            <meta itemprop="position" content="4">
        </li>
    </x-slot>

    <x-slot name="t_description">
        Активных записей: <strong>{{ (int)$activeRegs }}</strong>.
        Изменения не удаляют существующие записи.
    </x-slot>

    <x-slot name="style">
        <link href="/assets/org.css" rel="stylesheet">
    </x-slot>

    <div class="container">

        {{-- FLASH / ERRORS --}}
        @if (session('status'))
            <div class="ramka">
                <div class="alert alert-success">{{ session('status') }}</div>
            </div>
        @endif
        @if (session('error'))
            <div class="ramka">
                <div class="alert alert-error">{{ session('error') }}</div>
            </div>
        @endif
        @if ($errors->any())
            <div class="ramka">
                <div class="alert alert-error">
                    <div class="alert-title">Проверьте поля</div>
                    <ul class="list">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="form">
            <form method="POST"
                  action="{{ route('events.event_management.update', ['event' => (int)$event->id]) }}"
                  enctype="multipart/form-data">
                @csrf
                @method('PUT')

                {{-- ===== БЛОК 1: Основные настройки ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Основные настройки</h2>
                    <div class="row">

                        <div class="col-md-6">
                            <div class="card">
                                <label>Название мероприятия</label>
                                <input
                                    name="title"
                                    value="{{ old('title', (string)$event->title) }}"
                                    placeholder="Название мероприятия"
                                    required
                                >
                                @error('title')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Направление</label>
                                <select name="direction" id="direction_edit">
                                    <option value="classic" @selected(old('direction', $event->direction) === 'classic')>Классика</option>
                                    <option value="beach" @selected(old('direction', $event->direction) === 'beach')>Пляж</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Тип мероприятия</label>
                                <select name="format">
                                    @foreach([
                                        'game' => 'Игра',
                                        'training' => 'Тренировка',
                                        'training_game' => 'Тренировка + Игра',
                                        'coach_student' => 'Тренер + ученик',
                                        'tournament' => 'Турнир',
                                        'camp' => 'КЕМП',
                                    ] as $k => $label)
                                        <option value="{{ $k }}" @selected(old('format', $event->format) === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Начало (UTC)</label>
                                <input
                                    name="starts_at"
                                    type="datetime-local"
                                    value="{{ old('starts_at', $event->starts_at ? $event->starts_at->copy()->format('Y-m-d\TH:i') : '') }}"
                                    required
                                >
                                @error('starts_at')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Длительность</label>
                                <div class="row row2">
                                    <div class="col-4">
                                        <label>Часы</label>
                                        <select name="duration_hours">
                                            @for($h = 0; $h <= 23; $h++)
                                                <option value="{{ $h }}" @selected((old('duration_hours', $event->duration_sec ? (int)floor($event->duration_sec / 3600) : 0)) == $h)>{{ $h }}</option>
                                            @endfor
                                        </select>
                                            
                                    </div>
                                    <div class="col-4">
                                        <label>Мин</label>
                                        <select name="duration_minutes">
                                            @foreach([0,15,30,45] as $m)
                                                <option value="{{ $m }}" @selected((old('duration_minutes', $event->duration_sec ? (int)(($event->duration_sec % 3600) / 60) : 0)) == $m)>{{ $m }}</option>
                                            @endforeach
                                        </select>
                                            
                                    </div>
                                </div>
                                <input type="hidden" name="duration_sec" id="duration_sec_edit"
                                    value="{{ old('duration_sec', $event->duration_sec ?? 0) }}">
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Timezone</label>
                                <input
                                    name="timezone"
                                    value="{{ old('timezone', (string)$event->timezone) }}"
                                    placeholder="Europe/Moscow"
                                    required
                                >
                                <ul class="list f-16 mt-1">
                                    <li>Напр. Europe/Moscow, Asia/Novosibirsk</li>
                                </ul>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 2: Локация ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Локация</h2>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="d-flex between">
                                    <label>Локация</label>
                                    @if($isAdmin)
                                        <a href="{{ route('admin.locations.create') }}"
                                           class="f-16 cd b-600">+ Создать локацию</a>
                                    @endif
                                </div>

                                <select name="location_id" id="location_id_edit" class="w-full rounded-lg border-gray-200" required>
                                    <option value="">— выбрать —</option>
                                    @foreach(($locations ?? []) as $loc)
                                        @php
                                            $thumb = $loc->getFirstMediaUrl('photos', 'thumb');
                                            if (empty($thumb)) $thumb = $loc->getFirstMediaUrl('photos');
                                        @endphp
                                        <option
                                            value="{{ (int)$loc->id }}"
                                            @selected((int)old('location_id', (int)$event->location_id) === (int)$loc->id)
                                            data-name="{{ e((string)$loc->name) }}"
                                            data-city="{{ e((string)($loc->city?->name ?? '')) }}"
                                            data-address="{{ e((string)($loc->address ?? '')) }}"
                                            data-lat="{{ $loc->lat ?? '' }}"
                                            data-lng="{{ $loc->lng ?? '' }}"
                                            data-thumb="{{ e((string)$thumb) }}"
                                        >
                                            {{ $loc->name }}@if(!empty($loc->address)) — {{ $loc->address }}@endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('location_id')
                                    <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                @enderror

                                {{-- preview --}}
                                <div id="location_preview_edit" class="mt-2 hidden">
                                    <div class="row fvc">
                                        <div class="col-3 location_preview">
                                            <img id="lpe_img" src="" alt="" class="border hidden">
                                            <div id="lpe_noimg" class="icon-nophoto"></div>
                                        </div>
                                        <div class="col-5">
                                            <p class="cd b-600" id="lpe_name"></p>
                                            <p class="mt-1 f-16" id="lpe_meta"></p>
                                        </div>
                                        <div class="col-4">
                                            <div class="border" id="lpe_map_wrap" style="display:none;">
                                                <iframe id="lpe_map" src="" class="w-100" style="height:120px;" loading="lazy"></iframe>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- ===== БЛОК 3: Игровые настройки ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Игровые настройки</h2>
                    <div class="row">

                        <div class="col-md-3">
                            <div class="card">
                                <label>Подтип игры</label>
                                <select name="game_subtype">
                                    @foreach(['4x4' => '4×4', '4x2' => '4×2', '5x1' => '5×1'] as $k => $l)
                                        <option value="{{ $k }}"
                                            @selected(old('game_subtype', $event->gameSettings?->subtype) === $k)>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Команды</label>
                                <input type="number" name="teams_count" min="2" max="200"
                                    value="{{ old('teams_count', $event->gameSettings?->teams_count ?? 2) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Мин. игроков</label>
                                <input type="number" name="game_min_players" min="0" max="99"
                                    value="{{ old('game_min_players', $event->gameSettings?->min_players ?? 0) }}">
                            </div>
                        </div>

                        <div class="col-md-3">
                            <div class="card">
                                <label>Макс. игроков</label>
                                <input type="number" name="game_max_players" min="0" max="99"
                                    value="{{ old('game_max_players', $event->gameSettings?->max_players ?? 0) }}">
                                <ul class="list f-16 mt-1">
                                    <li>Рассчитывается автоматически при создании.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <label>Гендерные ограничения</label>
                                <select name="game_gender_policy">
                                    @foreach([
                                        'mixed_open' => 'М/Ж (без ограничений)',
                                        'mixed_5050' => 'Микс 50/50',
                                        'only_male' => 'Только М',
                                        'only_female' => 'Только Ж',
                                        'mixed_limited' => 'М/Ж (с ограничениями)',
                                    ] as $k => $l)
                                        <option value="{{ $k }}"
                                            @selected(old('game_gender_policy', $event->gameSettings?->gender_policy) === $k)>
                                            {{ $l }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card">
                                <label>Ограничиваемый пол: начало регистрации (дней до)</label>
                                <input type="number"
                                    name="game_gender_limited_reg_starts_days_before"
                                    min="0" max="365"
                                    value="{{ old('game_gender_limited_reg_starts_days_before', $event->gameSettings?->gender_limited_reg_starts_days_before) }}"
                                    placeholder="например, 1">
                                <ul class="list f-14 mt-1">
                                    <li>Только при «М/Ж (с ограничениями)». Если пусто — действует общее «Начало регистрации».</li>
                                </ul>
                            </div>
                        </div>

@if(($event->direction ?? 'classic') === 'classic')
                        <div class="col-md-6">
                            <div class="card">
                                <label>Уровень допуска (классика)</label>
                                <div class="row row2">
                                    <div class="col-6">
                                        <label>От</label>
                                        <input type="number" name="classic_level_min" min="1" max="7"
                                            value="{{ old('classic_level_min', $event->classic_level_min) }}"
                                            placeholder="—">
                                    </div>
                                    <div class="col-6">
                                        <label>До</label>
                                        <input type="number" name="classic_level_max" min="1" max="7"
                                            value="{{ old('classic_level_max', $event->classic_level_max) }}"
                                            placeholder="—">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        @if(($event->direction ?? 'classic') === 'beach')
                        <div class="col-md-6">
                            <div class="card">
                                <label>Уровень допуска (пляж)</label>
                                <div class="row row2">
                                    <div class="col-6">
                                        <label>От</label>
                                        <input type="number" name="beach_level_min" min="1" max="7"
                                            value="{{ old('beach_level_min', $event->beach_level_min) }}"
                                            placeholder="—">
                                    </div>
                                    <div class="col-6">
                                        <label>До</label>
                                        <input type="number" name="beach_level_max" min="1" max="7"
                                            value="{{ old('beach_level_max', $event->beach_level_max) }}"
                                            placeholder="—">
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        <div class="col-md-6">
                            <div class="card">
                                <label>Возрастные ограничения</label>
                                @php $agePolicy = old('age_policy', $event->age_policy ?? 'adult'); @endphp
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="adult" @checked($agePolicy === 'adult')>
                                    <div class="custom-radio"></div>
                                    <span>Для взрослых</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="child" @checked($agePolicy === 'child')>
                                    <div class="custom-radio"></div>
                                    <span>Для детей</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="age_policy" value="any" @checked($agePolicy === 'any')>
                                    <div class="custom-radio"></div>
                                    <span>Без ограничений</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 4: Регистрация ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Регистрация</h2>
                    <div class="row">

                        <div class="col-md-4">
                            <div class="card">
                                <label>Регистрация игроков через сервис</label>
                                @php $allowRegVal = old('allow_registration', (int)((bool)$event->allow_registration)); @endphp
                                <label class="radio-item">
                                    <input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal === '1')>
                                    <div class="custom-radio"></div>
                                    <span>Да</span>
                                </label>
                                <label class="radio-item">
                                    <input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal === '0')>
                                    <div class="custom-radio"></div>
                                    <span>Нет</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Начало регистрации (дней до)</label>
                                <select name="reg_starts_days_before">
                                    @for($d = 0; $d <= 90; $d++)
                                        <option value="{{ $d }}" @selected((old('reg_starts_days_before', 3)) == $d)>{{ $d }}</option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Окончание регистрации (минут до)</label>
                                <select name="reg_ends_minutes_before">
                                    @foreach([1,5,10,15,20,25,30,35,40,45,50,55,60] as $m)
                                        <option value="{{ $m }}" @selected((old('reg_ends_minutes_before', 15)) == $m)>{{ $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Запрет отмены записи (минут до)</label>
                                <select name="cancel_lock_minutes_before">
                                    @foreach([1,5,10,15,20,25,30,35,40,45,50,55,60] as $m)
                                        <option value="{{ $m }}" @selected((old('cancel_lock_minutes_before', 60)) == $m)>{{ $m }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 5: Доступность ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Доступность и оплата</h2>
                    <div class="row">

                        <div class="col-md-4">
                            <div class="card">
                                <label class="checkbox-item">
                                    <input type="hidden" name="is_private" value="0">
                                    <input type="checkbox" name="is_private" value="1"
                                        @checked(old('is_private', $event->is_private ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>Приватное (только по ссылке)</span>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label class="checkbox-item">
                                    <input type="hidden" name="is_paid" value="0">
                                    <input type="checkbox" name="is_paid" value="1" id="is_paid_edit"
                                        @checked(old('is_paid', $event->is_paid ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>Платное</span>
                                </label>

                                <div class="row row2 mt-1" id="price_wrap_edit">
                                    <div class="col-7">
                                        <label>Стоимость</label>
                                        <input type="number" name="price_amount" min="10" max="500000" step="0.01"
                                            value="{{ old('price_amount', $event->price_minor ? $event->price_minor / 100 : '') }}"
                                            placeholder="Напр. 500">
                                        @error('price_amount')
                                            <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="col-5">
                                        <label>Валюта</label>
                                        <select name="price_currency">
                                            @foreach(['RUB' => '₽ RUB', 'USD' => '$ USD', 'EUR' => '€ EUR', 'KZT' => '₸ KZT'] as $code => $label)
                                                <option value="{{ $code }}"
                                                    @selected(old('price_currency', $event->price_currency ?? 'RUB') === $code)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                </div>
                                
                                <div class="mt-2">
                                    <label>Способ оплаты</label>
                                    <select name="payment_method">
                                        @foreach([
                                            'cash' => '💵 Наличные (на месте)',
                                            'tbank_link' => '🏦 Перевод Т-Банк (по ссылке)',
                                            'sber_link' => '💚 Перевод Сбер (по ссылке)',
                                            'yoomoney' => '🟡 ЮКасса (автоматическая оплата)'
                                        ] as $method => $label)
                                            <option value="{{ $method }}" 
                                                @selected(old('payment_method', $event->payment_method ?? 'cash') === $method)>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="mt-2" id="payment-link-block" style="display:none">
                                    <label>Ссылка на оплату</label>
                                    <input type="text" name="payment_link" class="form-control"
                                        placeholder="https://..."
                                        value="{{ old('payment_link', $event->payment_link ?? '') }}">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="card">
                                <label>Показывать список записавшихся</label>
                                @php $showParts = old('show_participants', $event->show_participants ?? true); @endphp
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
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ===== БЛОК 6: Уведомления ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Уведомления</h2>
                    <div class="row">

                        <div class="col-md-6">
                            <div class="card">
                                <label>Напоминание игроку о записи</label>
                                <label class="checkbox-item">
                                    <input type="hidden" name="remind_registration_enabled" value="0">
                                    <input type="checkbox" name="remind_registration_enabled" value="1"
                                        @checked(old('remind_registration_enabled', $event->remind_registration_enabled ?? true))>
                                    <div class="custom-checkbox"></div>
                                    <span>Включено</span>
                                </label>
                                <div class="mt-2">
                                    <label>За сколько до начала</label>
                                    <div class="row row2">
                                        <div class="col-6">
                                            <label>Часы</label>
                                            <select id="remind_hours_edit">
                                                @for($h = 0; $h <= 12; $h++)
                                                    <option value="{{ $h }}" @selected((int) floor($remMin / 60) == $h)>{{ $h }}</option>
                                                @endfor
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label>Минуты</label>
                                            <select id="remind_minutes_edit">
                                                @foreach([0,5,10,15,20,25,30,35,40,45,50,55,60] as $m)
                                                    <option value="{{ $m }}" @selected(($remMin % 60) == $m)>{{ $m }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                    <input type="hidden" name="remind_registration_minutes_before"
                                        id="remind_hidden_edit" value="{{ $remMin }}">
                                    <ul class="list f-16 mt-1">
                                        <li>Пример: 10 часов 0 минут = напоминание за 10 часов до начала.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                @if(($event->registration_type ?? 'individual') !== 'team' && ($event->format ?? 'game') !== 'tournament')
                {{-- ===== БЛОК 7: Помощник записи 🤖 ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Помощник записи 🤖</h2>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <label class="checkbox-item">
                                    <input type="hidden" name="bot_assistant_enabled" value="0">
                                    <input type="checkbox" name="bot_assistant_enabled" value="1"
                                        id="bot_assistant_enabled_edit"
                                        @checked(old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false))>
                                    <div class="custom-checkbox"></div>
                                    <span>Включить помощника записи</span>
                                </label>
                                <ul class="list f-16 mt-1">
                                    <li>Боты занимают места если живых игроков меньше порога. Уходят по мере прихода реальных игроков.</li>
                                    <li>Видно только организатору и администратору.</li>
                                </ul>
                            </div>
                        </div>

                        <div class="col-md-6" id="bot_settings_edit"
                             @if(!old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false)) style="display:none" @endif>
                            <div class="card">
                                <label>Порог запуска:
                                    <strong id="bot_threshold_val_edit" class="cd">
                                        {{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}%
                                    </strong>
                                </label>
                                <input type="range" name="bot_assistant_threshold"
                                    min="5" max="30" step="5"
                                    value="{{ old('bot_assistant_threshold', $event->bot_assistant_threshold ?? 10) }}"
                                    oninput="document.getElementById('bot_threshold_val_edit').textContent = this.value + '%'">
                                <ul class="list f-16 mt-1"><li>Диапазон: 5–30%.</li></ul>
                            </div>
                        </div>

                        <div class="col-md-6" id="bot_fill_edit"
                             @if(!old('bot_assistant_enabled', $event->bot_assistant_enabled ?? false)) style="display:none" @endif>
                            <div class="card">
                                <label>Макс. заполнение ботами:
                                    <strong id="bot_fill_val_edit" class="cd">
                                        {{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}%
                                    </strong>
                                </label>
                                <input type="range" name="bot_assistant_max_fill_pct"
                                    min="10" max="60" step="10"
                                    value="{{ old('bot_assistant_max_fill_pct', $event->bot_assistant_max_fill_pct ?? 40) }}"
                                    oninput="document.getElementById('bot_fill_val_edit').textContent = this.value + '%'">
                                <ul class="list f-16 mt-1"><li>Диапазон: 10–60%.</li></ul>
                            </div>
                        </div>
                    </div>
                </div>

                @endif

                {{-- ===== БЛОК 8: Описание ===== --}}
                <div class="ramka">
                    <h2 class="-mt-05">Описание мероприятия</h2>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <input id="description_html_edit" type="hidden" name="description_html"
                                    value="{{ old('description_html', $event->description?->html ?? '') }}">
                                <trix-editor input="description_html_edit" class="trix-content"></trix-editor>
                                @error('description_html')
                                    <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- КНОПКИ --}}
                <div class="ramka text-center">
                    <a href="{{ route('events.create.event_management') }}" class="btn btn-secondary mr-2">← Отмена</a>
                    <button type="submit" class="btn">Сохранить изменения</button>
                </div>

            </form>
        </div>
    </div>

    <x-slot name="script">
        <script src="/assets/fas.js"></script>
        <script src="/assets/org.js"></script>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            // --- Длительность ---
            const dHours   = document.querySelector('[name="duration_hours"]');
            const dMinutes = document.querySelector('[name="duration_minutes"]');
            const dSec     = document.getElementById('duration_sec_edit');
            function syncDuration() {
                const h = Math.max(0, parseInt(dHours?.value || 0, 10));
                const m = Math.max(0, Math.min(59, parseInt(dMinutes?.value || 0, 10)));
                if (dSec) dSec.value = h * 3600 + m * 60;
            }
            dHours?.addEventListener('input', syncDuration);
            dMinutes?.addEventListener('input', syncDuration);
            syncDuration();

            // --- Напоминание ---
            const rH = document.getElementById('remind_hours_edit');
            const rM = document.getElementById('remind_minutes_edit');
            const rHid = document.getElementById('remind_hidden_edit');
            function syncRemind() {
                const h = Math.max(0, parseInt(rH?.value || 0, 10));
                const m = Math.max(0, Math.min(59, parseInt(rM?.value || 0, 10)));
                if (rHid) rHid.value = h * 60 + m;
            }
            rH?.addEventListener('input', syncRemind);
            rM?.addEventListener('input', syncRemind);
            syncRemind();

            // --- Бот-ассистент toggle ---
            const botChk = document.getElementById('bot_assistant_enabled_edit');
            const botSettings = document.getElementById('bot_settings_edit');
            const botFill     = document.getElementById('bot_fill_edit');
            function syncBot() {
                const show = botChk?.checked;
                if (botSettings) botSettings.style.display = show ? '' : 'none';
                if (botFill) botFill.style.display = show ? '' : 'none';
            }
            botChk?.addEventListener('change', syncBot);

            // --- Location preview ---
            const sel = document.getElementById('location_id_edit');
            const wrap = document.getElementById('location_preview_edit');
            const img = document.getElementById('lpe_img');
            const noimg = document.getElementById('lpe_noimg');
            const nameEl = document.getElementById('lpe_name');
            const metaEl = document.getElementById('lpe_meta');
            const mapWrap = document.getElementById('lpe_map_wrap');
            const mapEl = document.getElementById('lpe_map');

            function updatePreview() {
                const opt = sel?.options[sel.selectedIndex];
                if (!opt?.value) { wrap?.classList.add('hidden'); return; }
                wrap?.classList.remove('hidden');
                if (nameEl) nameEl.textContent = opt.getAttribute('data-name') || '';
                const city = opt.getAttribute('data-city') || '';
                const addr = opt.getAttribute('data-address') || '';
                if (metaEl) metaEl.textContent = [city, addr].filter(Boolean).join(' · ');
                const thumb = opt.getAttribute('data-thumb') || '';
                if (thumb && img && noimg) {
                    img.src = thumb; img.classList.remove('hidden'); noimg.classList.add('hidden');
                } else if (img && noimg) {
                    img.src = ''; img.classList.add('hidden'); noimg.classList.remove('hidden');
                }
                const lat = (opt.getAttribute('data-lat') || '').trim();
                const lng = (opt.getAttribute('data-lng') || '').trim();
                if (mapWrap && mapEl && lat && lng && !isNaN(lat) && !isNaN(lng)) {
                    mapWrap.style.display = '';
                    mapEl.src = `https://yandex.ru/map-widget/v1/?ll=${lng},${lat}&z=15&l=map&pt=${lng},${lat},pm2rdm`;
                } else if (mapWrap) {
                    mapWrap.style.display = 'none';
                }
            }
            sel?.addEventListener('change', updatePreview);
            updatePreview();



            // Автоматический расчёт макс. игроков (только для НЕ-турниров)
            function updateMaxPlayers() {
                const display = document.getElementById('max_players_display');
                const hidden = document.getElementById('game_max_players_hidden');
                
                // Работаем только если есть элементы автоматического расчёта  
                if (!display || !hidden) {
                    console.log('Автоматический расчёт отключён (турнир)');
                    return;
                }
                
                const teamsInput = document.querySelector('input[name="teams_count"]');
                if (teamsInput) {
                    const teams = parseInt(teamsInput.value) || 2;
                    const maxPlayers = teams * 6;
                    
                    display.textContent = maxPlayers;
                    hidden.value = maxPlayers;
                    
                    console.log(`Автоматический расчёт: ${teams} команд = ${maxPlayers} игроков`);
                }
            }
            
            // Привязываем обработчики только если есть автоматический расчёт
            const display = document.getElementById('max_players_display');
            if (display) {
                const teamsInput = document.querySelector('input[name="teams_count"]');
                if (teamsInput) {
                    teamsInput.addEventListener('input', updateMaxPlayers);
                    teamsInput.addEventListener('change', updateMaxPlayers);
                    console.log('Автоматический расчёт подключён');
                }
                updateMaxPlayers(); // Инициализация
            }
        });

            // === Payment link toggle ===
            const payMethodSel = document.querySelector('select[name="payment_method"]');
            const payLinkBlock = document.getElementById('payment-link-block');
            function syncPayLink() {
                if (!payMethodSel || !payLinkBlock) return;
                const needsLink = ['tbank_link','sber_link'].includes(payMethodSel.value);
                payLinkBlock.style.display = needsLink ? '' : 'none';
            }
            syncPayLink();
            payMethodSel?.addEventListener('change', syncPayLink);

        </script>
    </x-slot>

</x-voll-layout>