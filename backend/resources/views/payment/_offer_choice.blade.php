{{-- Выбор способа оплаты игроком. $s — строка настроек, $withCash — предлагать ли «наличными», $available — настроенные способы --}}
@php
    $ticked = old('offer_methods', $s->offer_methods ?? []);
    $ticked = is_array($ticked) ? $ticked : [];
@endphp
<div class="card">
    <input type="hidden" name="offer_choice" value="0">
    <label class="checkbox-item">
        <input type="checkbox" name="offer_choice" value="1" @checked(old('offer_choice', $s->offer_choice ?? false))>
        <div class="custom-checkbox"></div>
        <span>Предлагать пользователю выбор способа оплаты</span>
    </label>
    <div class="f-14 mt-05 mb-1" style="opacity:.6">Если отмечено два и более настроенных способа, при оплате появится окно выбора. Если способ один — всё работает как раньше (используется основной способ).</div>
    @foreach(\App\Support\PaymentMethods::LABELS as $key => $label)
    @continue($key === 'cash' && empty($withCash))
    @php $ok = in_array($key, $available, true); @endphp
    <label class="checkbox-item" @if(!$ok) style="opacity:.5" @endif>
        <input type="checkbox" name="offer_methods[]" value="{{ $key }}" @checked(in_array($key, $ticked, true)) @disabled(!$ok)>
        <div class="custom-checkbox"></div>
        <span>{{ $label }}@if(!$ok) <em class="f-13">— не настроено</em>@endif</span>
    </label>
    @endforeach
</div>
