{{--
    Partial: events._partials.gender

    Гендерные ограничения для occurrence_edit.
    Варианты политики фильтруются по $event->direction (серийная настройка):
      - classic: mixed_open, only_male, only_female, mixed_limited
      - beach:   mixed_open, mixed_5050, only_male, only_female

    При mixed_limited (только classic) показывается блок лимитов:
      - Кого ограничиваем (female/male)
      - Макс. мест (select 0-10)
      - Позиции — заполняются JS /js/occurrence-edit.js на основе subtype

    Expects in scope:
      - $event                  (Event)
      - $genderPolicyVal        (string)
      - $genderLimitedSideVal   (string|null) — female|male
      - $genderLimitedMaxVal    (int|null)
      - $subtypeVal             (string|null) — для JS
      - $gs                     (object) — для gender_limited_positions (initial)

    Зависимости в JS (occurrence-edit.js):
      - id "occ_gender_policy"        — select политики
      - id "occ_gender_limited_wrap"  — блок лимитов (show при mixed_limited)
      - id "occ_positions_box"        — контейнер checkbox позиций
      - id "occ_positions_old_json"   — hidden JSON со старыми/old значениями
--}}
@php
    $dir = $event->direction;
    $selectedPositions = old('gender_limited_positions', $gs->gender_limited_positions ?? []);
    if (is_string($selectedPositions)) $selectedPositions = [$selectedPositions];
    if (!is_array($selectedPositions)) $selectedPositions = [];
@endphp
<div class="ramka">
    <h2 class="-mt-05">Гендерные ограничения</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <label>Политика</label>
                <select name="gender_policy" id="occ_gender_policy" class="w-full rounded-lg border-gray-200">
                    <option value="mixed_open" @selected(old('gender_policy', $genderPolicyVal) === 'mixed_open')>М/Ж (без ограничений)</option>
                    @if($dir === 'beach')
                        <option value="mixed_5050" @selected(old('gender_policy', $genderPolicyVal) === 'mixed_5050')>Микс 50/50</option>
                    @endif
                    <option value="only_male" @selected(old('gender_policy', $genderPolicyVal) === 'only_male')>Только М</option>
                    <option value="only_female" @selected(old('gender_policy', $genderPolicyVal) === 'only_female')>Только Ж</option>
                    @if($dir === 'classic')
                        <option value="mixed_limited" @selected(old('gender_policy', $genderPolicyVal) === 'mixed_limited')>М/Ж (с ограничениями)</option>
                    @endif
                </select>
            </div>
        </div>
    </div>

    @if($dir === 'classic')
    <div id="occ_gender_limited_wrap" style="{{ old('gender_policy', $genderPolicyVal) === 'mixed_limited' ? '' : 'display:none' }}">
        <div class="row mt-1">
            <div class="col-md-4">
                <div class="card">
                    <label>Кого ограничиваем</label>
                    <label class="radio-item">
                        <input type="radio" name="gender_limited_side" value="female" @checked(old('gender_limited_side', $genderLimitedSideVal) === 'female')>
                        <div class="custom-radio"></div>
                        <span>Женщин</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="gender_limited_side" value="male" @checked(old('gender_limited_side', $genderLimitedSideVal) === 'male')>
                        <div class="custom-radio"></div>
                        <span>Мужчин</span>
                    </label>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <label>Макс. мест для ограничиваемых</label>
                    <select name="gender_limited_max">
                        <option value="">—</option>
                        @for($n = 0; $n <= 10; $n++)
                            <option value="{{ $n }}" @selected((string) old('gender_limited_max', $genderLimitedMaxVal) === (string) $n)>{{ $n }}</option>
                        @endfor
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <label>Позиции, доступные ограничиваемому полу</label>
                    <div id="occ_positions_box"></div>
                    <input type="hidden" id="occ_positions_old_json" value="{{ e(json_encode(array_values($selectedPositions))) }}">
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
