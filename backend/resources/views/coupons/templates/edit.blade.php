<x-voll-layout body_class="coupon-template-create-page">
    <x-slot name="title">Создать шаблон купона</x-slot>
    <x-slot name="h1">Создать шаблон купона</x-slot>
    <div class="container">
        @if($errors->any())
            <div class="ramka"><div class="alert alert-error"><ul class="list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
        @endif
        <div class="form">
        <form method="POST" action="{{ route('coupon_templates.store') }}">
            @csrf
            <div class="ramka">
                <h2 class="-mt-05">Основное</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>Название *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="150">
                    </div>
                    <div class="col-md-3">
                        <label>Скидка % *</label>
                        <input type="number" name="discount_pct" value="{{ old('discount_pct', 10) }}" min="1" max="100" required>
                    </div>
                    <div class="col-md-3">
                        <label>Использований на купон</label>
                        <input type="number" name="uses_per_coupon" value="{{ old('uses_per_coupon', 1) }}" min="1">
                    </div>
                    <div class="col-md-12">
                        <label>Описание</label>
                        <textarea name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="ramka">
                <h2 class="-mt-05">Мероприятия</h2>
                <div class="card">
                    <ul class="list f-14 mb-2"><li>Оставьте пустым — на все ваши мероприятия</li></ul>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach($events as $event)
                        <label class="checkbox-item">
                            <input type="checkbox" name="event_ids[]" value="{{ $event->id }}" @checked(in_array($event->id, old('event_ids', [])))>
                            <div class="custom-checkbox"></div>
                            <span class="f-14">{{ $event->title }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="ramka">
                <h2 class="-mt-05">Срок и лимиты</h2>
                <div class="row row2">
                    <div class="col-md-3"><label>Действует с</label><input type="date" name="valid_from" value="{{ old('valid_from') }}"></div>
                    <div class="col-md-3"><label>Действует до</label><input type="date" name="valid_until" value="{{ old('valid_until') }}"></div>
                    <div class="col-md-3"><label>Лимит выдачи</label><input type="number" name="issue_limit" value="{{ old('issue_limit') }}" min="1"></div>
                    <div class="col-md-3"><label>Сгорание при отмене (часов)</label><input type="number" name="cancel_hours_before" value="{{ old('cancel_hours_before', 0) }}" min="0"></div>
                    <div class="col-md-12">
                        <label class="checkbox-item">
                            <input type="hidden" name="transfer_enabled" value="0">
                            <input type="checkbox" name="transfer_enabled" value="1" @checked(old('transfer_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>Разрешить передачу купона</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="ramka text-center">
                <a href="{{ route('coupon_templates.index') }}" class="btn btn-secondary mr-2">← Назад</a>
                <button type="submit" class="btn">Создать шаблон</button>
            </div>
        </form>
        </div>
    </div>
</x-voll-layout>
