{{--
    Partial: events._partials.payment

    Блок "Оплата" для occurrence_edit: чекбокс is_paid и свёрнутый
    подблок с ценой, валютой, примечанием, способом оплаты и ссылкой.

    Expects in scope:
      - $isPaid         (bool)
      - $priceRub       (int|string) — цена в рублях (price_minor / 100)
      - $priceCurrency  (string)     — RUB|USD|EUR|BYN|KZT
      - $priceText      (string)     — примечание к цене
      - $paymentMethod  (string)     — ""|onsite|transfer|online|yookassa
      - $paymentLink    (string)     — URL ссылки на оплату

    JS (в occurrence_edit): id "occ_is_paid", id "occ_payment_block"
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
    </div>

    <div id="occ_payment_block" style="{{ old('is_paid', $isPaid) ? '' : 'display:none' }}">
        <div class="row">
            <div class="col-md-3">
                <div class="card">
                    <label>Цена (₽)</label>
                    <input type="number" name="price_rub" min="0" step="1" value="{{ old('price_rub', $priceRub) }}">
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <label>Валюта</label>
                    <select name="price_currency">
                        @foreach(['RUB','USD','EUR','BYN','KZT'] as $cur)
                            <option value="{{ $cur }}" @selected(old('price_currency', $priceCurrency) === $cur)>{{ $cur }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <label>Примечание к цене</label>
                    <input type="text" name="price_text" maxlength="255" value="{{ old('price_text', $priceText) }}" placeholder="Напр.: Оплата на месте">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <label>Способ оплаты</label>
                    <select name="payment_method">
                        <option value="" @selected(old('payment_method', $paymentMethod) === '')>—</option>
                        <option value="onsite" @selected(old('payment_method', $paymentMethod) === 'onsite')>На месте</option>
                        <option value="transfer" @selected(old('payment_method', $paymentMethod) === 'transfer')>Перевод</option>
                        <option value="online" @selected(old('payment_method', $paymentMethod) === 'online')>Онлайн</option>
                        <option value="yookassa" @selected(old('payment_method', $paymentMethod) === 'yookassa')>ЮKassa</option>
                    </select>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <label>Ссылка на оплату (опционально)</label>
                    <input type="url" name="payment_link" maxlength="500" value="{{ old('payment_link', $paymentLink) }}" placeholder="https://...">
                </div>
            </div>
        </div>
    </div>
</div>
