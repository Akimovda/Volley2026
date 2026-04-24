{{--
    Partial: events._partials.datetime

    Блок "Дата и время" для occurrence_edit: datetime-local поле starts_at_local
    (в таймзоне серии) и два select под duration_hours / duration_minutes.

    Expects in scope:
      - $tz           (string) — таймзона серии, напр. "Europe/Moscow"
      - $startsLocal  (string) — предзаполненное значение для datetime-local
                                  (формат Y-m-d\TH:i)
      - $durH         (int)    — часы длительности (0..12)
      - $durM         (int)    — минуты длительности (0,10,15,20,30,40,45,50)

    Источник $tz и $startsLocal — контроллер (не вычисляется в @php шаблона).
--}}
<div class="ramka">
    <h2 class="-mt-05">Дата и время</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <label>Начало ({{ $tz }})</label>
                <input type="datetime-local" name="starts_at_local" value="{{ old('starts_at_local', $startsLocal) }}" required>
                @error('starts_at_local') <div class="f-13" style="margin-top:4px">{{ $message }}</div> @enderror
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
