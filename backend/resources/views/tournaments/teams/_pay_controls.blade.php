{{-- Оплата участия в турнире: $event, $team, $payment (Payment|null) — платёж ТЕКУЩЕГО плательщика --}}
@php
    $payPs      = \App\Models\PaymentSetting::where('organizer_id', $event->organizer_id)->first();
    $payOffered = $payPs?->offeredMethods() ?? [];
    $payChoices = \App\Support\PaymentMethods::forJs($payOffered);
    $payFee     = $payment ? ($payment->fee_minor + $payment->tax_minor) : 0;
@endphp
@if(!$payment)
    <form method="POST" action="{{ route('tournamentTeams.pay', [$event, $team]) }}" @if($payChoices) data-pay-choices="{{ json_encode($payChoices, JSON_UNESCAPED_UNICODE) }}" @endif>
        @csrf
        <button type="submit" class="btn">💳 Оплатить</button>
    </form>
@elseif($payment->status === 'paid')
    <div class="alert alert-success f-18">✅ Оплачено</div>
@elseif($payment->user_confirmed)
    <div class="alert alert-warning f-18">⏳ Ожидает подтверждения организатором</div>
@else
    @if($payFee > 0)
        <div class="f-14 mb-1" style="opacity:.7">
            К оплате: <strong>{{ number_format($payment->amount_minor / 100, 2, '.', ' ') }} ₽</strong>
            (с учётом комиссии и налогов {{ number_format($payFee / 100, 2, '.', ' ') }} ₽)
        </div>
    @endif

    @if($payment->method === 'cash')
        <div class="alert alert-info f-18">💵 Оплата наличными — организатору на месте</div>
    @elseif(in_array($payment->method, ['tbank_link', 'sber_link'], true))
        @php $payLink = $payment->method === 'tbank_link' ? $payPs?->tbank_link : $payPs?->sber_link; @endphp
        @if($payLink)
            <a href="{{ $payLink }}" target="_blank" rel="noopener noreferrer" class="btn mb-1">
                {{ $payment->method === 'tbank_link' ? '🏦 Открыть Т-Банк' : '💚 Открыть Сбер' }}
            </a>
        @endif
        <form method="POST" action="{{ route('payments.user_confirm', $payment->id) }}">
            @csrf
            <button type="submit" class="btn btn-warning" onclick="return confirm('Подтверждаете что перевод выполнен?')">Я оплатил</button>
        </form>
    @elseif($payment->method === 'yoomoney')
        <a href="{{ $payment->yoomoney_confirmation_url ?: route('payments.online.choose', $payment->id) }}" class="btn mb-1">💳 Оплатить онлайн</a>
    @endif

    @if($payChoices)
        <form method="POST" action="{{ route('tournamentTeams.pay', [$event, $team]) }}" class="mt-1" data-pay-choices="{{ json_encode($payChoices, JSON_UNESCAPED_UNICODE) }}">
            @csrf
            <button type="submit" class="btn btn-secondary btn-small">Сменить способ оплаты</button>
        </form>
    @endif
@endif
