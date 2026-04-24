{{--
    Partial: events._partials.location_players

    Блок "Локация и участники" для occurrence_edit: выбор локации,
    минимальное число игроков (порог отмены) и тумблер видимости участников.

    Expects in scope:
      - $locations      (Collection) — список доступных локаций (id, name, address)
      - $occurrence     (EventOccurrence)
      - $event          (Event)
      - $minPlayersVal  (int|null) — значение min_players с override-логикой
      - $showParts      (bool)     — значение show_participants с override-логикой
--}}
<div class="ramka">
    <h2 class="-mt-05">Локация и участники</h2>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <label>Локация</label>
                <select name="location_id">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(old('location_id', $occurrence->location_id ?? $event->location_id) == $loc->id)>
                            {{ $loc->name }} — {{ $loc->address }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <label>Мин. игроков (для отмены)</label>
                <input type="number" name="min_players" min="0" value="{{ old('min_players', $minPlayersVal) }}" placeholder="—">
                <div class="f-13" style="margin-top:.25rem">Если не наберётся — игра отменится</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <label>Показывать участников</label>
                <input type="hidden" name="show_participants" value="0">
                <label class="checkbox-item">
                    <input type="checkbox" name="show_participants" value="1" @checked(old('show_participants', $showParts))>
                    <div class="custom-checkbox"></div>
                    <span>Да</span>
                </label>
            </div>
        </div>
    </div>
</div>
