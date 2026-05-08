<x-voll-layout body_class="subscription-template-edit-page">
    <x-slot name="title">{{ __('subscriptions.tpl_edit_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.tpl_edit_title') }}</x-slot>
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
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_main') }}</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_name') }}</label>
                            <input type="text" name="name"
                                   value="{{ old('name', $subscriptionTemplate->name) }}"
                                   required maxlength="150">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_visits_short') }}</label>
                            <input type="number" name="visits_total"
                                   value="{{ old('visits_total', $subscriptionTemplate->visits_total) }}"
                                   min="1" max="1000" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_status') }}</label>
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="is_active" value="1"
                                       @checked(old('is_active', $subscriptionTemplate->is_active))>
                                <span>{{ __('subscriptions.tpl_status_active_short') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_description') }}</label>
                            <textarea name="description" rows="3" maxlength="1000">{{ old('description', $subscriptionTemplate->description) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_events') }}</h2>
                <div class="card">
                    <label>{{ __('subscriptions.tpl_events_link_label') }}</label>
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
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_term') }}</h2>
                <div class="card mb-2 f-14" style="opacity:.7;">
                    {{ __('subscriptions.tpl_term_hint_short') }}
                </div>
                <div class="row row2">
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_months') }}</label>
                            <input type="number" name="duration_months"
                                   value="{{ old('duration_months', $subscriptionTemplate->duration_months ?? 0) }}"
                                   min="0" max="36" placeholder="0">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_days_extra') }}</label>
                            <input type="number" name="duration_days"
                                   value="{{ old('duration_days', $subscriptionTemplate->duration_days ?? 0) }}"
                                   min="0" max="365" placeholder="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_price') }}</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_price_rub') }}</label>
                            <input type="number" name="price_rub"
                                   value="{{ old('price_rub', round($subscriptionTemplate->price_minor / 100)) }}"
                                   min="0" step="1" required>
                            <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_price_zero') }}</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_currency') }}</label>
                            <select name="currency">
                                <option value="RUB" @selected(old('currency', $subscriptionTemplate->currency)==='RUB')>RUB ₽</option>
                                <option value="USD" @selected(old('currency', $subscriptionTemplate->currency)==='USD')>USD $</option>
                                <option value="EUR" @selected(old('currency', $subscriptionTemplate->currency)==='EUR')>EUR €</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_sale_limit') }}</label>
                            <input type="number" name="sale_limit"
                                   value="{{ old('sale_limit', $subscriptionTemplate->sale_limit) }}"
                                   min="1">
                            <ul class="list f-13 mt-1"><li>{{ __('subscriptions.tpl_hint_sale_unlimited_short') }}</li></ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="sale_enabled" value="1"
                                       @checked(old('sale_enabled', $subscriptionTemplate->sale_enabled))>
                                <span>{{ __('subscriptions.tpl_sale_enabled') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_extra') }}</h2>
                <div class="row row2">
                    <div class="col-md-4">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_cancel_short') }}</label>
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
                                <span>{{ __('subscriptions.tpl_transfer_short') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="auto_booking_enabled" value="1"
                                       @checked(old('auto_booking_enabled', $subscriptionTemplate->auto_booking_enabled))>
                                <span>{{ __('subscriptions.tpl_auto_booking_short') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <label class="d-flex fvc gap-1">
                                <input type="checkbox" name="freeze_enabled" value="1"
                                       id="freeze_toggle"
                                       @checked(old('freeze_enabled', $subscriptionTemplate->freeze_enabled))>
                                <span>{{ __('subscriptions.tpl_freeze_short') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4" id="freeze_fields" style="{{ old('freeze_enabled', $subscriptionTemplate->freeze_enabled) ? '' : 'display:none' }}">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_freeze_weeks_short') }}</label>
                            <input type="number" name="freeze_max_weeks"
                                   value="{{ old('freeze_max_weeks', $subscriptionTemplate->freeze_max_weeks) }}"
                                   min="0">
                        </div>
                    </div>
                    <div class="col-md-4" id="freeze_fields2" style="{{ old('freeze_enabled', $subscriptionTemplate->freeze_enabled) ? '' : 'display:none' }}">
                        <div class="card">
                            <label>{{ __('subscriptions.tpl_label_freeze_months_short') }}</label>
                            <input type="number" name="freeze_max_months"
                                   value="{{ old('freeze_max_months', $subscriptionTemplate->freeze_max_months) }}"
                                   min="0">
                        </div>
                    </div>
                </div>
            </div>

            <div class="ramka text-center">
                <a href="{{ route('subscription_templates.index') }}" class="btn btn-secondary mr-2">{{ __('subscriptions.tpl_btn_back') }}</a>
                <button type="submit" class="btn">{{ __('subscriptions.tpl_btn_save') }}</button>
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
