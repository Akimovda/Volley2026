{{--
    Partial: events._partials.payment

    Блок "Оплата" для occurrence_edit: чекбокс is_paid и поле цены в одну строку.
    Способ оплаты, валюта и ссылка наследуются от серии/профиля организатора.

    Expects in scope:
      - $isPaid    (bool)
      - $priceRub  (int|string) — цена в рублях (price_minor / 100)

    JS (в occurrence_edit): id "occ_is_paid", id "occ_price_wrap"
--}}
<div class="ramka">
    <h2 class="-mt-05">Оплата</h2>
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <label>Платное мероприятие</label>
                <input type="hidden" name="is_paid" value="0">
                <label class="checkbox-item">
                    <input type="checkbox" name="is_paid" value="1" id="occ_is_paid" @checked(old('is_paid', $isPaid))>
                    <div class="custom-checkbox"></div>
                    <span>Да</span>
                </label>
            </div>
        </div>
        <div class="col-md-4" id="occ_price_wrap" style="{{ old('is_paid', $isPaid) ? '' : 'display:none' }}">
            <div class="card">
                <label>Цена (₽)</label>
                <input type="number" name="price_rub" min="0" step="1" value="{{ old('price_rub', $priceRub) }}">
                <div class="f-13" style="margin-top:.25rem;opacity:.7">Способ оплаты — из настроек серии</div>
            </div>
        </div>
    </div>
</div>
