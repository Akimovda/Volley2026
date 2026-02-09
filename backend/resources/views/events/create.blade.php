{{-- resources/views/events/create.blade.php --}}
@php
    $prefill = $prefill ?? [];
    $formats = [
        'game' => 'Игра',
        'training' => 'Тренировка',
        'training_game' => 'Тренировка + Игра',
        'coach_student' => 'Тренер + ученик (только пляж)',
        'tournament' => 'Турнир',
        'camp' => 'КЕМП',
    ];
    $timezones = [
        'Europe/Moscow', 'Europe/Berlin', 'Europe/Kyiv',
        'Asia/Dubai', 'Asia/Almaty',
        'UTC',
    ];
    $isAdmin = (auth()->user()?->role ?? null) === 'admin';

    $step1Fields = [
        'organizer_id',
        'title','direction','format',
        // ✅ trainer
        'trainer_user_id',

        'game_subtype','game_min_players','game_max_players',
        'game_libero_mode',
        'game_gender_policy','game_gender_limited_side','game_gender_limited_max','game_gender_limited_positions',
        'classic_level_min','classic_level_max',
        'beach_level_min','beach_level_max',
        'allow_registration',
    ];
    // ✅ recurring перенесён в Step 2
    $step2Fields = [
        'timezone','starts_at_local','ends_at_local','location_id',
        'is_recurring','recurrence_type','recurrence_interval','recurrence_months','recurrence_rule',
    ];
    $step3Fields = [
        'is_private',
        'is_paid','price_text',
        'requires_personal_data',
        'save_as_template','template_name','template_payload_text',
        'cover_upload','cover_media_id',
    ];

    $initialStep = 1;
    if ($errors->any()) {
        foreach ($step3Fields as $f) { if ($errors->has($f)) { $initialStep = 3; break; } }
        if ($initialStep === 1) {
            foreach ($step2Fields as $f) { if ($errors->has($f)) { $initialStep = 2; break; } }
        }
    } else {
        if (
            old('timezone') || old('starts_at_local') || old('location_id') ||
            old('is_recurring') || old('recurrence_type') || old('recurrence_interval') || old('recurrence_months')
        ) {
            $initialStep = 2;
        } elseif (
            old('is_private') || old('is_paid') ||
            old('save_as_template') || old('template_name') ||
            old('requires_personal_data') ||
            old('cover_media_id')
        ) {
            $initialStep = 3;
        }
    }

    $monthsMap = [
        1=>'Янв',2=>'Фев',3=>'Мар',4=>'Апр',5=>'Май',6=>'Июн',
        7=>'Июл',8=>'Авг',9=>'Сен',10=>'Окт',11=>'Ноя',12=>'Дек'
    ];
    $oldMonths = old('recurrence_months', $prefill['recurrence_months'] ?? []);
    if (is_string($oldMonths)) $oldMonths = [$oldMonths];
    if (!is_array($oldMonths)) $oldMonths = [];
    $oldMonths = array_map('intval', $oldMonths);

    // ✅ Prefill trainer
    $oldTrainerId = (int)old('trainer_user_id', $prefill['trainer_user_id'] ?? 0);
    $oldTrainerLabel = (string)old('trainer_user_label', $prefill['trainer_user_label'] ?? '');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Создать мероприятие
            </h2>
            <a href="{{ route('events.index') }}"
               class="inline-flex items-center px-4 py-2 rounded-lg font-semibold text-sm border border-gray-200 bg-white hover:bg-gray-50">
                ← К мероприятиям
            </a>
        </div>
    </x-slot>

    {{-- FLASH --}}
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 mt-6">
        @if (session('status'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-800 border border-green-100">
                {{ session('status') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-red-50 text-red-800 border border-red-100 text-sm">
                <div class="font-semibold mb-2">Ошибки:</div>
                <ul class="list-disc ml-5 space-y-1">
                    @foreach ($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <style>
        .step-shell { border-radius: 18px; padding: 3px; transition: background 160ms ease, box-shadow 160ms ease; }
        .step-shell.is-active { background: linear-gradient(135deg, rgba(17,24,39,0.10), rgba(59,130,246,0.10)); box-shadow: 0 8px 30px rgba(15, 23, 42, 0.08); }
        .step-shell > .step-card { border-radius: 16px; }
        .step-shell.is-active > .step-card { border-color: rgba(59,130,246,0.35) !important; }
        .progress-pill { background: rgba(17,24,39,0.06); border: 1px solid rgba(17,24,39,0.08); }
        .pill.is-active { border-color: rgba(59,130,246,0.55) !important; background: rgba(59,130,246,0.06); color: #111827; }
        .pill.is-done { border-color: rgba(16,185,129,0.45) !important; background: rgba(16,185,129,0.06); color: #065f46; }
        .ac-box { position: relative; }
        .ac-dd { position:absolute; left:0; right:0; top: calc(100% + 6px); z-index:50; background:#fff; border:1px solid rgba(17,24,39,0.12); border-radius:12px; overflow:hidden; box-shadow: 0 12px 28px rgba(15,23,42,0.10); display:none; }
        .ac-item { padding:10px 12px; cursor:pointer; display:flex; justify-content:space-between; gap:10px; }
        .ac-item:hover { background: rgba(59,130,246,0.06); }
        .ac-meta { font-size:12px; color:#6b7280; }
    </style>

    <div class="py-10">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
            {{-- WIZARD HEADER --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="text-sm text-gray-500">Пошаговое создание</div>
                        <div class="font-semibold text-gray-900">
                            Шаг <span id="wizard_step_num">1</span> из 3
                        </div>
                        <div class="mt-2 text-sm text-gray-500" id="wizard_step_title">
                            Настройка мероприятия
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-gray-500">Выполнено</div>
                        <div class="mt-1 inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-semibold progress-pill">
                            <span id="wizard_percent">33%</span>
                        </div>
                    </div>
                </div>
                <div class="mt-4 h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                    <div id="wizard_bar" class="h-2 bg-gray-900 rounded-full" style="width: 33%;"></div>
                </div>
                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <span class="wizard-pill pill px-3 py-1 rounded-full border" id="pill_1">1) Настройка мероприятия</span>
                    <span class="wizard-pill pill px-3 py-1 rounded-full border" id="pill_2">2) Выбор локации и времени</span>
                    <span class="wizard-pill pill px-3 py-1 rounded-full border" id="pill_3">3) Доступность и шаблон</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <form method="POST" action="{{ route('events.store') }}" data-initial-step="{{ $initialStep }}" enctype="multipart/form-data">
                    @csrf

                    {{-- STEP 1 --}}
                    <div data-step="1" class="wizard-step step-shell">
                        <div class="step-card bg-white rounded-2xl border border-gray-100 p-5">
                            {{-- Admin organizer --}}
                            @if(!empty($canChooseOrganizer))
                                <div class="mb-5">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Назначение организатора (для admin)</label>
                                    <select name="organizer_id" class="w-full rounded-lg border-gray-200">
                                        <option value="">— выбрать organizer —</option>
                                        @foreach($organizers as $org)
                                            <option value="{{ $org->id }}"
                                                @selected(old('organizer_id', $prefill['organizer_id'] ?? '') == $org->id)>
                                                #{{ $org->id }} — {{ $org->name ?? $org->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Admin обязан выбрать organizer — событие будет привязано к нему.
                                    </div>
                                </div>
                            @else
                                <div class="mb-5 p-3 rounded-lg bg-gray-50 border border-gray-100 text-sm text-gray-700">
                                    <div class="font-semibold">Создание</div>
                                    <div class="mt-1">
                                        {{ $resolvedOrganizerLabel ?? '—' }}
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Название мероприятия</label>
                                    <input type="text"
                                           name="title"
                                           value="{{ old('title', $prefill['title'] ?? '') }}"
                                           class="w-full rounded-lg border-gray-200"
                                           placeholder="Напр. Вечерняя игра 6х6">
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Направление</label>
                                    <select name="direction" id="direction" class="w-full rounded-lg border-gray-200">
                                        <option value="classic" @selected(old('direction', $prefill['direction'] ?? 'classic')==='classic')>Классический волейбол</option>
                                        <option value="beach" @selected(old('direction', $prefill['direction'] ?? '')==='beach')>Пляжный волейбол</option>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Тип мероприятия</label>
                                    <select name="format" id="format" class="w-full rounded-lg border-gray-200">
                                        @foreach($formats as $k => $label)
                                            <option value="{{ $k }}" @selected(old('format', $prefill['format'] ?? 'game')===$k)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <div class="text-xs text-gray-500 mt-1">
                                        “Тренер + ученик” доступен только при “Пляжный волейбол”.
                                    </div>
                                </div>

                                {{-- ✅ TRAINER (только training/training_game) --}}
                                @php
                                  $fmt0 = (string)old('format', $prefill['format'] ?? 'game');
                                  $showTrainer0 = in_array($fmt0, ['training','training_game'], true);
                                @endphp
                                <div class="md:col-span-2" id="trainer_block" style="{{ $showTrainer0 ? '' : 'display:none;' }}">

                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Тренер</label>

                                    <div class="ac-box">
                                        <input type="text"
                                               id="trainer_search"
                                               class="w-full rounded-lg border-gray-200"
                                               placeholder="Начни вводить имя, ник, username…"
                                               value="{{ e($oldTrainerLabel) }}"
                                               autocomplete="off">

                                        <input type="hidden" name="trainer_user_id" id="trainer_user_id" value="{{ $oldTrainerId ?: '' }}">
                                        <input type="hidden" name="trainer_user_label" id="trainer_user_label" value="{{ e($oldTrainerLabel) }}">

                                        <div id="trainer_dd" class="ac-dd"></div>
                                    </div>

                                    <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                        <span>Выбранный тренер сохраняется в событии и будет виден в списке/карточке.</span>
                                        <button type="button" id="trainer_clear" class="text-blue-600 font-semibold hover:text-blue-700">Сбросить</button>
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        Поле показывается только для “Тренировка” и “Тренировка + Игра”.
                                    </div>
                                </div>

                                {{-- Game config --}}
                                <div class="md:col-span-2">
                                    <div class="p-4 rounded-xl border border-gray-100 bg-white">
                                        <div class="text-sm font-semibold text-gray-800">Игровые настройки</div>
                                        <div class="text-xs text-gray-500 mt-1" id="game_defaults_hint">
                                            Подсказки: 4×4 → 8; 4×2 → 10–12; 5×1 → 10–12 (режим либеро — ниже).
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Сейчас применяется для <span class="font-semibold">classic + “Игра”</span>.
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-semibold text-gray-700 mb-2">Подтип игры</label>
                                                <select name="game_subtype" id="game_subtype" class="w-full rounded-lg border-gray-200">
                                                    <option value="">— выбрать —</option>
                                                    <option value="4x4" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='4x4')>4×4</option>
                                                    <option value="4x2" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='4x2')>4×2</option>
                                                    <option value="5x1" @selected(old('game_subtype', $prefill['game_subtype'] ?? '')==='5x1')>5×1</option>
                                                </select>
                                            </div>

                                            <div class="flex gap-3">
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Мин. участников</label>
                                                    <input type="number"
                                                           name="game_min_players"
                                                           id="game_min_players"
                                                           value="{{ old('game_min_players', $prefill['game_min_players'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200"
                                                           min="1" max="99"
                                                           placeholder="напр. 10">
                                                    <div class="text-xs text-gray-500 mt-1" id="game_min_hint" style="display:none;"></div>
                                                </div>
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Макс. участников</label>
                                                    <input type="number"
                                                           name="game_max_players"
                                                           id="game_max_players"
                                                           value="{{ old('game_max_players', $prefill['game_max_players'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200"
                                                           min="1" max="99"
                                                           placeholder="напр. 12">
                                                    <div class="text-xs text-gray-500 mt-1" id="game_max_hint" style="display:none;"></div>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- libero_mode --}}
                                        <div id="libero_mode_block" class="mt-4 hidden">
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Режим либеро</label>
                                            <select name="game_libero_mode" id="game_libero_mode" class="w-full rounded-lg border-gray-200">
                                                <option value="with_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? 'with_libero')==='with_libero')>С либеро (отдельная позиция)</option>
                                                <option value="without_libero" @selected(old('game_libero_mode', $prefill['game_libero_mode'] ?? '')==='without_libero')>Без либеро</option>
                                            </select>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Позиции для записи будут выставлены автоматически.
                                            </div>
                                        </div>

                                        {{-- Gender policy --}}
                                        <div class="mt-4 p-4 rounded-xl border border-gray-100 bg-gray-50">
                                            <div class="text-sm font-semibold text-gray-800">Гендерные ограничения</div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                Лимит по мероприятию главный: <span class="font-semibold">max_players</span>. Гендерные лимиты — дополнительные.
                                            </div>

                                            <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Режим</label>
                                                    <select name="game_gender_policy" id="game_gender_policy" class="w-full rounded-lg border-gray-200">
                                                        <option value="mixed_open" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? 'mixed_open')==='mixed_open')>М/Ж (без ограничений)</option>
                                                        <option value="only_male" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_male')>Только М</option>
                                                        <option value="only_female" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='only_female')>Только Ж</option>
                                                        <option value="mixed_limited" @selected(old('game_gender_policy', $prefill['game_gender_policy'] ?? '')==='mixed_limited')>М/Ж (с ограничениями)</option>
                                                    </select>
                                                </div>

                                                <div id="gender_limited_side_wrap" class="hidden">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Кого ограничиваем</label>
                                                    @php
                                                        $sideVal = old('game_gender_limited_side', $prefill['game_gender_limited_side'] ?? 'female');
                                                    @endphp
                                                    <div class="flex flex-wrap gap-3 mt-1">
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="game_gender_limited_side" value="female" @checked($sideVal==='female')>
                                                            <span class="text-sm font-semibold">Ж</span>
                                                        </label>
                                                        <label class="inline-flex items-center gap-2">
                                                            <input type="radio" name="game_gender_limited_side" value="male" @checked($sideVal==='male')>
                                                            <span class="text-sm font-semibold">М</span>
                                                        </label>
                                                    </div>
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        Ограничиваемый пол получает лимит мест (ниже).
                                                    </div>
                                                </div>

                                                <div id="gender_limited_max_wrap" class="hidden">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Макс. мест для ограничиваемых</label>
                                                    <input type="number"
                                                           name="game_gender_limited_max"
                                                           id="game_gender_limited_max"
                                                           value="{{ old('game_gender_limited_max', $prefill['game_gender_limited_max'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200"
                                                           min="0" max="99"
                                                           placeholder="напр. 2">
                                                    <div class="text-xs text-gray-500 mt-1">
                                                        Если поставить 0 — ограничиваемый пол не сможет записаться.
                                                    </div>
                                                </div>
                                            </div>

                                            <div id="gender_limited_positions_wrap" class="mt-4 hidden">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div>
                                                        <div class="text-xs font-semibold text-gray-600">Позиции, доступные ограничиваемому полу</div>
                                                        <div class="text-xs text-gray-500 mt-1">
                                                            Если не выбрать ничего — значит “на любые позиции”.
                                                        </div>
                                                    </div>
                                                    <button type="button" id="gender_positions_clear" class="text-xs font-semibold text-blue-600 hover:text-blue-700">
                                                        Сбросить выбор
                                                    </button>
                                                </div>
                                                <div id="gender_positions_box" class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-2"></div>

                                                @php
                                                    $oldLimitedPositions = old('game_gender_limited_positions', $prefill['game_gender_limited_positions'] ?? []);
                                                    if (is_string($oldLimitedPositions)) $oldLimitedPositions = [$oldLimitedPositions];
                                                    if (!is_array($oldLimitedPositions)) $oldLimitedPositions = [];
                                                @endphp
                                                <input type="hidden" id="gender_positions_old_json" value="{{ e(json_encode(array_values($oldLimitedPositions))) }}">
                                            </div>

                                            {{-- legacy hidden (compat) --}}
                                            <input type="hidden" name="game_allow_girls" id="game_allow_girls_legacy" value="{{ old('game_allow_girls', $prefill['game_allow_girls'] ?? 1) ? 1 : 0 }}">
                                            <input type="hidden" name="game_girls_max" id="game_girls_max_legacy" value="{{ old('game_girls_max', $prefill['game_girls_max'] ?? '') }}">
                                        </div>
                                    </div>
                                </div>

                                {{-- Levels --}}
                                <div class="md:col-span-2">
                                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                                        <div class="font-semibold text-sm text-gray-800">Уровень допуска</div>

                                        <div id="levels_classic" class="mt-3 hidden">
                                            <div class="text-xs font-semibold text-gray-600 mb-2">Classic</div>
                                            <div class="flex gap-3">
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                                                    <input type="number" name="classic_level_min"
                                                           value="{{ old('classic_level_min', $prefill['classic_level_min'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                                </div>
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                                                    <input type="number" name="classic_level_max"
                                                           value="{{ old('classic_level_max', $prefill['classic_level_max'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                                </div>
                                            </div>
                                        </div>

                                        <div id="levels_beach" class="mt-3 hidden">
                                            <div class="text-xs font-semibold text-gray-600 mb-2">Beach</div>
                                            <div class="flex gap-3">
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                                                    <input type="number" name="beach_level_min"
                                                           value="{{ old('beach_level_min', $prefill['beach_level_min'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                                </div>
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                                                    <input type="number" name="beach_level_max"
                                                           value="{{ old('beach_level_max', $prefill['beach_level_max'] ?? '') }}"
                                                           class="w-full rounded-lg border-gray-200" min="0" max="10">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-2 text-xs text-gray-500">
                                            Если заполнены оба — диапазон “от и до”. Если заполнено одно — ограничение будет по нему.
                                        </div>
                                    </div>
                                </div>

                                {{-- allow_registration --}}
                                <div class="md:col-span-2 p-4 rounded-xl border border-gray-100 bg-white">
                                    <div class="text-sm font-semibold text-gray-800">Регистрация игроков через сервис?</div>
                                    @php
                                        $allowRegVal = old('allow_registration', $prefill['allow_registration'] ?? 1);
                                    @endphp
                                    <div class="mt-3 flex flex-col md:flex-row gap-3">
                                        <label class="inline-flex items-center gap-3">
                                            <input type="radio" name="allow_registration" value="1" @checked((string)$allowRegVal==='1')>
                                            <span class="text-sm font-semibold">Да</span>
                                            <span class="text-xs text-gray-500">(Доступно создание повторяющихся мероприятий)</span>
                                        </label>
                                        <label class="inline-flex items-center gap-3">
                                            <input type="radio" name="allow_registration" value="0" @checked((string)$allowRegVal==='0')>
                                            <span class="text-sm font-semibold">Нет</span>
                                            <span class="text-xs text-gray-500">(Только одноразовое + заглушка оплаты)</span>
                                        </label>
                                    </div>
                                    <div id="no_registration_stub" class="mt-3 hidden text-sm text-gray-700 bg-gray-50 border border-gray-100 rounded-lg p-3">
                                        <div class="font-semibold">Платное размещение (заглушка)</div>
                                        <div class="mt-1 text-xs text-gray-500">
                                            Здесь позже появится “Оплатить” и логика платного размещения, если регистрация выключена.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" class="v-btn v-btn--primary" data-next>
                                    Дальше →
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2 --}}
                    <div data-step="2" class="wizard-step hidden step-shell">
                        <div class="step-card bg-white rounded-2xl border border-gray-100 p-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Часовой пояс</label>
                                    <select name="timezone" class="w-full rounded-lg border-gray-200">
                                        @foreach($timezones as $tz)
                                            <option value="{{ $tz }}" @selected(old('timezone', $prefill['timezone'] ?? 'Europe/Moscow')===$tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Начало (локальное)</label>
                                    <input type="datetime-local"
                                           name="starts_at_local"
                                           value="{{ old('starts_at_local') }}"
                                           class="w-full rounded-lg border-gray-200">
                                    <div class="text-xs text-gray-500 mt-1">Для шаблонов можно оставить пустым.</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Окончание (локальное)</label>
                                    <input type="datetime-local"
                                           name="ends_at_local"
                                           value="{{ old('ends_at_local') }}"
                                           class="w-full rounded-lg border-gray-200">
                                    <div class="text-xs text-gray-500 mt-1">Можно оставить пустым.</div>
                                </div>

                                <div>
                                    <div class="flex items-center justify-between gap-3">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Локация</label>
                                        @if($isAdmin)
                                            <a href="{{ route('admin.locations.create') }}"
                                               class="text-sm font-semibold text-blue-600 hover:text-blue-700">
                                                + Создать локацию
                                            </a>
                                        @endif
                                    </div>

                                    <select name="location_id" id="location_id" class="w-full rounded-lg border-gray-200">
                                        <option value="">— выбрать —</option>
                                        @foreach($locations as $loc)
                                            @php
                                                $thumb = $loc->getFirstMediaUrl('photos', 'thumb');
                                                if (empty($thumb)) $thumb = $loc->getFirstMediaUrl('photos');
                                            @endphp
                                            <option
                                                value="{{ $loc->id }}"
                                                @selected((int)old('location_id', $prefill['location_id'] ?? 0)===(int)$loc->id)
                                                data-name="{{ e((string)$loc->name) }}"
                                                data-city="{{ e((string)($loc->city ?? '')) }}"
                                                data-address="{{ e((string)($loc->address ?? '')) }}"
                                                data-short="{{ e((string)($loc->short_text ?? '')) }}"
                                                data-lat="{{ $loc->lat ?? '' }}"
                                                data-lng="{{ $loc->lng ?? '' }}"
                                                data-thumb="{{ e((string)$thumb) }}"
                                            >
                                                {{ $loc->name }}@if(!empty($loc->address)) — {{ $loc->address }}@endif
                                            </option>
                                        @endforeach
                                    </select>

                                    {{-- preview --}}
                                    <div id="location_preview" class="mt-3 hidden">
                                        <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                                            <div class="aspect-[16/9] bg-gray-100">
                                                <img id="location_preview_img" src="" alt="" class="w-full h-full object-cover hidden">
                                                <div id="location_preview_noimg" class="w-full h-full flex items-center justify-center text-sm text-gray-400">
                                                    Нет фото
                                                </div>
                                            </div>
                                            <div class="p-4">
                                                <div class="font-semibold text-gray-900" id="location_preview_name"></div>
                                                <div class="mt-1 text-sm text-gray-600" id="location_preview_meta"></div>
                                                <div class="mt-2 text-sm text-gray-700" id="location_preview_short" style="display:none;"></div>
                                                <div class="mt-3 rounded-2xl overflow-hidden border border-gray-100" id="location_preview_map_wrap" style="display:none;">
                                                    <iframe
                                                        id="location_preview_map"
                                                        src=""
                                                        class="w-full"
                                                        style="height: 220px;"
                                                        loading="lazy"
                                                        referrerpolicy="no-referrer-when-downgrade"
                                                    ></iframe>
                                                </div>
                                                <div class="mt-2 text-xs text-gray-500" id="location_preview_coords" style="display:none;"></div>
                                            </div>
                                        </div>
                                    </div>

                                    @if(!$isAdmin)
                                        <div class="text-xs text-gray-500 mt-1">
                                            Локации создаёт администратор. Если нужной локации нет — напишите админу.
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- ✅ Повторение перенесено сюда (Step 2) --}}
                            <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
                                <div class="font-semibold text-sm text-gray-800">Повторение мероприятия</div>

                                <label class="mt-3 flex items-center gap-3">
                                    <input type="hidden" name="is_recurring" value="0">
                                    <input type="checkbox" name="is_recurring" value="1" id="is_recurring"
                                           @checked(old('is_recurring', $prefill['is_recurring'] ?? false))>
                                    <span class="text-sm font-semibold">Повторяющееся мероприятие</span>
                                </label>

                                <div class="text-xs text-gray-500 mt-2" id="recurrence_hint" style="display:none;">
                                    Повторения доступны только при “Регистрация через сервис: Да”.
                                </div>

                                <div id="recurrence_fields" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4" style="display:none;">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Тип повторения</label>
                                        <select name="recurrence_type" id="recurrence_type" class="w-full rounded-lg border-gray-200">
                                            <option value="">— выбрать —</option>
                                            <option value="daily" @selected(old('recurrence_type', $prefill['recurrence_type'] ?? '')==='daily')>Ежедневно</option>
                                            <option value="weekly" @selected(old('recurrence_type', $prefill['recurrence_type'] ?? '')==='weekly')>Еженедельно</option>
                                            <option value="monthly" @selected(old('recurrence_type', $prefill['recurrence_type'] ?? '')==='monthly')>Ежемесячно</option>
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Интервал</label>
                                        <input type="number"
                                               min="1" max="365"
                                               name="recurrence_interval"
                                               id="recurrence_interval"
                                               value="{{ old('recurrence_interval', $prefill['recurrence_interval'] ?? 1) }}"
                                               class="w-full rounded-lg border-gray-200"
                                               placeholder="1">
                                        <div class="text-xs text-gray-500 mt-1">
                                            Например: 1 = каждый раз, 2 = через раз (каждые 2 дня/недели/месяца)
                                        </div>
                                    </div>

                                    <div class="md:col-span-2" id="months_wrap" style="display:none;">
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Месяцы (необязательно)</label>
                                        <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
                                            @foreach($monthsMap as $num => $label)
                                                <label class="flex items-center gap-2 text-sm">
                                                    <input type="checkbox" name="recurrence_months[]" value="{{ $num }}"
                                                           @checked(in_array($num, $oldMonths, true))>
                                                    <span>{{ $label }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <div class="text-xs text-gray-500 mt-2">
                                            Если не выбирать — будет повторяться каждый месяц. Если выбрать — только в отмеченных месяцах.
                                        </div>
                                    </div>

                                    {{-- legacy hidden --}}
                                    <input type="hidden" name="recurrence_rule" value="{{ old('recurrence_rule', $prefill['recurrence_rule'] ?? '') }}">
                                </div>
                            </div>

                            <div class="mt-6 flex justify-between gap-3">
                                <button type="button" class="v-btn v-btn--secondary" data-back>
                                    ← Назад
                                </button>
                                <button type="button" class="v-btn v-btn--primary" data-next>
                                    Дальше →
                                </button>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 3 --}}
                    <div data-step="3" class="wizard-step hidden step-shell">
                        <div class="step-card bg-white rounded-2xl border border-gray-100 p-5">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 rounded-xl border border-gray-100 bg-white">
                                    <label class="flex items-center gap-3">
                                        <input type="hidden" name="is_private" value="0">
                                        <input type="checkbox" name="is_private" value="1" @checked(old('is_private', $prefill['is_private'] ?? false))>
                                        <span class="text-sm font-semibold">Приватное (доступно только по ссылке)</span>
                                    </label>
                                    <div class="text-xs text-gray-500 mt-2">
                                        В БД будет сгенерирован токен ссылки (public_token) для приватного.
                                    </div>
                                </div>

                                <div class="p-4 rounded-xl border border-gray-100 bg-white">
                                    <label class="flex items-center gap-3">
                                        <input type="hidden" name="is_paid" value="0">
                                        <input type="checkbox" name="is_paid" value="1" id="is_paid" @checked(old('is_paid', $prefill['is_paid'] ?? false))>
                                        <span class="text-sm font-semibold">Платное</span>
                                    </label>
                                    <div class="mt-3" id="price_wrap">
                                        <label class="block text-xs font-semibold text-gray-600 mb-1">Цена / условия</label>
                                        <input type="text"
                                               name="price_text"
                                               value="{{ old('price_text', $prefill['price_text'] ?? '') }}"
                                               class="w-full rounded-lg border-gray-200"
                                               placeholder="Напр. 1200₽/чел или по абонементу">
                                    </div>
                                </div>

                                <div class="p-4 rounded-xl border border-gray-100 bg-white">
                                    <label class="flex items-center gap-3">
                                        <input type="hidden" name="requires_personal_data" value="0">
                                        <input type="checkbox" name="requires_personal_data" value="1" @checked(old('requires_personal_data', $prefill['requires_personal_data'] ?? false))>
                                        <span class="text-sm font-semibold">Требовать персональные данные</span>
                                    </label>
                                    <div class="text-xs text-gray-500 mt-2">
                                        Если включено — при записи будем просить дополнительные данные.
                                    </div>
                                </div>
                            </div>

                            {{-- ✅ COVER --}}
                            <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
                                <div class="font-semibold text-sm text-gray-800">Обложка мероприятия</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    Можно загрузить файл или выбрать из вашей галереи. Если загружен файл — он важнее выбора из галереи.
                                </div>
                                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Загрузить с компьютера</label>
                                        <input type="file" name="cover_upload" accept="image/*" class="w-full rounded-lg border-gray-200">
                                        <div class="text-xs text-gray-500 mt-1">JPG/PNG/WebP, до 5MB.</div>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-700 mb-2">Или выбрать из галереи</label>
                                        <select name="cover_media_id" class="w-full rounded-lg border-gray-200">
                                            <option value="">— не выбирать —</option>
                                            @foreach(($userCovers ?? []) as $m)
                                                <option value="{{ (int)$m->id }}" @selected((int)old('cover_media_id') === (int)$m->id)>
                                                    #{{ (int)$m->id }} — {{ $m->file_name }} @if($m->collection_name) ({{ $m->collection_name }}) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="text-xs text-gray-500 mt-1">
                                            Если список пуст — значит у пользователя пока нет загруженных фото в Media Library.
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Save as template --}}
                            <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-gray-50">
                                <div class="font-semibold text-sm text-gray-800">Шаблон</div>
                                <label class="flex items-center gap-3 mt-3">
                                    <input type="hidden" name="save_as_template" value="0">
                                    <input type="checkbox"
                                           id="save_as_template_toggle"
                                           name="save_as_template"
                                           value="1"
                                           @checked(old('save_as_template'))>
                                    <span class="text-sm font-semibold">Сохранить как шаблон (is_template)</span>
                                </label>
                                <div id="save_as_template_fields" class="mt-3 hidden">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Название шаблона</label>
                                    <input type="text"
                                           name="template_name"
                                           class="w-full rounded-lg border-gray-200"
                                           value="{{ old('template_name') }}"
                                           placeholder="Напр. Classic 5×1 — вечер">
                                    <input type="hidden" name="template_payload_text" id="template_payload_text" value="{{ old('template_payload_text','') }}">
                                    <div class="text-xs text-gray-500 mt-2">
                                        Шаблон будет создан как событие с is_template=true. Даты можно не заполнять.
                                    </div>
                                </div>
                            </div>

                            <div class="mt-6 flex justify-between gap-3">
                                <button type="button" class="v-btn v-btn--secondary" data-back>
                                    ← Назад
                                </button>
                                <div class="flex gap-3">
                                    <a href="{{ route('events.index') }}" class="v-btn v-btn--secondary">Отмена</a>
                                    <button type="submit" class="v-btn v-btn--primary">Создать</button>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
         <script>
        (function () {
            function hasClass(el, c) { return el && el.classList && el.classList.contains(c); }
            function addClass(el, c) { if (el && el.classList) el.classList.add(c); }
            function removeClass(el, c) { if (el && el.classList) el.classList.remove(c); }
            function toggleClass(el, c, on) { if (!el || !el.classList) return; if (on) el.classList.add(c); else el.classList.remove(c); }
        
            function qs(sel, root) { return (root || document).querySelector(sel); }
            function qsa(sel, root) { return (root || document).querySelectorAll(sel); }
        
            function trim(s) { return String(s || '').replace(/^\s+|\s+$/g, ''); }
        
            function escHtml(s) {
                return String(s || '').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            }
        
            // ====== Base refs ======
            var dirEl = document.getElementById('direction');
            var fmtEl = document.getElementById('format');
        
            // steps
            var stepBlocks = qsa('[data-step]');
            var btnNext = qsa('[data-next]');
            var btnBack = qsa('[data-back]');
            var stepNumEl = document.getElementById('wizard_step_num');
            var stepTitleEl = document.getElementById('wizard_step_title');
            var percentEl = document.getElementById('wizard_percent');
            var barEl = document.getElementById('wizard_bar');
            var pill1 = document.getElementById('pill_1');
            var pill2 = document.getElementById('pill_2');
            var pill3 = document.getElementById('pill_3');
        
            var titles = { 1:'Настройка мероприятия', 2:'Выбор локации и времени', 3:'Доступность и шаблон' };
        
            function setActivePills(step) {
                var pills = [
                    { el: pill1, s: 1 },
                    { el: pill2, s: 2 },
                    { el: pill3, s: 3 }
                ];
                for (var i = 0; i < pills.length; i++) {
                    var p = pills[i];
                    if (!p.el) continue;
                    removeClass(p.el, 'is-active');
                    removeClass(p.el, 'is-done');
                    if (p.s < step) addClass(p.el, 'is-done');
                    else if (p.s === step) addClass(p.el, 'is-active');
                }
            }
        
            function stepPercent(step) {
                if (step === 1) return 33;
                if (step === 2) return 66;
                return 100;
            }
        
            function setBarColor(step) {
                var c = '#111827';
                if (step === 1) c = '#4f46e5';
                if (step === 2) c = '#10b981';
                if (step === 3) c = '#f59e0b';
                if (barEl) barEl.style.backgroundColor = c;
            }
        
            function showStep(step) {
                for (var i = 0; i < stepBlocks.length; i++) {
                    var b = stepBlocks[i];
                    var isActive = Number(b.getAttribute('data-step')) === step;
                    toggleClass(b, 'hidden', !isActive);
                    toggleClass(b, 'is-active', isActive);
                }
                if (stepNumEl) stepNumEl.textContent = String(step);
                if (stepTitleEl) stepTitleEl.textContent = titles[step] || '';
                var pct = stepPercent(step);
                if (barEl) barEl.style.width = pct + '%';
                if (percentEl) percentEl.textContent = pct + '%';
                setActivePills(step);
                setBarColor(step);
                try { window.scrollTo({ top: 0, behavior: 'smooth' }); } catch (e) { window.scrollTo(0,0); }
            }
        
            function getCurrentStep() {
                for (var i = 0; i < stepBlocks.length; i++) {
                    if (!hasClass(stepBlocks[i], 'hidden')) return Number(stepBlocks[i].getAttribute('data-step')) || 1;
                }
                return 1;
            }
        
            // ✅ coach_student only beach (как было)
            function syncFormatOptions() {
                var direction = dirEl ? dirEl.value : '';
                var optCoach = fmtEl ? fmtEl.querySelector('option[value="coach_student"]') : null;
                if (!optCoach) return;
                var shouldShow = (direction === 'beach');
                optCoach.disabled = !shouldShow;
                optCoach.hidden = !shouldShow;
                if (!shouldShow && fmtEl && fmtEl.value === 'coach_student') fmtEl.value = 'training';
            }
        
            // ✅ trainer field visibility
            var trainerBlock = document.getElementById('trainer_block');
            function syncTrainerVisibility() {
                var fmt = fmtEl ? trim(fmtEl.value) : '';
                var show = (fmt === 'training' || fmt === 'training_game');
                if (trainerBlock) trainerBlock.style.display = show ? '' : 'none';
            }
        
            // levels by direction
            var levelsClassic = document.getElementById('levels_classic');
            var levelsBeach = document.getElementById('levels_beach');
            function syncLevelsUI() {
                var direction = dirEl ? dirEl.value : '';
                if (levelsClassic) toggleClass(levelsClassic, 'hidden', direction !== 'classic');
                if (levelsBeach) toggleClass(levelsBeach, 'hidden', direction !== 'beach');
            }
        
            // game UI
            var gameSubtype = document.getElementById('game_subtype');
            var gameMinEl = document.getElementById('game_min_players');
            var gameMaxEl = document.getElementById('game_max_players');
            var liberoModeBlock = document.getElementById('libero_mode_block');
            var liberoModeSelect = document.getElementById('game_libero_mode');
            var gameDefaultsHint = document.getElementById('game_defaults_hint');
            var gameMinHint = document.getElementById('game_min_hint');
            var gameMaxHint = document.getElementById('game_max_hint');
        
            var subtypeMeta = {
                '4x4': { max: 8,  min: null, range: '8' },
                '4x2': { max: 12, min: 10,   range: '10–12' },
                '5x1': { max: 12, min: 10,   range: '10–12' }
            };
        
            function isEmptyInput(el) { return !el || trim(el.value) === ''; }
        
            function applySmartDefaults() {
                var st = gameSubtype ? trim(gameSubtype.value) : '';
                var meta = subtypeMeta[st];
                if (!meta) return;
                if (gameMaxEl && isEmptyInput(gameMaxEl) && typeof meta.max === 'number') gameMaxEl.value = String(meta.max);
                if (gameMinEl && isEmptyInput(gameMinEl) && typeof meta.min === 'number') gameMinEl.value = String(meta.min);
            }
        
            function updateRecommendedHints() {
                var st = gameSubtype ? trim(gameSubtype.value) : '';
                var meta = subtypeMeta[st];
        
                if (gameDefaultsHint) {
                    if (!meta) gameDefaultsHint.textContent = 'Подсказки: 4×4 → 8; 4×2 → 10–12; 5×1 → 10–12 (режим либеро — ниже).';
                    else if (st === '4x4') gameDefaultsHint.textContent = 'Подсказки: 4×4 → обычно 8 участников.';
                    else if (st === '4x2') gameDefaultsHint.textContent = 'Подсказки: 4×2 → обычно 10–12 участников.';
                    else if (st === '5x1') gameDefaultsHint.textContent = 'Подсказки: 5×1 → обычно 10–12 участников (режим либеро — ниже).';
                }
        
                var show = !!meta;
                if (gameMinHint) {
                    gameMinHint.style.display = show ? '' : 'none';
                    gameMinHint.textContent = show ? ('Рекомендуемо: ' + meta.range) : '';
                }
                if (gameMaxHint) {
                    gameMaxHint.style.display = show ? '' : 'none';
                    gameMaxHint.textContent = show ? ('Рекомендуемо: ' + meta.range) : '';
                }
            }
        
            function syncGameUI() {
                var direction = dirEl ? dirEl.value : '';
                var format = fmtEl ? fmtEl.value : '';
                var isClassicGame = (direction === 'classic' && format === 'game');
                var st = gameSubtype ? trim(gameSubtype.value) : '';
        
                if (liberoModeBlock) toggleClass(liberoModeBlock, 'hidden', !(isClassicGame && st === '5x1'));
                if (st !== '5x1' && liberoModeSelect) liberoModeSelect.value = 'with_libero';
        
                updateRecommendedHints();
            }
        
            // gender UI (оставлено как было логически, только ES5)
            var genderPolicyEl = document.getElementById('game_gender_policy');
            var limitedSideWrap = document.getElementById('gender_limited_side_wrap');
            var limitedMaxWrap = document.getElementById('gender_limited_max_wrap');
            var limitedPositionsWrap = document.getElementById('gender_limited_positions_wrap');
            var genderMaxEl = document.getElementById('game_gender_limited_max');
            var positionsBox = document.getElementById('gender_positions_box');
            var positionsOldJson = document.getElementById('gender_positions_old_json');
            var positionsClearBtn = document.getElementById('gender_positions_clear');
            var legacyAllowGirls = document.getElementById('game_allow_girls_legacy');
            var legacyGirlsMax = document.getElementById('game_girls_max_legacy');
        
            var POS_LABELS = {
                setter:   'Связующий (setter)',
                outside:  'Доигровщик (outside)',
                opposite: 'Диагональный (opposite)',
                middle:   'Центральный (middle)',
                libero:   'Либеро (libero)'
            };
        
            function positionsForSubtype() {
                var st = gameSubtype ? trim(gameSubtype.value) : '';
                var libero = liberoModeSelect ? trim(liberoModeSelect.value || 'with_libero') : 'with_libero';
        
                if (st === '4x2') return ['setter','outside'];
                if (st === '4x4') return ['setter','outside','opposite'];
                if (st === '5x1') return (libero === 'with_libero')
                    ? ['setter','outside','opposite','middle','libero']
                    : ['setter','outside','opposite','middle'];
                return [];
            }
        
            function getOldSelectedPositions() {
                try {
                    var raw = positionsOldJson ? (positionsOldJson.value || '[]') : '[]';
                    var arr = JSON.parse(raw);
                    if (!arr || !arr.length) return [];
                    var out = [];
                    for (var i = 0; i < arr.length; i++) out.push(String(arr[i]));
                    return out;
                } catch (e) {
                    return [];
                }
            }
        
            function getCurrentSelectedPositions() {
                if (!positionsBox) return [];
                var cbs = positionsBox.querySelectorAll('input[type="checkbox"][name="game_gender_limited_positions[]"]:checked');
                var out = [];
                for (var i = 0; i < cbs.length; i++) out.push(String(cbs[i].value));
                return out;
            }
        
            function buildPositionsCheckboxes() {
                if (!positionsBox) return;
        
                var list = positionsForSubtype();
                var cur = getCurrentSelectedPositions();
                var old = getOldSelectedPositions();
        
                var curMap = {};
                for (var i = 0; i < cur.length; i++) curMap[cur[i]] = true;
        
                var oldMap = {};
                for (var j = 0; j < old.length; j++) oldMap[old[j]] = true;
        
                positionsBox.innerHTML = '';
        
                if (!list.length) {
                    var div = document.createElement('div');
                    div.className = 'text-xs text-gray-500';
                    div.textContent = 'Сначала выбери подтип игры (и режим либеро для 5×1), чтобы показать список позиций.';
                    positionsBox.appendChild(div);
                    return;
                }
        
                for (var k = 0; k < list.length; k++) {
                    var key = list[k];
        
                    var label = document.createElement('label');
                    label.className = 'flex items-center gap-3 p-3 rounded-lg border border-gray-200 bg-white';
        
                    var cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'game_gender_limited_positions[]';
                    cb.value = key;
        
                    // если уже есть текущий выбор — используем его, иначе старый
                    var hasCur = (cur.length > 0);
                    cb.checked = hasCur ? !!curMap[key] : !!oldMap[key];
        
                    var span = document.createElement('span');
                    span.className = 'text-sm font-semibold text-gray-800';
                    span.textContent = POS_LABELS[key] || key;
        
                    label.appendChild(cb);
                    label.appendChild(span);
                    positionsBox.appendChild(label);
                }
            }
        
            function clearPositionsSelection() {
                if (!positionsBox) return;
                var all = positionsBox.querySelectorAll('input[type="checkbox"][name="game_gender_limited_positions[]"]');
                for (var i = 0; i < all.length; i++) all[i].checked = false;
            }
        
            function updateLegacyMappingOnly() {
                if (!legacyAllowGirls || !legacyGirlsMax) return;
        
                var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
        
                if (policy === 'only_male') {
                    legacyAllowGirls.value = '0';
                    legacyGirlsMax.value = '';
                    return;
                }
        
                legacyAllowGirls.value = '1';
        
                if (policy === 'mixed_limited') {
                    var sideEl = qs('input[name="game_gender_limited_side"]:checked');
                    var side = sideEl ? trim(sideEl.value || 'female') : 'female';
                    legacyGirlsMax.value = (side === 'female') ? String(trim(genderMaxEl ? genderMaxEl.value : '')) : '';
                } else {
                    legacyGirlsMax.value = '';
                }
            }
        
            function syncGenderLimitedBlocks() {
                var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
                var isLimited = (policy === 'mixed_limited');
        
                if (limitedSideWrap) toggleClass(limitedSideWrap, 'hidden', !isLimited);
                if (limitedMaxWrap) toggleClass(limitedMaxWrap, 'hidden', !isLimited);
                if (limitedPositionsWrap) toggleClass(limitedPositionsWrap, 'hidden', !isLimited);
        
                if (isLimited) buildPositionsCheckboxes();
                updateLegacyMappingOnly();
            }
        
            if (genderPolicyEl) genderPolicyEl.addEventListener('change', syncGenderLimitedBlocks);
        
            var sideRadios = qsa('input[name="game_gender_limited_side"]');
            for (var sr = 0; sr < sideRadios.length; sr++) {
                sideRadios[sr].addEventListener('change', syncGenderLimitedBlocks);
            }
        
            if (genderMaxEl) genderMaxEl.addEventListener('input', updateLegacyMappingOnly);
            if (positionsClearBtn) positionsClearBtn.addEventListener('click', clearPositionsSelection);
        
            if (gameSubtype) gameSubtype.addEventListener('change', function () {
                if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
            });
        
            if (liberoModeSelect) liberoModeSelect.addEventListener('change', function () {
                if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
            });
        
            // ✅ Recurrence UI (Step 2)
            var recEl = document.getElementById('is_recurring');
            var recFields = document.getElementById('recurrence_fields');
            var recType = document.getElementById('recurrence_type');
            var recInterval = document.getElementById('recurrence_interval');
            var monthsWrap = document.getElementById('months_wrap');
            var recurrenceHint = document.getElementById('recurrence_hint');
        
            function syncMonthsVisibility() {
                if (!monthsWrap || !recType) return;
                monthsWrap.style.display = (recType.value === 'monthly') ? '' : 'none';
            }
        
            function syncRecFieldsVisibility() {
                if (!recEl || !recFields) return;
                recFields.style.display = recEl.checked ? '' : 'none';
                syncMonthsVisibility();
            }
        
            if (recEl) recEl.addEventListener('change', syncRecFieldsVisibility);
            if (recType) recType.addEventListener('change', syncMonthsVisibility);
        
            // Step validation (логика та же, только ES5)
            function validateStep(step) {
                if (step === 1) {
                    var title = qs('input[name="title"]');
                    var v = title ? trim(title.value) : '';
                    if (!v) { alert('Заполни название мероприятия.'); if (title) title.focus(); return false; }
        
                    var direction = dirEl ? dirEl.value : '';
                    var format = fmtEl ? fmtEl.value : '';
        
                    if (direction === 'classic' && format === 'game') {
                        if (!gameSubtype || !trim(gameSubtype.value)) { alert('Выбери подтип игры (4×4 / 4×2 / 5×1).'); if (gameSubtype) gameSubtype.focus(); return false; }
                        if (!gameMaxEl || !trim(gameMaxEl.value)) { alert('Укажи максимум участников для игры.'); if (gameMaxEl) gameMaxEl.focus(); return false; }
        
                        var hasMin = gameMinEl && trim(gameMinEl.value) !== '';
                        var hasMax = gameMaxEl && trim(gameMaxEl.value) !== '';
                        var minP = hasMin ? Number(trim(gameMinEl.value)) : 0;
                        var maxP = hasMax ? Number(trim(gameMaxEl.value)) : 0;
        
                        if (hasMin && hasMax && !isNaN(minP) && !isNaN(maxP) && maxP < minP) {
                            alert('Макс. участников не может быть меньше Мин. участников.');
                            if (gameMaxEl) gameMaxEl.focus();
                            return false;
                        }
        
                        var policy = genderPolicyEl ? trim(genderPolicyEl.value || 'mixed_open') : 'mixed_open';
                        if (policy === 'mixed_limited') {
                            var side = qs('input[name="game_gender_limited_side"]:checked');
                            if (!side) { alert('Выбери, кого ограничиваем (М или Ж).'); return false; }
                            if (!genderMaxEl || !trim(genderMaxEl.value)) { alert('Укажи максимум мест для ограничиваемых.'); if (genderMaxEl) genderMaxEl.focus(); return false; }
                        }
                    }
        
                    function checkMinMaxPair(minName, maxName, label) {
                        var minEl = qs('input[name="' + minName + '"]');
                        var maxEl = qs('input[name="' + maxName + '"]');
                        var hasMin2 = minEl && trim(minEl.value) !== '';
                        var hasMax2 = maxEl && trim(maxEl.value) !== '';
                        if (!hasMin2 || !hasMax2) return true;
        
                        var a = Number(minEl.value);
                        var b = Number(maxEl.value);
                        if (!isNaN(a) && !isNaN(b) && b < a) {
                            alert(label + ': "До (max)" не может быть меньше "От (min)".');
                            if (maxEl) maxEl.focus();
                            return false;
                        }
                        return true;
                    }
        
                    if (!checkMinMaxPair('classic_level_min', 'classic_level_max', 'Уровень Classic')) return false;
                    if (!checkMinMaxPair('beach_level_min', 'beach_level_max', 'Уровень Beach')) return false;
        
                    return true;
                }
        
                if (step === 2) {
                    var loc = document.getElementById('location_id');
                    if (!loc || !trim(loc.value)) { alert('Выбери локацию.'); if (loc) loc.focus(); return false; }
        
                    if (recEl && recEl.checked) {
                        var t = recType ? trim(recType.value) : '';
                        var i = recInterval ? trim(recInterval.value) : '';
                        if (!t) { alert('Выбери тип повторения (ежедневно/еженедельно/ежемесячно).'); if (recType) recType.focus(); return false; }
                        if (!i) { alert('Укажи интервал повторения.'); if (recInterval) recInterval.focus(); return false; }
                    }
                    return true;
                }
        
                if (step === 3) {
                    var paidEl = document.getElementById('is_paid');
                    var isPaid = paidEl ? !!paidEl.checked : false;
                    var price = qs('input[name="price_text"]');
                    if (isPaid && (!price || !trim(price.value))) { alert('Укажи стоимость/условия оплаты (price_text).'); if (price) price.focus(); return false; }
        
                    var tplToggle = document.getElementById('save_as_template_toggle');
                    var saveTpl = tplToggle ? !!tplToggle.checked : false;
                    var tplName = qs('input[name="template_name"]');
                    if (saveTpl && (!tplName || !trim(tplName.value))) { alert('Укажи название шаблона.'); if (tplName) tplName.focus(); return false; }
        
                    return true;
                }
        
                return true;
            }
        
            for (var iN = 0; iN < btnNext.length; iN++) {
                btnNext[iN].addEventListener('click', function () {
                    var step = getCurrentStep();
                    if (!validateStep(step)) return;
                    showStep(Math.min(3, step + 1));
                });
            }
        
            for (var iB = 0; iB < btnBack.length; iB++) {
                btnBack[iB].addEventListener('click', function () {
                    var step = getCurrentStep();
                    showStep(Math.max(1, step - 1));
                });
            }
        
            // Location preview
            var sel = document.getElementById('location_id');
            var wrap = document.getElementById('location_preview');
            var img = document.getElementById('location_preview_img');
            var noimg = document.getElementById('location_preview_noimg');
            var nameEl = document.getElementById('location_preview_name');
            var metaEl = document.getElementById('location_preview_meta');
            var shortEl = document.getElementById('location_preview_short');
            var mapWrap = document.getElementById('location_preview_map_wrap');
            var mapEl = document.getElementById('location_preview_map');
            var coordsEl = document.getElementById('location_preview_coords');
        
            function updatePreview() {
                if (!sel) return;
        
                var opt = null;
                if (sel.selectedIndex >= 0) opt = sel.options[sel.selectedIndex];
        
                if (!opt || !opt.value) {
                    if (wrap) addClass(wrap, 'hidden');
                    if (mapEl) mapEl.src = '';
                    return;
                }
        
                var name = opt.getAttribute('data-name') || '';
                var city = opt.getAttribute('data-city') || '';
                var address = opt.getAttribute('data-address') || '';
                var shortText = opt.getAttribute('data-short') || '';
                var thumb = opt.getAttribute('data-thumb') || '';
                var lat = trim(opt.getAttribute('data-lat') || '');
                var lng = trim(opt.getAttribute('data-lng') || '');
        
                if (wrap) removeClass(wrap, 'hidden');
                if (nameEl) nameEl.textContent = name;
        
                var metaParts = [];
                if (city) metaParts.push(city);
                if (address) metaParts.push(address);
                if (metaEl) metaEl.textContent = metaParts.join(' • ');
        
                if (shortEl) {
                    if (trim(shortText)) { shortEl.style.display = ''; shortEl.textContent = shortText; }
                    else { shortEl.style.display = 'none'; shortEl.textContent = ''; }
                }
        
                if (thumb && img && noimg) {
                    img.src = thumb;
                    removeClass(img, 'hidden');
                    addClass(noimg, 'hidden');
                } else if (img && noimg) {
                    img.src = '';
                    addClass(img, 'hidden');
                    removeClass(noimg, 'hidden');
                }
        
                var hasCoords = (lat !== '' && lng !== '' && !isNaN(Number(lat)) && !isNaN(Number(lng)));
                if (mapWrap && mapEl && coordsEl) {
                    if (hasCoords) {
                        mapWrap.style.display = '';
                        coordsEl.style.display = '';
                        coordsEl.textContent = 'Координаты: ' + lat + ', ' + lng;
                        mapEl.src = 'https://www.openstreetmap.org/export/embed.html?layer=mapnik&marker=' +
                            encodeURIComponent(lat) + ',' + encodeURIComponent(lng) + '&zoom=16';
                    } else {
                        mapWrap.style.display = 'none';
                        coordsEl.style.display = 'none';
                        coordsEl.textContent = '';
                        mapEl.src = '';
                    }
                }
            }
        
            if (sel) sel.addEventListener('change', updatePreview);
            updatePreview();
        
            // paid UX
            var paidEl2 = document.getElementById('is_paid');
            var priceWrap = document.getElementById('price_wrap');
            function togglePaid() {
                if (!paidEl2 || !priceWrap) return;
                priceWrap.style.opacity = paidEl2.checked ? '1' : '0.45';
            }
            if (paidEl2) paidEl2.addEventListener('change', togglePaid);
            togglePaid();
        
            // template toggle
            var tplToggle2 = document.getElementById('save_as_template_toggle');
            var tplFields2 = document.getElementById('save_as_template_fields');
            function syncTplFields() {
                if (!tplToggle2 || !tplFields2) return;
                toggleClass(tplFields2, 'hidden', !tplToggle2.checked);
            }
            if (tplToggle2) tplToggle2.addEventListener('change', syncTplFields);
            syncTplFields();
        
            // allow_registration rule (affects recurring)
            var noRegStub = document.getElementById('no_registration_stub');
        
            function getAllowRegistrationValue() {
                var el = qs('input[name="allow_registration"]:checked');
                if (!el) return 1;
                return Number(el.value) === 1 ? 1 : 0;
            }
        
            function clearRecurrenceInputs() {
                if (recEl) recEl.checked = false;
                if (recType) recType.value = '';
                if (recInterval) recInterval.value = '1';
        
                var monthCbs = qsa('input[name="recurrence_months[]"]');
                for (var i = 0; i < monthCbs.length; i++) monthCbs[i].checked = false;
        
                var legacy = qs('input[name="recurrence_rule"]');
                if (legacy) legacy.value = '';
            }
        
            function enforceRegistrationRules() {
                var allowReg = getAllowRegistrationValue();
        
                if (noRegStub) toggleClass(noRegStub, 'hidden', allowReg === 1);
        
                if (recEl) {
                    if (allowReg === 0) {
                        clearRecurrenceInputs();
                        recEl.disabled = true;
                        if (recFields) recFields.style.opacity = '0.45';
                        if (recurrenceHint) recurrenceHint.style.display = '';
                    } else {
                        recEl.disabled = false;
                        if (recFields) recFields.style.opacity = '1';
                        if (recurrenceHint) recurrenceHint.style.display = 'none';
                    }
                }
                syncRecFieldsVisibility();
            }
        
            var allowRegs = qsa('input[name="allow_registration"]');
            for (var ar = 0; ar < allowRegs.length; ar++) {
                allowRegs[ar].addEventListener('change', enforceRegistrationRules);
            }
    
            // ---------- Trainer autocomplete (как города) ----------
            (function () {
                const trainerInput = document.getElementById('trainer_search');
                const trainerId    = document.getElementById('trainer_user_id');
                const trainerLabel = document.getElementById('trainer_user_label');
                const dd           = document.getElementById('trainer_dd'); // твой .ac-dd
                const clearBtn     = document.getElementById('trainer_clear');
                const fmtEl        = document.getElementById('format');
            
                if (!trainerInput || !trainerId || !dd) return;
            
                function escapeHtml(s) {
                    return String(s || '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                }
            
                function showDropdown() { 
                    dd.style.display = 'block'; 
                    
                }
                function hideDropdown() { 
                    dd.style.display = 'none'; 
                    
                }
            
                function clearResults() {
                    dd.innerHTML = '';
                }
            
                function applySelected(id, label) {
                    trainerId.value = id ? String(id) : '';
                    if (trainerInput) trainerInput.value = label || '';
                    if (trainerLabel) trainerLabel.value = label || '';
                    hideDropdown();
                }
            
                function clearTrainer() {
                    applySelected('', '');
                    clearResults();
                }
            
                if (clearBtn) clearBtn.addEventListener('click', clearTrainer);
            
                // защита от “устаревших” запросов (как у городов)
                let lastReqId = 0;
            
                async function fetchUsers(q) {
                    const reqId = ++lastReqId;
            
                    const url = new URL('/api/users/search', window.location.origin);
                    url.searchParams.set('q', q || '');
            
                    const r = await fetch(url.toString(), {
                        headers: { 'Accept': 'application/json' },
                        credentials: 'same-origin'
                    });
            
                    if (reqId !== lastReqId) return null; // устаревший — игнор
                    if (!r.ok) return null;
            
                    return await r.json();
                }
            
                function renderItems(items) {
                    const html = [];
            
                    items.forEach(item => {
                        const id = item.id;
                        const label = item.label || '';
                        const meta = item.meta || item.sub || '';
            
                        html.push(
                            '<button type="button" class="w-full text-left px-3 py-2 hover:bg-gray-50 border-b border-gray-100 trainer-item" ' +
                            'data-id="' + escapeHtml(id) + '" data-label="' + escapeHtml(label) + '">' +
                                '<div class="text-sm text-gray-900">' + escapeHtml(label) + '</div>' +
                                (meta ? '<div class="text-xs text-gray-500">' + escapeHtml(meta) + '</div>' : '') +
                            '</button>'
                        );
                    });
            
                    dd.innerHTML = html.join('');
            
                    dd.querySelectorAll('.trainer-item').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const id = btn.getAttribute('data-id');
                            const label = btn.getAttribute('data-label');
                            applySelected(id, label);
                        });
                    });
                }
            
                function debounce(fn, ms) {
                    let t = null;
                    return function (...args) {
                        clearTimeout(t);
                        t = setTimeout(() => fn.apply(this, args), ms);
                    };
                }
            
                const runSearch = debounce(async () => {
                    const q = (trainerInput.value || '').trim();
            
                    if (q.length === 0) {
                        trainerId.value = '';
                        if (trainerLabel) trainerLabel.value = '';
                        clearResults();
                        hideDropdown();
                        return;
                    }
            
                    if (q.length < 2) {
                        trainerId.value = '';
                        if (trainerLabel) trainerLabel.value = '';
                        clearResults();
                        showDropdown();
                        dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Введите ещё символы…</div>';
                        return;
                    }
            
                    trainerId.value = ''; // пока не выберут из списка — id сброшен
                    if (trainerLabel) trainerLabel.value = '';
            
                    clearResults();
                    showDropdown();
                    dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Поиск…</div>';
            
                    const data = await fetchUsers(q);
            
                    if (!data) {
                        dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Не удалось загрузить список.</div>';
                        return;
                    }
            
                    const items = Array.isArray(data) ? data : (data.items || []);
            
                    if (!items.length) {
                        dd.innerHTML = '<div class="px-3 py-3 text-sm text-gray-500">Ничего не найдено.</div>';
                        return;
                    }
            
                    renderItems(items.slice(0, 10));
                }, 220);
            
                trainerInput.addEventListener('input', () => {
                    runSearch();
                });
            
                trainerInput.addEventListener('focus', () => {
                    const q = (trainerInput.value || '').trim();
                    if (q.length >= 2) runSearch();
                });
            
                document.addEventListener('click', (e) => {
                    // закрываем если клик вне блока с инпутом/дропдауном
                    if (e.target !== trainerInput && !dd.contains(e.target)) {
                        hideDropdown();
                    }
                });
            
                trainerInput.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') hideDropdown();
                });
            
                // ✅ жёсткая проверка перед submit
                const formEl = trainerInput.closest('form');
                if (formEl) {
                    formEl.addEventListener('submit', function (e) {
                        const fmt = fmtEl ? String(fmtEl.value || '') : '';
                        const needTrainer = (fmt === 'training' || fmt === 'training_game');
            
                        if (needTrainer) {
                            const idv = String(trainerId.value || '').trim();
                            if (!idv) {
                                e.preventDefault();
                                alert('Выбери тренера из выпадающего списка.');
                                trainerInput.focus();
                                return false;
                            }
                        }
                        return true;
                    });
                }
            
                // стартовое состояние
                hideDropdown();
            })();


            // initial step (ES5)
            var formInit = qs('form[data-initial-step]');
            var initial = 1;
            if (formInit) initial = Number(formInit.getAttribute('data-initial-step') || '1');
            if (initial !== 1 && initial !== 2 && initial !== 3) initial = 1;
            showStep(initial);
        
            // events
            if (dirEl) {
                dirEl.addEventListener('change', function () {
                    syncFormatOptions();
                    syncLevelsUI();
                    syncGameUI();
                    syncTrainerVisibility();
                });
            }
        
            if (fmtEl) {
                fmtEl.addEventListener('change', function () {
                    syncGameUI();
                    syncTrainerVisibility();
                });
            }
        
            if (gameSubtype) {
                gameSubtype.addEventListener('change', function () {
                    syncGameUI();
                    applySmartDefaults();
                    updateRecommendedHints();
                });
            }
        
            // initial sync
            syncFormatOptions();
            syncLevelsUI();
            enforceRegistrationRules();
            syncGameUI();
            applySmartDefaults();
            updateRecommendedHints();
            syncGenderLimitedBlocks();
        
            // если mixed_limited — отрисовать позиции
            if (genderPolicyEl && trim(genderPolicyEl.value || '') === 'mixed_limited') buildPositionsCheckboxes();
        
            updateLegacyMappingOnly();
            syncRecFieldsVisibility();
            syncMonthsVisibility();
            syncTrainerVisibility();
        })();
        </script>

</x-app-layout>
