{{--
    Partial: events._partials.registration

    Блок "Регистрация" для occurrence_edit: чекбокс allow_registration,
    начало регистрации (дни до), конец (часы+мин до), запрет отмены (часы+мин до).

    Expects in scope:
      - $allowReg      (bool)  — регистрация включена
      - $regStartsDays (int)   — за сколько дней до начала открывается рег.
      - $regEndsMin    (int)   — total minutes до начала когда рег. закрывается
      - $regEndsHours  (int)   — часовая часть $regEndsMin
      - $regEndsMins   (int)   — минутная часть $regEndsMin
      - $cancelMin     (int)   — total minutes до начала для запрета отмены
      - $cancelHours   (int)   — часовая часть $cancelMin
      - $cancelMins    (int)   — минутная часть $cancelMin

    JS (в occurrence_edit): syncHM привязан к id:
      - occ_reg_ends_h/m → occ_reg_ends_min
      - occ_cancel_h/m   → occ_cancel_min
--}}
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
