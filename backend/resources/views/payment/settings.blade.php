{{-- resources/views/payment/settings.blade.php --}}
<x-voll-layout body_class="payment-settings-page">

    <x-slot name="title">{{ __('profile.pay_settings_title') }}</x-slot>
    <x-slot name="h1">{{ __('profile.pay_settings_title') }}</x-slot>
    <x-slot name="t_description">{{ __('profile.pay_settings_t_description') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('profile.show') }}" itemprop="item"><span itemprop="name">{{ __('profile.nch_breadcrumb') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('profile.pay_settings_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <div class="container">

        @if(session('status'))
            <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif
        @if($errors->any())
            <div class="ramka">
                <div class="alert alert-error">
                    <div class="alert-title">{{ __('profile.check_fields') }}</div>
                    <ul class="list">
                        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Статус --}}
        <div class="ramka">
            <h2 class="-mt-05">Статус платёжной системы</h2>
            <div class="card">
                @if($settings->yoomoney_verified)
                    <div class="f-18 cs b-600">✅ Платежи через ЮМани настроены</div>
                    <div class="f-16 mt-1" style="opacity:.6">Shop ID: {{ $settings->yoomoney_shop_id }}</div>
                @elseif($settings->exists && ($settings->tbank_link || $settings->sber_link))
                    <div class="f-18 cd b-600">🔗 Настроены платежи по ссылке</div>
                    @if($settings->tbank_link)<div class="f-16 mt-05">Т-Банк: <a href="{{ $settings->tbank_link }}" target="_blank">{{ $settings->tbank_link }}</a></div>@endif
                    @if($settings->sber_link)<div class="f-16 mt-05">Сбер: <a href="{{ $settings->sber_link }}" target="_blank">{{ $settings->sber_link }}</a></div>@endif
                @else
                    <div class="f-18 b-600" style="opacity:.5">⚙️ Платежи не настроены</div>
                    <div class="f-16 mt-1" style="opacity:.5">Заполните форму ниже чтобы принимать оплату.</div>
                @endif
            </div>
        </div>

        <div class="form">
        <form method="POST" action="{{ route('profile.payment_settings.update') }}">
            @csrf

            {{-- Способ оплаты по умолчанию --}}
            <div class="ramka">
                <h2 class="-mt-05">💳 Способ оплаты по умолчанию</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            @php $dm = old('default_method', $settings->default_method ?? 'cash'); @endphp
                            <label class="radio-item">
                                <input type="radio" name="default_method" value="cash" @checked($dm === 'cash')>
                                <div class="custom-radio"></div>
                                <span>💵 Наличные (оплата на месте)</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="default_method" value="tbank_link" @checked($dm === 'tbank_link')>
                                <div class="custom-radio"></div>
                                <span>🏦 Перевод через Т-Банк (по ссылке)</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="default_method" value="sber_link" @checked($dm === 'sber_link')>
                                <div class="custom-radio"></div>
                                <span>💚 Перевод через Сбер (по ссылке)</span>
                            </label>
                            <label class="radio-item">
                                <input type="radio" name="default_method" value="yoomoney" @checked($dm === 'yoomoney')>
                                <div class="custom-radio"></div>
                                <span>🟡 ЮМани (автоматический приём оплаты)</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="b-600 mb-1">Как это работает</div>
                            <ul class="list f-16">
                                <li><strong>Наличные</strong> — запись без оплаты, деньги берёте на месте</li>
                                <li><strong>По ссылке</strong> — игрок переводит сам, нажимает "Я оплатил", вы подтверждаете</li>
                                <li><strong>ЮМани</strong> — автоматический приём, игрок попадает в список только после оплаты. Комиссия ~3.5% на вас</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ссылки для перевода --}}
            <div class="ramka">
                <h2 class="-mt-05">🔗 Ссылки для перевода</h2>
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Ссылка Т-Банк</label>
                            <input type="url" name="tbank_link"
                                value="{{ old('tbank_link', $settings->tbank_link) }}"
                                placeholder="https://www.tbank.ru/cf/...">
                            <ul class="list f-14 mt-1">
                                <li>Получите ссылку в приложении Т-Банк → Платежи → Реквизиты</li>
                            </ul>
                            @error('tbank_link')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Ссылка Сбер</label>
                            <input type="url" name="sber_link"
                                value="{{ old('sber_link', $settings->sber_link) }}"
                                placeholder="https://www.sberbank.com/...">
                            <ul class="list f-14 mt-1">
                                <li>Получите ссылку в СберБанк Онлайн → Профиль → Поделиться реквизитами</li>
                            </ul>
                            @error('sber_link')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- ЮМани --}}
            <div class="ramka">
                <h2 class="-mt-05">🟡 Настройка ЮМани</h2>
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <label class="checkbox-item">
                                <input type="hidden" name="yoomoney_enabled" value="0">
                                <input type="checkbox" name="yoomoney_enabled" value="1"
                                    @checked(old('yoomoney_enabled', $settings->yoomoney_enabled ?? false))>
                                <div class="custom-checkbox"></div>
                                <span>Включить приём платежей через ЮМани</span>
                            </label>
                            <ul class="list f-16 mt-1">
                                <li>Необходим аккаунт ЮКасса (Yoomoney для бизнеса)</li>
                                <li>Комиссия ~3.5% за каждый платёж — за счёт организатора</li>
                                <li>Игрок попадает в список участников только после успешной оплаты</li>
                                <li>Неоплаченный резерв освобождается автоматически через N минут</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Shop ID (идентификатор магазина)</label>
                            <input type="text" name="yoomoney_shop_id"
                                value="{{ old('yoomoney_shop_id', $settings->yoomoney_shop_id) }}"
                                placeholder="123456">
                            <ul class="list f-14 mt-1">
                                <li>Получите в личном кабинете ЮКасса → Интеграция</li>
                            </ul>
                            @error('yoomoney_shop_id')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Секретный ключ</label>
                            <input type="password" name="yoomoney_secret_key"
                                value="{{ old('yoomoney_secret_key', $settings->yoomoney_secret_key ? '••••••••' : '') }}"
                                placeholder="test_xxxxxxxxxxxxxxxx"
                                autocomplete="new-password">
                            <ul class="list f-14 mt-1">
                                <li>Ключ хранится в зашифрованном виде</li>
                                <li>Оставьте пустым если не хотите менять</li>
                            </ul>
                            @error('yoomoney_secret_key')<div class="text-xs text-red-600 mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Политика возвратов --}}
            <div class="ramka">
                <h2 class="-mt-05">🔄 Политика возвратов (по умолчанию)</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Полный возврат (100%) — за сколько часов до начала</label>
                            <input type="number" name="refund_hours_full" min="0" max="720"
                                value="{{ old('refund_hours_full', $settings->refund_hours_full ?? 48) }}">
                            <ul class="list f-14 mt-1"><li>Например: 48 = за 2 суток</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Частичный возврат — за сколько часов до начала</label>
                            <input type="number" name="refund_hours_partial" min="0" max="720"
                                value="{{ old('refund_hours_partial', $settings->refund_hours_partial ?? 24) }}">
                            <ul class="list f-14 mt-1"><li>Например: 24 = за сутки</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Размер частичного возврата (%)</label>
                            <input type="number" name="refund_partial_pct" min="0" max="100"
                                value="{{ old('refund_partial_pct', $settings->refund_partial_pct ?? 50) }}">
                            <ul class="list f-14 mt-1"><li>0 = нет возврата, 100 = полный</li></ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label class="checkbox-item">
                                <input type="hidden" name="refund_no_quorum_full" value="0">
                                <input type="checkbox" name="refund_no_quorum_full" value="1"
                                    @checked(old('refund_no_quorum_full', $settings->refund_no_quorum_full ?? true))>
                                <div class="custom-checkbox"></div>
                                <span>Полный возврат при отмене по кворуму (100%)</span>
                            </label>
                            <ul class="list f-14 mt-1">
                                <li>Рекомендуется: если мероприятие отменяется из-за нехватки игроков — полный возврат на виртуальный счёт</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Время удержания резерва (минуты)</label>
                            <input type="number" name="payment_hold_minutes" min="5" max="120"
                                value="{{ old('payment_hold_minutes', $settings->payment_hold_minutes ?? 15) }}">
                            <ul class="list f-14 mt-1">
                                <li>Сколько минут ждать оплату через ЮМани до освобождения места</li>
                                <li>Рекомендуется: 15–30 минут</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('profile.show') }}" class="btn btn-secondary mr-2">← Профиль</a>
                <button type="submit" class="btn">Сохранить настройки</button>
            </div>

        </form>
        </div>
    </div>

</x-voll-layout>
