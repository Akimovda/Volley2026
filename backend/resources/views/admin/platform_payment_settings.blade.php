<x-voll-layout body_class="admin-page">

    <x-slot name="title">Настройка оплаты Premium — Admin</x-slot>
    <x-slot name="h1">💳 Оплата Premium и рекламы</x-slot>
    <x-slot name="t_description">Настройте приём платежей за Premium-подписки и рекламные размещения</x-slot>

    <div class="container">

        @if(session('status'))
        <div class="ramka">
            <div class="f-16 cs b-600">{{ session('status') }}</div>
        </div>
        @endif

        <div class="form">
        <form method="POST" action="{{ route('admin.platform_payment_settings.update') }}">
            @csrf

            <div class="ramka">
                <h2 class="-mt-05">Способ приёма оплаты</h2>
                @php $m = old('method', $settings->method ?? 'tbank_link'); @endphp
                <div class="card">
                    <label class="radio-item">
                        <input type="radio" name="method" value="tbank_link" @checked($m === 'tbank_link')>
                        <div class="custom-radio"></div>
                        <span>🏦 Перевод через Т-Банк (по ссылке)</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="method" value="sber_link" @checked($m === 'sber_link')>
                        <div class="custom-radio"></div>
                        <span>💚 Перевод через Сбер (по ссылке)</span>
                    </label>
                    <label class="radio-item">
                        <input type="radio" name="method" value="yoomoney" @checked($m === 'yoomoney')>
                        <div class="custom-radio"></div>
                        <span>🟡 ЮМани (автоматический приём)</span>
                    </label>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">🔗 Ссылки для перевода</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Ссылка Т-Банк</label>
                            <input type="url" name="tbank_link"
                                value="{{ old('tbank_link', $settings->tbank_link) }}"
                                placeholder="https://www.tbank.ru/cf/...">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Ссылка Сбер</label>
                            <input type="url" name="sber_link"
                                value="{{ old('sber_link', $settings->sber_link) }}"
                                placeholder="https://www.sberbank.com/...">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">🟡 ЮМани</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Shop ID</label>
                            <input type="text" name="yoomoney_shop_id"
                                value="{{ old('yoomoney_shop_id', $settings->yoomoney_shop_id) }}"
                                placeholder="123456">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Секретный ключ</label>
                            <input type="password" name="yoomoney_secret_key"
                                value="{{ old('yoomoney_secret_key', $settings->yoomoney_secret_key ? '••••••••' : '') }}"
                                placeholder="test_xxxxxxxxxxxxxxxx"
                                autocomplete="new-password">
                            <div class="f-14 mt-05" style="opacity:.6">Оставьте пустым если не хотите менять</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('profile.show') }}" class="btn btn-secondary mr-2">← Назад</a>
                <button type="submit" class="btn">Сохранить</button>
            </div>

        </form>
        </div>
    </div>

</x-voll-layout>
