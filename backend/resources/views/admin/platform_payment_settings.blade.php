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

            <div class="ramka">
                <h2 class="-mt-05">📣 Рекламные мероприятия</h2>
                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Стоимость размещения (₽)</label>
                            <input type="number" name="ad_event_price_rub"
                                value="{{ old('ad_event_price_rub', $settings->ad_event_price_rub ?? 0) }}"
                                min="0" step="1" placeholder="0">
                            <div class="f-14 mt-05" style="opacity:.6">0 = бесплатно / не задано</div>
                        </div>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Ответственный за оплату (получает уведомления)</label>
                            <select name="payment_admin_id" class="form-control">
                                @foreach(\App\Models\User::where('role','admin')->orderBy('id')->get() as $admin)
                                <option value="{{ $admin->id }}"
                                    {{ (int)old('payment_admin_id', $settings->payment_admin_id ?? 1) === (int)$admin->id ? 'selected' : '' }}>
                                    #{{ $admin->id }} {{ $admin->first_name }} {{ $admin->last_name }}
                                </option>
                                @endforeach
                            </select>
                            <div class="f-14 mt-05" style="opacity:.6">По умолчанию — главный администратор</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">⭐ Организатор Pro — Тарифы</h2>
                <p class="f-14 text-muted mb-1">Цены отображаются на странице <a href="/organizer-pro" target="_blank">/organizer-pro</a>. 0 = бесплатно.</p>
                <div class="row row2">
                    <div class="col-md-3 col-sm-6">
                        <div class="card">
                            <label class="f-15 b-600 mb-05">Пробный период (дней)</label>
                            <input type="number" name="organizer_pro_trial_days"
                                   value="{{ old('organizer_pro_trial_days', $settings->organizer_pro_trial_days ?? 7) }}"
                                   min="1" max="90" step="1">
                            <div class="f-13 mt-05" style="opacity:.6">Дней бесплатного доступа</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card">
                            <label class="f-15 b-600 mb-05">1 месяц (₽)</label>
                            <input type="number" name="organizer_pro_month_rub"
                                   value="{{ old('organizer_pro_month_rub', $settings->organizer_pro_month_rub ?? 499) }}"
                                   min="0" step="1">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card">
                            <label class="f-15 b-600 mb-05">3 месяца (₽)</label>
                            <input type="number" name="organizer_pro_quarter_rub"
                                   value="{{ old('organizer_pro_quarter_rub', $settings->organizer_pro_quarter_rub ?? 1199) }}"
                                   min="0" step="1">
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="card">
                            <label class="f-15 b-600 mb-05">1 год (₽)</label>
                            <input type="number" name="organizer_pro_year_rub"
                                   value="{{ old('organizer_pro_year_rub', $settings->organizer_pro_year_rub ?? 3999) }}"
                                   min="0" step="1">
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
