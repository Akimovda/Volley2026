{{--
    Partial: events._partials.level_age

    Блок "Уровень и возраст" для occurrence_edit:
    - Уровни (beach или classic по direction) — select
    - Возрастная политика — radio (синхрон с create step1)
    - Возраст детей (6-17) — при age_policy=child

    Expects in scope (effective-переменные из контроллера):
      - $event            (Event)   — для direction
      - $beachLevelMin    (int|null)
      - $beachLevelMax    (int|null)
      - $classicLevelMin  (int|null)
      - $classicLevelMax  (int|null)
      - $agePolicy        (string)  — adult|child|any
      - $childAgeMin      (int|null)
      - $childAgeMax      (int|null)

    JS (в occurrence_edit): child_age_wrap показывается при age_policy=child.
    В create использовались radio + JS applyShowIf, здесь inline style
    на wrap по старой схеме.
--}}
<div class="ramka">
    <h2 class="-mt-05">{{ __('events.occ_level_age_title') }}</h2>
    <div class="row">
        @if($event->direction === 'beach')
        <div class="col-md-3">
            <div class="card">
                <label>{{ __('events.level_min_beach') }}</label>
                <select name="beach_level_min">
                    <option value="">—</option>
                    @for($l = 1; $l <= 10; $l++)
                        <option value="{{ $l }}" @selected(old('beach_level_min', $beachLevelMin) == $l)>{{ $l }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <label>{{ __('events.level_max_beach') }}</label>
                <select name="beach_level_max">
                    <option value="">—</option>
                    @for($l = 1; $l <= 10; $l++)
                        <option value="{{ $l }}" @selected(old('beach_level_max', $beachLevelMax) == $l)>{{ $l }}</option>
                    @endfor
                </select>
            </div>
        </div>
        @else
        <div class="col-md-3">
            <div class="card">
                <label>{{ __('events.level_min_classic') }}</label>
                <select name="classic_level_min">
                    <option value="">—</option>
                    @for($l = 1; $l <= 10; $l++)
                        <option value="{{ $l }}" @selected(old('classic_level_min', $classicLevelMin) == $l)>{{ $l }}</option>
                    @endfor
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <label>{{ __('events.level_max_classic') }}</label>
                <select name="classic_level_max">
                    <option value="">—</option>
                    @for($l = 1; $l <= 10; $l++)
                        <option value="{{ $l }}" @selected(old('classic_level_max', $classicLevelMax) == $l)>{{ $l }}</option>
                    @endfor
                </select>
            </div>
        </div>
        @endif

        <div class="col-md-6">
            <div class="card">
                <label>{{ __('events.age_policy_label') }}</label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="adult" @checked(old('age_policy', $agePolicy) === 'adult')>
                    <div class="custom-radio"></div>
                    <span>{{ __('events.age_policy_adult') }}</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="child" @checked(old('age_policy', $agePolicy) === 'child')>
                    <div class="custom-radio"></div>
                    <span>{{ __('events.age_policy_child') }}</span>
                </label>
                <label class="radio-item">
                    <input type="radio" name="age_policy" value="any" @checked(old('age_policy', $agePolicy) === 'any')>
                    <div class="custom-radio"></div>
                    <span>{{ __('events.age_policy_any') }}</span>
                </label>

                <div id="occ_child_age_wrap" class="mt-1 {{ old('age_policy', $agePolicy) === 'child' ? '' : 'hidden' }}">
                    <div class="row">
                        <div class="col-6">
                            <label>{{ __('events.child_age_from') }}</label>
                            <input type="number" name="child_age_min" min="6" max="17" value="{{ old('child_age_min', $childAgeMin ?: 6) }}">
                        </div>
                        <div class="col-6">
                            <label>{{ __('events.child_age_to_short') }}</label>
                            <input type="number" name="child_age_max" min="6" max="17" value="{{ old('child_age_max', $childAgeMax ?: 17) }}">
                        </div>
                    </div>
                    <div class="f-13" style="margin-top:.25rem;opacity:.7">{{ __('events.child_age_range_short') }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
