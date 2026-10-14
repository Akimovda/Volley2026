{{-- Данные чека (receipt) в платеже ЮKassa. $s — строка настроек (PaymentSetting / PlatformPaymentSetting) --}}
<div class="card">
    <input type="hidden" name="yookassa_receipt_enabled" value="0">
    <label class="checkbox-item">
        <input type="checkbox" name="yookassa_receipt_enabled" value="1" @checked(old('yookassa_receipt_enabled', $s->yookassa_receipt_enabled ?? false))>
        <div class="custom-checkbox"></div>
        <span>Передавать данные чека (receipt) в платеже</span>
    </label>
    <div class="f-14 mt-05 mb-1" style="opacity:.6">Включите, если ЮKassa отклоняет платёж с ошибкой «Receipt is missing or illegal». Сам чек формирует ЮKassa.</div>
    @php
        $vatOptions = [1 => 'Без НДС', 2 => 'НДС 0%', 3 => 'НДС 10%', 4 => 'НДС 20%', 7 => 'НДС 5%', 8 => 'НДС 7%', 11 => 'НДС 22%'];
        $taxOptions = [1 => 'ОСН', 2 => 'УСН доход', 3 => 'УСН доход − расход', 5 => 'ЕСХН', 6 => 'Патент'];
        $vatSel = (int) old('yookassa_vat_code', $s->yookassa_vat_code ?? 1);
        $taxSel = old('yookassa_tax_system_code', $s->yookassa_tax_system_code ?? null);
    @endphp
    <label>Ставка НДС</label>
    <select name="yookassa_vat_code">
        @foreach($vatOptions as $code => $label)
        <option value="{{ $code }}" @selected($vatSel === $code)>{{ $label }}</option>
        @endforeach
    </select>
    <label class="mt-1">Система налогообложения</label>
    <select name="yookassa_tax_system_code">
        <option value="">Не передавать (АУСН, НПД/самозанятый, задана в кабинете ЮKassa)</option>
        @foreach($taxOptions as $code => $label)
        <option value="{{ $code }}" @selected((string) $taxSel === (string) $code)>{{ $label }}</option>
        @endforeach
    </select>
</div>
