{{--
    Partial: events._partials.location_players

    Блок "Локация и участники" для occurrence_edit: выбор локации
    и минимальное число игроков (порог отмены).

    Expects in scope (effective-переменные из контроллера):
      - $locations      (Collection) — список доступных локаций (id, name, address, city — с city для терминологии уровня)
      - $locationId     (int|null)   — effective location_id
      - $minPlayersVal  (int|null)   — effective min_players

    Каждая option несёт data-level-scope (standard|spb) по городу локации — JS в
    occurrence_edit.blade.php слушает change на #occ_location_id и пересчитывает
    названия уровней в select'ах level_age.blade.php (см. window.__levelTerminologyNames).
--}}
<div class="ramka">
    <h2 class="-mt-05">{{ __('events.occ_loc_players_title') }}</h2>
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <label>{{ __('events.location_label') }}</label>
                <select name="location_id" id="occ_location_id">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->id }}" data-level-scope="{{ level_terminology_scope_for_city($loc->city) }}" @selected(old('location_id', $locationId) == $loc->id)>
                            {{ $loc->name }} — {{ $loc->address }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <label>{{ __('events.occ_min_players') }}</label>
                <input type="number" name="min_players" min="0" value="{{ old('min_players', $minPlayersVal) }}" placeholder="—">
                <div class="f-13" style="margin-top:.25rem">{{ __('events.occ_min_players_hint') }}</div>
            </div>
        </div>
    </div>
</div>
