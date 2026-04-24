{{--
    Partial: events._partials.personal_data

    Блок "Персональные данные" для occurrence_edit: чекбокс
    requires_personal_data — требовать согласие на обработку ПД при записи.

    Expects in scope:
      - $reqPersonal (bool) — значение с override-логикой
--}}
<div class="ramka">
    <h2 class="-mt-05">Персональные данные</h2>
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <input type="hidden" name="requires_personal_data" value="0">
                <label class="checkbox-item">
                    <input type="checkbox" name="requires_personal_data" value="1" @checked(old('requires_personal_data', $reqPersonal))>
                    <div class="custom-checkbox"></div>
                    <span>Требовать согласие на обработку ПД при записи</span>
                </label>
            </div>
        </div>
    </div>
</div>
