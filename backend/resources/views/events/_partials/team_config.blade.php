{{--
    Partial: events._partials.team_config

    Блок "Команды и игровая схема" для occurrence_edit:
    - Количество команд (teams_count) — number
    - Подтип схемы (subtype) — select с опциями по direction события

    Expects in scope:
      - $event          (Event)  — для direction (отображаемый лейбл)
      - $subtypes       (array)  — ключи доступных схем для direction серии
                                    (из config("volleyball.{direction}"))
      - $teamsCountVal  (int)
      - $subtypeVal     (string|null)

    Примечание: значение унаследовано от настроек серии ($gs), но можно
    поменять — тогда сохранится override в event_occurrence_game_settings.
--}}
@php
$subtypeLabels = [
    '2x2' => '2×2', '3x3' => '3×3', '4x4' => '4×4',
    '4x2' => '4×2', '5x1' => '5×1', '5x1_libero' => '5×1 ' . __('events.libero_word'),
];
@endphp
<div class="ramka">
    <h2 class="-mt-05">{{ __('events.occ_team_title') }}</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_team_count') }}</label>
                <select name="teams_count">
                    @for($t = 2; $t <= 16; $t++)
                        <option value="{{ $t }}" @selected(old('teams_count', $teamsCountVal) == $t)>{{ $t }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card">
                <label>{{ __('events.occ_team_subtype') }}</label>
                <select name="subtype" id="occ_subtype" class="w-full rounded-lg border-gray-200">
                    @foreach($subtypes as $st)
                        <option value="{{ $st }}" @selected(old('subtype', $subtypeVal) === $st)>{{ $subtypeLabels[$st] ?? $st }}</option>
                    @endforeach
                </select>
                <div class="f-13" style="margin-top:.25rem;opacity:.7">{{ __('events.occ_team_subtype_hint', ['dir' => direction_name($event->direction)]) }}</div>
            </div>
        </div>
    </div>
</div>
