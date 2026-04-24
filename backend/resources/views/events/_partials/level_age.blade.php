{{--
    Partial: events._partials.level_age

    Блок "Уровень и возраст" для occurrence_edit: уровень игры
    (beach_level_min/max для пляжа или classic_level_min/max для классики),
    возрастная политика (adult/child/any) и свёрнутый подблок возраста детей.

    Expects in scope:
      - $event       (Event)  — для direction (beach vs classic ветка)
      - $occurrence  (EventOccurrence)  — для override значений уровней
      - $agePolicy   (string) — adult|child|any с override-логикой
      - $childMin    (int|null) — возраст детей min с override-логикой
      - $childMax    (int|null) — возраст детей max с override-логикой

    Зависимости в shared JS (живёт в самом occurrence_edit.blade.php):
      - id "occ_age_policy"    — селект возрастной политики
      - id "occ_child_age_row" — row с child_age_min/max, показывается
                                 при age_policy === 'child'
    Эти id ТРОГАТЬ НЕЛЬЗЯ — на них завязан обработчик change.

    Примечание: уровни (beach_level_* / classic_level_*) читаются напрямую
    через $occurrence->X ?? $event->X, а не через @php-переменную, —
    копируем поведение исходного шаблона 1:1.
--}}
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
