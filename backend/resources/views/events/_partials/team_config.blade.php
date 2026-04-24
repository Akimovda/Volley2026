{{--
    Partial: events._partials.team_config

    Блок "Команды и игровая схема" для occurrence_edit: число команд (teams_count)
    и radio-выбор подтипа схемы (subtype).

    Expects in scope:
      - $event          (Event)        — для direction (человекочитаемый лейбл)
      - $subtypes       (iterable<string>) — список доступных схем для direction
                                             (формируется в контроллере)
      - $teamsCountVal  (int)          — значение teams_count с override-логикой
      - $subtypeVal     (string|null)  — значение subtype с override-логикой

    Примечание: min_players живёт в соседнем блоке location_players, т.к. он
    про порог отмены, а не про конфигурацию команд. Это сознательно — UI на
    странице именно такой, блоки не сливаются.
--}}
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
