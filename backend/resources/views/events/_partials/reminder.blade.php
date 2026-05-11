{{--
    Partial: events._partials.reminder

    Блок "Напоминание" для occurrence_edit: чекбокс включения
    и часы+минуты до начала.

    Expects in scope:
      - $remEnabled (bool) — напоминание включено
      - $remMin     (int)  — total minutes до начала
      - $remH       (int)  — часовая часть
      - $remM       (int)  — минутная часть

    JS (в occurrence_edit): syncHM привязан к id:
      - occ_rem_h/m → occ_rem_min
--}}
<div class="ramka">
    <h2 class="-mt-05">{{ __('events.occ_remind_title') }}</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <input type="hidden" name="remind_registration_enabled" value="0">
                <label class="checkbox-item">
                    <input type="checkbox" name="remind_registration_enabled" value="1" @checked(old('remind_registration_enabled', $remEnabled))>
                    <div class="custom-checkbox"></div>
                    <span>{{ __('events.occ_remind_enabled') }}</span>
                </label>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_remind_when') }}</label>
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
                <div id="occ_rem_fire_hint" class="f-16 cd b-600 mt-1"></div>
            </div>
        </div>
    </div>
</div>
