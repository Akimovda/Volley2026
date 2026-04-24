{{--
    Partial: events._partials.location_players

    Блок "Локация и участники" для occurrence_edit: выбор локации
    и минимальное число игроков (порог отмены).

    Expects in scope (effective-переменные из контроллера):
      - $locations      (Collection) — список доступных локаций (id, name, address)
      - $locationId     (int|null)   — effective location_id
      - $minPlayersVal  (int|null)   — effective min_players
--}}
<div class="ramka">
    <h2 class="-mt-05">Локация и участники</h2>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <label>Локация</label>
                <select name="location_id">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" @selected(old('location_id', $locationId) == $loc->id)>
                            {{ $loc->name }} — {{ $loc->address }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>Мин. игроков</label>
                <input type="number" name="min_players" min="0" value="{{ old('min_players', $minPlayersVal) }}" placeholder="—">
                <div class="f-13" style="margin-top:.25rem">Порог отмены — если не наберётся, игра отменится</div>
            </div>
        </div>
    </div>
</div>
