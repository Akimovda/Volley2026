<x-voll-layout body_class="subscription-template-create-page">
    <x-slot name="title">{{ __('subscriptions.tpl_create_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.tpl_create_title') }}</x-slot>

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
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_main') }}</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>{{ __('subscriptions.tpl_label_name') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="150">
                        @error('name')<div class="text-xs text-red-600">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label>{{ __('subscriptions.tpl_label_visits_total') }}</label>
                        <input type="number" name="visits_total" value="{{ old('visits_total', 10) }}" min="1" max="1000" required>
                    </div>
                    <div class="col-md-12">
                        <label>{{ __('subscriptions.tpl_label_description') }}</label>
                        <textarea name="description" rows="3" maxlength="1000">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_events') }}</h2>
                <div class="card">
                    <label class="f-16 mb-1">{{ __('subscriptions.tpl_events_label') }}</label>
                    <ul class="list f-14 mb-2"><li>{{ __('subscriptions.tpl_events_hint') }}</li></ul>
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
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_term') }}</h2>
                <div class="card mb-2 f-14" style="opacity:.7;">
                    {{ __('subscriptions.tpl_term_hint') }}
                </div>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_months') }}</label>
                            <input type="number" name="duration_months" value="{{ old('duration_months', 0) }}" min="0" max="36" placeholder="0">
                            <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_months_eg') }}</li></ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_days_extra') }}</label>
                            <input type="number" name="duration_days" value="{{ old('duration_days', 0) }}" min="0" max="365" placeholder="0">
                            <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_days_eg') }}</li></ul>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_price') }}</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.tpl_label_price_rub') }}</label>
                        <input type="number" name="price_rub" value="{{ old('price_rub', 0) }}" min="0" step="1" required>
                        <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_price_eg') }}</li></ul>
                        <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_price_eg2') }}</li></ul>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.tpl_label_currency') }}</label>
                        <select name="currency">
                            <option value="RUB" @selected(old('currency','RUB')==='RUB')>RUB ₽</option>
                            <option value="USD" @selected(old('currency')==='USD')>USD $</option>
                            <option value="EUR" @selected(old('currency')==='EUR')>EUR €</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label>{{ __('subscriptions.tpl_label_sale_limit') }}</label>
                        <input type="number" name="sale_limit" value="{{ old('sale_limit') }}" min="1">
                        <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_sale_unlimited') }}</li></ul>
                    </div>
                    <div class="col-md-12">
                        <label class="checkbox-item">
                            <input type="hidden" name="sale_enabled" value="0">
                            <input type="checkbox" name="sale_enabled" value="1" @checked(old('sale_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('subscriptions.tpl_sale_enabled') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_rules') }}</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>{{ __('subscriptions.tpl_label_cancel_hours') }}</label>
                        <input type="number" name="cancel_hours_before" value="{{ old('cancel_hours_before', 0) }}" min="0">
                        <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_cancel_zero') }}</li></ul>
                    </div>
                    <div class="col-md-6">
                        <label class="checkbox-item mb-1">
                            <input type="hidden" name="transfer_enabled" value="0">
                            <input type="checkbox" name="transfer_enabled" value="1" @checked(old('transfer_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('subscriptions.tpl_transfer_enabled') }}</span>
                        </label>
                        <label class="checkbox-item mb-1">
                            <input type="hidden" name="auto_booking_enabled" value="0">
                            <input type="checkbox" name="auto_booking_enabled" value="1" @checked(old('auto_booking_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('subscriptions.tpl_auto_booking') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_freeze') }}</h2>
                <div class="row row2">
                    <div class="col-md-12">
                        <label class="checkbox-item mb-2">
                            <input type="hidden" name="freeze_enabled" value="0">
                            <input type="checkbox" name="freeze_enabled" value="1" id="freeze_enabled"
                                @checked(old('freeze_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('subscriptions.tpl_freeze_enabled') }}</span>
                        </label>
                    </div>
                    <div class="col-md-6" id="freeze_fields" style="{{ old('freeze_enabled') ? '' : 'display:none' }}">
                        <label>{{ __('subscriptions.tpl_label_freeze_weeks') }}</label>
                        <input type="number" name="freeze_max_weeks" value="{{ old('freeze_max_weeks', 2) }}" min="0">
                    </div>
                    <div class="col-md-6" id="freeze_fields2" style="{{ old('freeze_enabled') ? '' : 'display:none' }}">
                        <label>{{ __('subscriptions.tpl_label_freeze_months') }}</label>
                        <input type="number" name="freeze_max_months" value="{{ old('freeze_max_months', 1) }}" min="0">
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary mr-2">{{ __('subscriptions.tpl_btn_back') }}</a>
                <button type="submit" class="btn">{{ __('subscriptions.tpl_btn_create_short') }}</button>
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
