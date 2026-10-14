{{-- Комиссия эквайринга (по способам оплаты) и налог. $s — строка настроек, $p — префикс ('' | 'sub_' | 'ad_') --}}
@php
    $feeRaw  = $s->{$p . 'fee_methods'} ?? null;
    $feeVals = is_array($feeRaw) ? $feeRaw : \App\Support\PaymentPricing::DEFAULT_FEE;
    $feeMode = old($p . 'fee_mode', $s->{$p . 'fee_mode'} ?? 'included');
    $taxMode = old($p . 'tax_mode', $s->{$p . 'tax_mode'} ?? 'included');
@endphp
<div class="col-md-6">
    <div class="card">
        <label class="b-600">Комиссия эквайринга (ЮKassa), % по способам оплаты</label>
        @foreach(\App\Support\PaymentPricing::METHODS as $key => $label)
        <div class="mt-1">
            <label class="f-14">{{ $label }}</label>
            <input type="number" min="0" max="50" step="0.01" name="{{ $p }}fee_m_{{ $key }}"
                   value="{{ old($p . 'fee_m_' . $key, $feeVals[$key] ?? '') }}" placeholder="не предлагать">
        </div>
        @endforeach
        <div class="f-14 mt-1" style="opacity:.6">Пустое поле — способ не предлагается (в режиме «сверху»). Тариф ЮKassa: карты, ЮMoney, SberPay, T-Pay — 2,8% + 1% = 3,8%; СБП — 0,7% + 1,5% = 2,2%. Минимум/максимум комиссии не учитываются.</div>
        <label class="radio-item mt-1">
            <input type="radio" name="{{ $p }}fee_mode" value="included" @checked($feeMode !== 'on_top')>
            <div class="custom-radio"></div>
            <span>Включена в стоимость (плательщик платит обычную цену, любой способ)</span>
        </label>
        <label class="radio-item">
            <input type="radio" name="{{ $p }}fee_mode" value="on_top" @checked($feeMode === 'on_top')>
            <div class="custom-radio"></div>
            <span>Добавляется сверху — плательщик сначала выбирает способ оплаты</span>
        </label>
        @error($p . 'fee_mode')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
    </div>
</div>
<div class="col-md-6">
    <div class="card">
        @php $taxSys = old($p . 'tax_system', $s->{$p . 'tax_system'} ?? ''); @endphp
        <label class="b-600">Система налогообложения</label>
        <select name="{{ $p }}tax_system" id="{{ $p }}tax_system">
            <option value="">Не выбрана (ставка вручную)</option>
            @foreach(\App\Support\PaymentPricing::TAX_SYSTEMS as $key => [$label, $rate])
            <option value="{{ $key }}" data-rate="{{ $rate }}" @selected($taxSys === $key)>{{ $label }}{{ $rate ? ' — ' . $rate . '%' : '' }}</option>
            @endforeach
        </select>
        <div class="f-14 mt-05" style="opacity:.6">При выборе подставляется типовая ставка, её можно изменить. Для АУСН «Доходы минус расходы» минимум 3% от доходов не рассчитывается — ставка применяется к цене.</div>
        <label class="b-600 mt-1">Налог, %</label>
        <input type="number" id="{{ $p }}tax_percent" name="{{ $p }}tax_percent" min="0" max="50" step="0.01"
               value="{{ old($p . 'tax_percent', $s->{$p . 'tax_percent'} ?? 0) }}" placeholder="Например 6">
        <label class="radio-item mt-1">
            <input type="radio" name="{{ $p }}tax_mode" value="included" @checked($taxMode !== 'on_top')>
            <div class="custom-radio"></div>
            <span>Включён в стоимость</span>
        </label>
        <label class="radio-item">
            <input type="radio" name="{{ $p }}tax_mode" value="on_top" @checked($taxMode === 'on_top')>
            <div class="custom-radio"></div>
            <span>Добавляется сверху к цене</span>
        </label>
        @error($p . 'tax_percent')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
    </div>
</div>
<script>
(function () {
    var sel = document.getElementById(@json($p . 'tax_system'));
    var pct = document.getElementById(@json($p . 'tax_percent'));
    if (!sel || !pct) return;
    sel.addEventListener('change', function () {
        var o = sel.options[sel.selectedIndex];
        if (o && o.dataset.rate) pct.value = o.dataset.rate;
    });
})();
</script>
