@php
    $durationSec = $occurrence->duration_sec ?: ($event->duration_sec ?: 7200);
    $durH = (int) floor($durationSec / 3600);
    $durM = (int) floor(($durationSec % 3600) / 60);

    $allowReg = !is_null($occurrence->allow_registration) ? (bool) $occurrence->allow_registration : (bool) ($event->allow_registration ?? false);
    $maxPlayers = $occurrence->max_players ?? $event->gameSettings?->max_players ?? 0;

    // Регистрация: вычисляем из дат occurrence, fallback из event, fallback defaults
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
    <x-slot name="t_description">Изменения применяются только к этой дате, не затрагивая остальные повторения.</x-slot>

    <div class="container form">
        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if(session('error'))
        <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        <form action="{{ route('events.occurrences.update', [$event, $occurrence]) }}" method="POST">
            @csrf @method('PUT')

            <div class="ramka">
                <h2 class="-mt-05">Дата и время</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Начало ({{ $tz }})</label>
                            <input type="datetime-local" name="starts_at_local" value="{{ old('starts_at_local', $startsLocal) }}" required>
                            @error('starts_at_local') <div class="f-12" style="color:#dc2626;margin-top:4px">{{ $message }}</div> @enderror
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
                            <label>Макс. игроков</label>
                            <input type="number" name="max_players" min="0" value="{{ old('max_players', $maxPlayers) }}">
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
                            <select name="age_policy">
                                <option value="adult" @selected(old('age_policy', $occurrence->age_policy ?? $event->age_policy) === 'adult')>Взрослые</option>
                                <option value="child" @selected(old('age_policy', $occurrence->age_policy ?? $event->age_policy) === 'child')>Дети</option>
                                <option value="any" @selected(old('age_policy', $occurrence->age_policy ?? $event->age_policy) === 'any')>Все</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
    });
    </script>
    </x-slot>
</x-voll-layout>
