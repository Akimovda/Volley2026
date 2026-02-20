{{-- resources/views/events/create.blade.php --}}
<link rel="stylesheet" href="https://unpkg.com/trix@2.1.8/dist/trix.css">

<style>
    trix-editor {
        min-height: 200px;
        background: #fff;
        border-radius: 12px;
    }

    .trix-button-group {
        border-radius: 8px;
    }
</style>

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
    // ✅ Timezones groups приходят из контроллера: $tzGroups + $tzDefault
        //    (если вдруг не пришли — не падаем)
        $timezoneGroups  = $tzGroups ?? [];
        $timezoneDefault = !empty($prefill['timezone'])
            ? (string)$prefill['timezone']
            : (string)($tzDefault ?? 'Europe/Moscow');
        
        $currentTimezone = (string) old('timezone', $timezoneDefault);
        
        $timezoneGroups = $tzGroups ?? (array) config('event_timezones.groups', []);

        $currentTimezone = old('timezone', $timezoneDefault);


    $isAdmin = (auth()->user()?->role ?? null) === 'admin';

    $step1Fields = [
        'organizer_id',
        'title','direction','format',
        // ✅ trainer
        'trainer_user_ids',      // новое
        'trainer_user_id',       // legacy оставить
        'trainer_user_label',    // чтобы ошибки лейбла тоже попадали в шаг 1 (если будут)


        'game_subtype','game_min_players','game_max_players',
        'game_libero_mode',
        'game_gender_policy','game_gender_limited_side','game_gender_limited_max','game_gender_limited_positions',
        'classic_level_min','classic_level_max',
        'beach_level_min','beach_level_max',
        'allow_registration',
        'age_policy','is_snow',
    ];
        // ✅ Step 2 fields (including registration timings)
        $step2Fields = [
            'timezone','starts_at_local','ends_at_local','location_id',
            'is_recurring','recurrence_type','recurrence_interval','recurrence_months','recurrence_rule',
            'reg_starts_days_before','reg_ends_minutes_before','cancel_lock_minutes_before',
        ];

            $step3Fields = [
            'is_private',
            'is_paid','price_text',
            'requires_personal_data',
            'remind_registration_enabled',
            'remind_registration_minutes_before',
            'show_participants',
            'cover_upload','cover_media_id',
            'description_html', // ✅ описание
        ];


            // --- wizard initial step (server-side) ---
    $initialStep = (int) session('wizard_initial_step', 0);

    // helper: ловим и обычные ошибки, и ошибки массива вида field.0
    $hasErr = function (string $field) use ($errors): bool {
        return $errors->has($field) || $errors->has($field . '.*');
    };

    if ($initialStep < 1 || $initialStep > 3) {
        $initialStep = 1;

        if ($errors->any()) {
            foreach ($step3Fields as $f) { if ($hasErr($f)) { $initialStep = 3; break; } }

            if ($initialStep === 1) {
                foreach ($step2Fields as $f) { if ($hasErr($f)) { $initialStep = 2; break; } }
            }
        } else {
            // fallback по old()
            if (
                old('timezone') || old('starts_at_local') || old('location_id') ||
                old('is_recurring') || old('recurrence_type') || old('recurrence_interval') || old('recurrence_months') ||
                old('reg_starts_days_before') || old('reg_ends_minutes_before') || old('cancel_lock_minutes_before')
            ) {
                $initialStep = 2;
            } elseif (
                old('is_private') || old('is_paid') ||
                old('requires_personal_data') ||
                old('cover_media_id') ||
                old('remind_registration_enabled') ||
                old('remind_registration_minutes_before') ||
                old('show_participants') ||
                old('description_html') // ✅ чтобы оставаться на шаге 3
            ) {
                $initialStep = 3;
            }
        }
    }

    // --- other precomputed helpers ---
    $monthsMap = [
        1=>'Янв',2=>'Фев',3=>'Мар',4=>'Апр',5=>'Май',6=>'Июн',
        7=>'Июл',8=>'Авг',9=>'Сен',10=>'Окт',11=>'Ноя',12=>'Дек'
    ];

    $oldMonths = old('recurrence_months', $prefill['recurrence_months'] ?? []);
    if (is_string($oldMonths)) $oldMonths = [$oldMonths];
    if (!is_array($oldMonths)) $oldMonths = [];
    $oldMonths = array_map('intval', $oldMonths);

    // ✅ Prefill trainers (multi + legacy fallback)
    $oldTrainerIds = old('trainer_user_ids', $prefill['trainer_user_ids'] ?? []);
    if (is_string($oldTrainerIds)) $oldTrainerIds = [$oldTrainerIds];
    if (!is_array($oldTrainerIds)) $oldTrainerIds = [];
    $oldTrainerIds = array_values(array_filter(array_unique(array_map('intval', $oldTrainerIds)), fn($id) => $id > 0));
    // ✅ Prefill tозрастные ограничения
    $oldAgePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'any');
    if (!in_array($oldAgePolicy, ['adult','child','any'], true)) $oldAgePolicy = 'any';

    
    // legacy fallback: если пришёл один trainer_user_id
    $legacyOne = (int) old('trainer_user_id', $prefill['trainer_user_id'] ?? 0);
    if ($legacyOne > 0 && !in_array($legacyOne, $oldTrainerIds, true)) {
        $oldTrainerIds[] = $legacyOne;
    }
    
    // label для инпута (теперь контроллер отдаёт trainerPrefillLabel)
    $oldTrainerLabel = (string) old('trainer_user_label', $trainerPrefillLabel ?? ($prefill['trainer_user_label'] ?? ''));


    // ✅ registration offsets defaults
    $oldRegStartsDaysBefore = (int) old('reg_starts_days_before', 3);
    $oldRegEndsMinutesBefore = (int) old('reg_ends_minutes_before', 15);
    $oldCancelLockMinutesBefore = (int) old('cancel_lock_minutes_before', 60);

    if ($oldRegStartsDaysBefore < 0) $oldRegStartsDaysBefore = 3;
    if ($oldRegEndsMinutesBefore < 0) $oldRegEndsMinutesBefore = 15;
    if ($oldCancelLockMinutesBefore < 0) $oldCancelLockMinutesBefore = 60;
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
    @if (session('private_link'))
      <div class="mb-4 p-3 rounded-lg bg-blue-50 text-blue-900 border border-blue-100">
        <div class="font-semibold">🙈 Ссылка на приватное мероприятие:</div>
        <div class="mt-1">
          <a class="text-blue-700 underline break-all" href="{{ session('private_link') }}">
            {{ session('private_link') }}
          </a>
        </div>
      </div>
    @endif
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
                    <span class="wizard-pill pill px-3 py-1 rounded-full border" id="pill_2">2) Выбор локации,времени и ограничений записи</span>
                    <span class="wizard-pill pill px-3 py-1 rounded-full border" id="pill_3">3) Доступность, описание и др.</span>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <form
                     method="POST"
                      action="{{ route('events.store') }}"
                      data-initial-step="{{ $initialStep }}"
                      data-users-search-url="{{ route('api.users.search') }}"
                      enctype="multipart/form-data"
                    >

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
                                    @php
                                      $agePolicy = (string) old('age_policy', $prefill['age_policy'] ?? 'any'); // adult|child|any
                                    @endphp
                                    
                                    <div class="md:col-span-2" id="age_policy_block">
                                      <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                                        <div class="font-semibold text-sm text-gray-800">Возрастные ограничения</div>
                                        <div class="text-xs text-gray-500 mt-1">Мероприятие для:</div>
                                    
                                        <div class="mt-3 flex flex-col sm:flex-row gap-3">
                                          <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="age_policy" value="adult" @checked($agePolicy==='adult')>
                                            <span class="text-sm font-semibold">Взрослых 👨‍🦰👩‍🦰</span>
                                          </label>
                                    
                                          <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="age_policy" value="child" @checked($agePolicy==='child')>
                                            <span class="text-sm font-semibold">Детей 👧🧒</span>
                                          </label>
                                    
                                          <label class="inline-flex items-center gap-2">
                                            <input type="radio" name="age_policy" value="any" @checked($agePolicy==='any')>
                                            <span class="text-sm font-semibold">Без ограничений 🧑‍🧑‍🧒‍🧒</span>
                                          </label>
                                        </div>
                                    
                                        {{-- ✅ Климатические условия только для пляжа + "Игра" --}}
                                        <div class="mt-4" id="climate_block" style="display:none;">
                                          <div class="font-semibold text-sm text-gray-800">Климатические условия</div>
                                    
                                          <label class="mt-3 flex items-center gap-3" id="is_snow_wrap">
                                            <input type="hidden" name="is_snow" value="0">
                                            <input type="checkbox" name="is_snow" value="1" id="is_snow"
                                                   @checked(old('is_snow', $prefill['is_snow'] ?? false))>
                                            <span class="text-sm font-semibold">Снег/зима (только для “Игра”)</span>
                                          </label>
                                        </div>
                                      </div>
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
                                  $showTrainer0 = in_array($fmt0, ['training','training_game','training_pro_am','camp','coach_student'], true);
                                @endphp
                                <div class="md:col-span-2" id="trainer_block" style="{{ $showTrainer0 ? '' : 'display:none;' }}">

                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Тренеры</label>

                                        <div class="ac-box">
                                            {{-- chips --}}
                                            <div id="trainer_chips" class="mb-2 flex flex-wrap gap-2">
                                                @foreach($oldTrainerIds as $tid)
                                                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-gray-100 border border-gray-200 text-sm">
                                                        <span>#{{ (int)$tid }}</span>
                                                        <button type="button" class="trainer-chip-remove text-gray-500 hover:text-gray-800" data-id="{{ (int)$tid }}">×</button>
                                                    </span>
                                                    <input type="hidden" name="trainer_user_ids[]" value="{{ (int)$tid }}" data-trainer-hidden="{{ (int)$tid }}">
                                                @endforeach
                                            </div>
                                        
                                            <input type="text"
                                                   id="trainer_search"
                                                   class="w-full rounded-lg border-gray-200"
                                                   placeholder="Начни вводить имя, ник, username…"
                                                   value=""
                                                   autocomplete="off">
                                        
                                            {{-- legacy hidden (первый тренер, чтобы старые места не ломались) --}}
                                            <input type="hidden" name="trainer_user_id" id="trainer_user_id_legacy" value="{{ $oldTrainerIds[0] ?? '' }}">
                                            <input type="hidden" name="trainer_user_label" id="trainer_user_label" value="{{ e($oldTrainerLabel) }}">
                                        
                                            <div id="trainer_dd" class="ac-dd"></div>
                                        </div>
                                        
                                        <div class="mt-2 flex items-center gap-2 text-xs text-gray-500">
                                            <span>Можно выбрать несколько тренеров.</span>
                                            <button type="button" id="trainer_clear" class="text-blue-600 font-semibold hover:text-blue-700">Сбросить</button>
                                        </div>
                                        
                                        <div class="text-xs text-gray-500 mt-1">
                                            Поле показывается только для “Тренировка”, “Тренировка + Игра”, “Тренер + ученик”, “Кемп”.
                                        </div>
                                </div>

                                {{-- Game config --}}
                                <div class="md:col-span-2">
                                    <div class="p-4 rounded-xl border border-gray-100 bg-white">
                                        <div class="text-sm font-semibold text-gray-800">Игровые настройки</div>
                                        <div class="text-xs text-gray-500 mt-1" id="game_defaults_hint">
                                            Подсказки: 4×4 → 8; 4×2 → 10–12; 5×1 → 6–12 (режим либеро — ниже).
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
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                                                <input type="number"
                                                       name="game_min_players"
                                                       id="game_min_players"
                                                       min="0" max="99"
                                                       value="{{ old('game_min_players', $prefill['game_min_players'] ?? '') }}"
                                                       class="w-full rounded-lg border-gray-200"
                                                       placeholder="например 6">
                                                <div id="game_min_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
                                              </div>
                                            
                                              <div class="w-1/2">
                                                <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                                                <input type="number"
                                                       name="game_max_players"
                                                       id="game_max_players"
                                                       min="1" max="99"
                                                       value="{{ old('game_max_players', $prefill['game_max_players'] ?? '') }}"
                                                       class="w-full rounded-lg border-gray-200"
                                                       placeholder="например 12">
                                                <div id="game_max_hint" class="text-xs text-gray-500 mt-1" style="display:none;"></div>
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
                                                    <div id="gender_5050_hint" class="text-sm text-gray-500 mt-2 hidden"></div>
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
                                @php
                                    $classicMin = old('classic_level_min', $prefill['classic_level_min'] ?? null);
                                    $classicMax = old('classic_level_max', $prefill['classic_level_max'] ?? null);
                                    $beachMin   = old('beach_level_min',   $prefill['beach_level_min'] ?? null);
                                    $beachMax   = old('beach_level_max',   $prefill['beach_level_max'] ?? null);
                                @endphp
                                
                                <div class="md:col-span-2">
                                    <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                                        <div class="font-semibold text-sm text-gray-800">Уровень допуска</div>
                                        <div id="levels_classic" class="mt-3 hidden">
                                          <div class="text-xs font-semibold text-gray-600 mb-2">🏐 Classic (Классический волейбол)</div>
                           
                                          <div class="flex gap-3">
                                            <div class="w-1/2">
                                              <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                                              <select name="classic_level_min" class="w-full rounded-lg border-gray-200">
                                               <option value="">—</option>
                                                  @for ($i = 1; $i <= 7; $i++)
                                                    <option value="{{ $i }}" @selected((string)$classicMin === (string)$i)>{{ $i }}</option>
                                                  @endfor
                                             </select>
                                            </div>
                                        
                                            <div class="w-1/2">
                                              <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                                              <select name="classic_level_max" class="w-full rounded-lg border-gray-200">
                                                  <option value="">—</option>
                                                  @for ($i = 1; $i <= 7; $i++)
                                                    <option value="{{ $i }}" @selected((string)$classicMax === (string)$i)>{{ $i }}</option>
                                                  @endfor
                                                </select>
                                            </div>
                                          </div>
                                        </div>

                                
                                        <div id="levels_beach" class="mt-3 hidden">
                                            <div class="text-xs font-semibold text-gray-600 mb-2">🏝 Beach (Пляжный волейбол)</div>
                                            <div class="flex gap-3">
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">От (min)</label>
                                                    <select name="beach_level_min" class="w-full rounded-lg border-gray-200">
                                                        <option value="">-</option>
                                                        @for ($i = 1; $i <= 7; $i++)
                                                            <option value="{{ $i }}" @selected((string)$beachMin === (string)$i)>{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                    @error('beach_level_min')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                                </div>
                                
                                                <div class="w-1/2">
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1">До (max)</label>
                                                    <select name="beach_level_max" class="w-full rounded-lg border-gray-200">
                                                        <option value="">-</option>
                                                        @for ($i = 1; $i <= 7; $i++)
                                                            <option value="{{ $i }}" @selected((string)$beachMax === (string)$i)>{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                    @error('beach_level_max')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                                                </div>
                                            </div>
                                        </div>
                                
                                        <div class="mt-2 text-xs text-gray-500">
                                            Если выбраны оба — диапазона “от и до”. Если заполнено одно — ограничение будет по нему.
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
                                          @forelse(($timezoneGroups ?? []) as $groupLabel => $items)
                                            <optgroup label="{{ $groupLabel }}">
                                              @foreach(($items ?? []) as $tzValue => $tzLabel)
                                                <option value="{{ $tzValue }}" @selected($currentTimezone === $tzValue)>
                                                  {{ $tzLabel }}
                                                </option>
                                              @endforeach
                                            </optgroup>
                                          @empty
                                            <option value="Europe/Moscow" @selected($currentTimezone === 'Europe/Moscow')>
                                              Москва (UTC+3) — Europe/Moscow
                                            </option>
                                            <option value="UTC" @selected($currentTimezone === 'UTC')>
                                              UTC (UTC+0) — UTC
                                            </option>
                                          @endforelse
                                        </select>
                                        @error('timezone')
                                          <div class="text-xs text-red-600 mt-1">{{ $message }}</div>
                                        @enderror

                                    <div class="text-xs text-gray-500 mt-1">
                                      Хранится как IANA timezone (например: <span class="font-mono">Europe/Moscow</span>).
                                    </div>

                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Начало (локальное)</label>
                                    <input type="datetime-local"
                                           name="starts_at_local"
                                           value="{{ old('starts_at_local') }}"
                                           class="w-full rounded-lg border-gray-200">
                                    <div class="text-xs text-gray-500 mt-1">Обязательное поле.</div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Окончание (локальное)</label>
                                    <input type="datetime-local"
                                           name="ends_at_local"
                                           value="{{ old('ends_at_local') }}"
                                           class="w-full rounded-lg border-gray-200">
                                    <div class="text-xs text-gray-500 mt-1">Можно оставить пустым.</div>
                                </div>
                                {{-- ✅ Registration timings (Step 2) --}}
                                <div class="md:col-span-2 mt-2 p-4 rounded-xl border border-gray-100 bg-gray-50" id="reg_timing_box">
                                    <div class="font-semibold text-sm text-gray-800">Окно регистрации</div>
                                    <div class="text-xs text-gray-500 mt-1">
                                        Эти настройки применяются только если в шаге 1 выбрано “Регистрация игроков через сервис: Да”.
                                        Время считается от <span class="font-semibold">начала мероприятия</span>.
                                    </div>
                                
                                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Начало регистрации (дней до)</label>
                                            <input type="number"
                                                   name="reg_starts_days_before"
                                                   id="reg_starts_days_before"
                                                   min="0" max="365"
                                                   value="{{ $oldRegStartsDaysBefore }}"
                                                   class="w-full rounded-lg border-gray-200">
                                            <div class="text-xs text-gray-500 mt-1">По умолчанию: 3 дня.</div>
                                        </div>
                                
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Окончание регистрации (минут до)</label>
                                            <input type="number"
                                                   name="reg_ends_minutes_before"
                                                   id="reg_ends_minutes_before"
                                                   min="0" max="10080"
                                                   value="{{ $oldRegEndsMinutesBefore }}"
                                                   class="w-full rounded-lg border-gray-200">
                                            <div class="text-xs text-gray-500 mt-1">По умолчанию: 15 минут.</div>
                                        </div>
                                
                                        <div>
                                            <label class="block text-xs font-semibold text-gray-600 mb-1">Запрет отмены записи (минут до)</label>
                                            <input type="number"
                                                   name="cancel_lock_minutes_before"
                                                   id="cancel_lock_minutes_before"
                                                   min="0" max="10080"
                                                   value="{{ $oldCancelLockMinutesBefore }}"
                                                   class="w-full rounded-lg border-gray-200">
                                            <div class="text-xs text-gray-500 mt-1">По умолчанию: 60 минут.</div>
                                        </div>
                                    </div>
                                
                                    <div class="mt-3 text-xs text-gray-500">
                                        Пример: “Запрет отмены 60 минут” → за час до начала кнопка отмены станет недоступной.
                                    </div>
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
                            {{-- ✅ Notifications + participants visibility --}}
                            <div class="mt-6 p-4 rounded-xl border border-gray-100 bg-white">
                              <div class="font-semibold text-sm text-gray-800">Уведомления и видимость</div>
                            
                              @php
                                $remEnabled = (bool) old('remind_registration_enabled', $prefill['remind_registration_enabled'] ?? true);
                                $remMin = (int) old('remind_registration_minutes_before', $prefill['remind_registration_minutes_before'] ?? 600);
                                if ($remMin < 0) $remMin = 600;
                                $showParts = (bool) old('show_participants', $prefill['show_participants'] ?? true);
                              @endphp
                            
                              <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                                  <div class="font-semibold text-sm text-gray-800">Напоминание игроку о записи</div>
                            
                                  <label class="mt-3 flex items-center gap-3">
                                    <input type="hidden" name="remind_registration_enabled" value="0">
                                    <input type="checkbox" name="remind_registration_enabled" value="1" id="remind_registration_enabled"
                                           @checked($remEnabled)>
                                    <span class="text-sm font-semibold">Включено</span>
                                  </label>
                            
                                  <div class="mt-3">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">За сколько минут до начала</label>
                                    <input type="number"
                                           name="remind_registration_minutes_before"
                                           min="0" max="10080"
                                           value="{{ $remMin }}"
                                           class="w-full rounded-lg border-gray-200">
                                    <div class="text-xs text-gray-500 mt-1">По умолчанию: 600 минут (10 часов). Каналы: Telegram и VK.</div>
                                  </div>
                                </div>
                            
                                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
                                  <div class="font-semibold text-sm text-gray-800">Показывать список записавшихся</div>
                            
                                  <div class="mt-3 flex flex-col gap-2">
                                    <label class="inline-flex items-center gap-2">
                                      <input type="radio" name="show_participants" value="1" @checked($showParts)>
                                      <span class="text-sm font-semibold">Да</span>
                                    </label>
                                    <label class="inline-flex items-center gap-2">
                                      <input type="radio" name="show_participants" value="0" @checked(!$showParts)>
                                      <span class="text-sm font-semibold">Нет</span>
                                    </label>
                                  </div>
                            
                                  <div class="text-xs text-gray-500 mt-2">
                                    Если “Нет” — на странице события список участников не показываем.
                                  </div>
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
                            {{-- STEP 3: Описание мероприятия --}}
                            <div class="mt-4">
                                <label class="block text-sm font-medium mb-2">Описание мероприятия</label>
                            
                                {{-- Важно: hidden input + trix-editor --}}
                                <input id="description_html" type="hidden" name="description_html"
                                     value="{{ old('description_html', $prefill['description_html'] ?? '') }}">
                                     
                                <trix-editor input="description_html" class="trix-content"></trix-editor>
                            
                                @error('description_html')
                                    <div class="text-red-600 text-sm mt-2">{{ $message }}</div>
                                @enderror
                            
                                <div class="text-gray-500 text-xs mt-2">
                                    Можно форматировать текст: жирный/курсив, списки, ссылки.
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
   
   {{-- Trix (CDN) --}}
  <script src="https://unpkg.com/trix@2.1.8/dist/trix.umd.min.js"></script>

  {{-- запрет загрузки файлов/картинок в trix --}}
  <script>
    document.addEventListener("trix-file-accept", function (event) {
      event.preventDefault();
    });
  </script>

  {{-- Page JS --}}
  <script src="{{ asset('js/events-create.js') }}?v={{ filemtime(public_path('js/events-create.js')) }}"></script>
</x-app-layout>
