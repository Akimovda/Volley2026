<x-voll-layout body_class="subscription-template-edit-page">
    <x-slot name="title">Редактировать шаблон абонемента</x-slot>
    <x-slot name="h1">Редактировать шаблон абонемента</x-slot>
    <x-slot name="h2">{{ $subscriptionTemplate->name }}</x-slot>

    <div class="container">
        @if($errors->any())
        <div class="ramka">
            <div class="alert alert-error">
                <ul class="list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
        @endif

        @if(session('status'))
        <div class="ramka"><div class="alert alert-success">{{ session('status') }}</div></div>
        @endif

        <div class="form">
        <form method="POST" action="{{ route('subscription_templates.update', $subscriptionTemplate) }}">
            @csrf
            @method('PUT')

            <div class="ramka">
                <h2 class="-mt-05">Основное</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Название *</label>
                            <input type="text" name="name"
                                   value="{{ old('name', $subscriptionTemplate->name) }}"
                                   required maxlength="150">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Посещений *</label>
                            <input type="number" name="visits_total"
                                   value="{{ old('visits_total', $subscriptionTemplate->visits_total) }}"
                                   min="1" max="1000" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>Статус</label>
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="is_active" value="1"
                                       @checked(old('is_active', $subscriptionTemplate->is_active))>
                                <span>Активен</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="card">
                            <label>Описание</label>
                            <textarea name="description" rows="3" maxlength="1000">{{ old('description', $subscriptionTemplate->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Мероприятия</h2>
                <div class="card">
                    <label>Привязать к мероприятиям (необязательно)</label>
                    <div style="max-height:20rem;overflow-y:auto;">
                        @foreach($events as $event)
                        <label class="d-flex fvc gap-1 mb-05">
                            <input type="checkbox" name="event_ids[]" value="{{ $event->id }}"
                                @checked(in_array($event->id, old('event_ids', $subscriptionTemplate->event_ids ?? [])))>
                            <span>{{ $event->title }} (#{{ $event->id }})</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Срок действия</h2>
                <div class="card mb-2 f-14" style="opacity:.7;">
                    Срок считается с момента покупки или выдачи. Оставьте пустыми — бессрочный.
                </div>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>Месяцев</label>
                            <input type="number" name="duration_months"
                                   value="{{ old('duration_months', $subscriptionTemplate->duration_months ?? 0) }}"
                                   min="0" max="36" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>Дней (дополнительно)</label>
                            <input type="number" name="duration_days"
                                   value="{{ old('duration_days', $subscriptionTemplate->duration_days ?? 0) }}"
                                   min="0" max="365" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Стоимость и продажа</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Цена (рубли) *</label>
                            <input type="number" name="price_rub"
                                   value="{{ old('price_rub', round($subscriptionTemplate->price_minor / 100)) }}"
                                   min="0" step="1" required>
                            <ul class="list f-13 mt-1"><li>0 = бесплатно</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Валюта</label>
                            <select name="currency">
                                <option value="RUB" @selected(old('currency', $subscriptionTemplate->currency)==='RUB')>RUB ₽</option>
                                <option value="USD" @selected(old('currency', $subscriptionTemplate->currency)==='USD')>USD $</option>
                                <option value="EUR" @selected(old('currency', $subscriptionTemplate->currency)==='EUR')>EUR €</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>Лимит продаж</label>
                            <input type="number" name="sale_limit"
                                   value="{{ old('sale_limit', $subscriptionTemplate->sale_limit) }}"
                                   min="1">
                            <ul class="list f-13 mt-1"><li>Пусто — безлимит</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="sale_enabled" value="1"
                                       @checked(old('sale_enabled', $subscriptionTemplate->sale_enabled))>
                                <span>Доступен для продажи на сайте</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">Дополнительно</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <div class="card">
                            <label>Отмена за (часов)</label>
                            <input type="number" name="cancel_hours_before"
                                   value="{{ old('cancel_hours_before', $subscriptionTemplate->cancel_hours_before) }}"
                                   min="0">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="transfer_enabled" value="1"
                                       @checked(old('transfer_enabled', $subscriptionTemplate->transfer_enabled))>
                                <span>🔄 Передача разрешена</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="auto_booking_enabled" value="1"
                                       @checked(old('auto_booking_enabled', $subscriptionTemplate->auto_booking_enabled))>
                                <span>Авто-бронирование</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="freeze_enabled" value="1"
                                       id="freeze_toggle"
                                       @checked(old('freeze_enabled', $subscriptionTemplate->freeze_enabled))>
                                <span>❄️ Заморозка разрешена</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4" id="freeze_fields" style="{{ old('freeze_enabled', $subscriptionTemplate->freeze_enabled) ? '' : 'display:none' }}">
                        <div class="card">
                            <label>Макс. недель заморозки</label>
                            <input type="number" name="freeze_max_weeks"
                                   value="{{ old('freeze_max_weeks', $subscriptionTemplate->freeze_max_weeks) }}"
                                   min="0">
                        </div>
                    </div>
                    <div class="col-md-4" id="freeze_fields2" style="{{ old('freeze_enabled', $subscriptionTemplate->freeze_enabled) ? '' : 'display:none' }}">
                        <div class="card">
                            <label>Макс. месяцев заморозки</label>
                            <input type="number" name="freeze_max_months"
                                   value="{{ old('freeze_max_months', $subscriptionTemplate->freeze_max_months) }}"
                                   min="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary mr-2">← Назад</a>
                <button type="submit" class="btn">💾 Сохранить</button>
            </div>
        </form>
        </div>
    </div>

    <x-slot name="script">
    <script>
    document.getElementById('freeze_toggle')?.addEventListener('change', function() {
        document.getElementById('freeze_fields').style.display = this.checked ? '' : 'none';
        document.getElementById('freeze_fields2').style.display = this.checked ? '' : 'none';
    });
    </script>
    </x-slot>
</x-voll-layout>
