{{--
    Partial: events._partials.level_age

    Блок "Уровень и возраст" для occurrence_edit:
    - Уровни (beach или classic по direction) — select
    - Возрастная политика — radio (синхрон с create step1)
    - Возраст детей (6-17) — при age_policy=child

    Expects in scope:
      - $event       (Event)   — для direction
      - $occurrence  (EventOccurrence)
      - $agePolicy   (string)  — adult|child|any
      - $childMin    (int|null)
      - $childMax    (int|null)

    JS (в occurrence_edit): child_age_wrap показывается при age_policy=child.
    В create использовались radio + JS applyShowIf, здесь inline style
    на wrap по старой схеме.
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

        <div class="col-md-6">
            <div class="card">
                <label>Возрастные ограничения</label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="adult" @checked(old('age_policy', $agePolicy) === 'adult')>
                    <div class="custom-radio"></div>
                    <span>Для взрослых</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="child" @checked(old('age_policy', $agePolicy) === 'child')>
                    <div class="custom-radio"></div>
                    <span>Для детей</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="any" @checked(old('age_policy', $agePolicy) === 'any')>
                    <div class="custom-radio"></div>
                    <span>Без ограничений</span>
                </label>

                <div id="occ_child_age_wrap" class="mt-1 {{ old('age_policy', $agePolicy) === 'child' ? '' : 'hidden' }}">
                    <div class="row">
                        <div class="col-6">
                            <label>Возраст от</label>
                            <input type="number" name="child_age_min" min="6" max="17" value="{{ old('child_age_min', $childMin ?: 6) }}">
                        </div>
                        <div class="col-6">
                            <label>до</label>
                            <input type="number" name="child_age_max" min="6" max="17" value="{{ old('child_age_max', $childMax ?: 17) }}">
                        </div>
                    </div>
                    <div class="f-13" style="margin-top:.25rem;opacity:.7">Допустимый возраст: от 6 до 17 лет</div>
                </div>
            </div>
        </div>
    </div>
</div>
