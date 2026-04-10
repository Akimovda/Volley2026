<x-voll-layout body_class="subscription-template-create-page">
    <x-slot name="title">Создать шаблон абонемента</x-slot>
    <x-slot name="h1">Создать шаблон абонемента</x-slot>

    <div class="container">
        @if($errors->any())
            <div class="ramka">
                <div class="alert alert-error">
                    <ul class="list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            </div>
        @endif

        <div class="form">
        <form method="POST" action="{{ route('subscription_templates.store') }}">
            @csrf

            <div class="ramka">
                <h2 class="-mt-05">Основное</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>Название *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="150">
                        @error('name')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label>Количество посещений *</label>
                        <input type="number" name="visits_total" value="{{ old('visits_total', 10) }}" min="1" max="1000" required>
                    </div>
                    <div class="col-md-12">
                        <label>Описание</label>
                        <textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Мероприятия</h2>
                <div class="card">
                    <label class="f-16 mb-1">Мероприятия где действует абонемент</label>
                    <ul class="list f-14 mb-2"><li>Оставьте пустым — будет действовать на все ваши мероприятия</li></ul>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($events as $event)
                        <label class="checkbox-item">
                            <input type="checkbox" name="event_ids[]" value="{{ $event->id }}"
                                @checked(in_array($event->id, old('event_ids', [])))>
                            <div class="custom-checkbox"></div>
                            <span class="f-14">{{ $event->title }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Срок действия</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>Действует с</label>
                        <input type="date" name="valid_from" value="{{ old('valid_from') }}">
                    </div>
                    <div class="col-md-6">
                        <label>Действует до</label>
                        <input type="date" name="valid_until" value="{{ old('valid_until') }}">
                        <ul class="list f-13 mt-1"><li>Оставьте пустым — бессрочно</li></ul>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Стоимость и продажа</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <label>Цена (копейки) *</label>
                        <input type="number" name="price_minor" value="{{ old('price_minor', 0) }}" min="0" required>
                        <ul class="list f-13 mt-1"><li>Например: 150000 = 1500 ₽, 0 = бесплатно</li></ul>
                    </div>
                    <div class="col-md-4">
                        <label>Валюта</label>
                        <select name="currency">
                            <option value="RUB" @selected(old('currency','RUB')==='RUB')>RUB ₽</option>
                            <option value="USD" @selected(old('currency')==='USD')>USD $</option>
                            <option value="EUR" @selected(old('currency')==='EUR')>EUR €</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>Лимит продаж</label>
                        <input type="number" name="sale_limit" value="{{ old('sale_limit') }}" min="1">
                        <ul class="list f-13 mt-1"><li>Оставьте пустым — безлимит</li></ul>
                    </div>
                    <div class="col-md-12">
                        <label class="checkbox-item">
                            <input type="hidden" name="sale_enabled" value="0">
                            <input type="checkbox" name="sale_enabled" value="1" @checked(old('sale_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>Доступен для продажи на сайте</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Правила использования</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>Сгорание посещения при отмене (часов до начала)</label>
                        <input type="number" name="cancel_hours_before" value="{{ old('cancel_hours_before', 0) }}" min="0">
                        <ul class="list f-13 mt-1"><li>0 = посещение не сгорает при отмене</li></ul>
                    </div>
                    <div class="col-md-6">
                        <label class="checkbox-item mb-1">
                            <input type="hidden" name="transfer_enabled" value="0">
                            <input type="checkbox" name="transfer_enabled" value="1" @checked(old('transfer_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>Разрешить передачу другому игроку</span>
                        </label>
                        <label class="checkbox-item mb-1">
                            <input type="hidden" name="auto_booking_enabled" value="0">
                            <input type="checkbox" name="auto_booking_enabled" value="1" @checked(old('auto_booking_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>Разрешить автозапись</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Заморозка</h2>
                <div class="row row2">
                    <div class="col-md-12">
                        <label class="checkbox-item mb-2">
                            <input type="hidden" name="freeze_enabled" value="0">
                            <input type="checkbox" name="freeze_enabled" value="1" id="freeze_enabled"
                                @checked(old('freeze_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>Разрешить заморозку абонемента</span>
                        </label>
                    </div>
                    <div class="col-md-6" id="freeze_fields" style="{{ old('freeze_enabled') ? '' : 'display:none' }}">
                        <label>Максимум недель заморозки</label>
                        <input type="number" name="freeze_max_weeks" value="{{ old('freeze_max_weeks', 2) }}" min="0">
                    </div>
                    <div class="col-md-6" id="freeze_fields2" style="{{ old('freeze_enabled') ? '' : 'display:none' }}">
                        <label>Максимум месяцев заморозки</label>
                        <input type="number" name="freeze_max_months" value="{{ old('freeze_max_months', 1) }}" min="0">
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary mr-2">← Назад</a>
                <button type="submit" class="btn">Создать шаблон</button>
            </div>
        </form>
        </div>
    </div>

    <x-slot name="script">
    <script>
    document.getElementById('freeze_enabled')?.addEventListener('change', function() {
        const show = this.checked;
        document.getElementById('freeze_fields').style.display = show ? '' : 'none';
        document.getElementById('freeze_fields2').style.display = show ? '' : 'none';
    });
    </script>
    </x-slot>
</x-voll-layout>
