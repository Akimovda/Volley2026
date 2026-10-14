<x-voll-layout body_class="payment-page">

    <x-slot name="title">Способ оплаты</x-slot>
    <x-slot name="h1">💳 Способ оплаты</x-slot>

    <div class="container">
        @if(session('error'))
        <div class="ramka"><div class="alert alert-danger">{{ session('error') }}</div></div>
        @endif

        <div class="ramka">
            <div class="f-17 b-600 mb-1">{{ $title }}</div>
            <div class="f-15 mb-2" style="opacity:.7">
                Стоимость: {{ number_format($base / 100, 2, '.', ' ') }} ₽. Комиссия платёжной системы зависит от способа оплаты и добавляется к стоимости.
            </div>

            <form method="POST" action="{{ $action }}" class="form">
                @csrf
                @foreach($options as $key => $o)
                <label class="radio-item mb-1">
                    <input type="radio" name="method" value="{{ $key }}" @checked($loop->first) required>
                    <div class="custom-radio"></div>
                    <span>
                        <strong>{{ $o['label'] }}</strong> —
                        {{ number_format($o['total'] / 100, 2, '.', ' ') }} ₽
                        <span style="opacity:.6">(комиссия {{ rtrim(rtrim(number_format($o['percent'], 2, '.', ''), '0'), '.') }}% — {{ number_format($o['fee'] / 100, 2, '.', ' ') }} ₽)</span>
                    </span>
                </label>
                @endforeach
                <button type="submit" class="btn mt-1">Перейти к оплате</button>
            </form>
        </div>
    </div>
</x-voll-layout>
