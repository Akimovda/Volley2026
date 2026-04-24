{{--
    Partial: events._partials.gender

    Блок "Гендерные ограничения" для occurrence_edit: radio-выбор
    политики (any / men_only / women_only / women_limited) и свёрнутый
    подблок лимитов — показывается только при women_limited.

    Expects in scope:
      - $genderPolicyVal       (string)  — any|men_only|women_only|women_limited
      - $girlsMaxVal           (int|null)
      - $genderLimitedMaxVal   (int|null)
      - $genderLimitedSideVal  (string|null) — ""|"women"|"men"

    Зависимости в shared JS (живёт в самом occurrence_edit.blade.php):
      - id "gender_limited_wrap" — row с лимитами, показывается при выборе
                                    radio gender_policy=women_limited.
      - id "gp_any", "gp_limited" — не используются JS сейчас, но оставлены
                                     на случай будущих хуков.
    ЭТИ id ТРОГАТЬ НЕЛЬЗЯ.
--}}
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
