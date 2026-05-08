<x-voll-layout body_class="coupon-template-create-page">
    <x-slot name="title">{{ __('subscriptions.coupon_tpl_create_title') }}</x-slot>
    <x-slot name="h1">{{ __('subscriptions.coupon_tpl_create_title') }}</x-slot>
    <div class="container">
        @if($errors->any())
            <div class="ramka"><div class="alert alert-error"><ul class="list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div></div>
        @endif
        <div class="form">
        <form method="POST" action="{{ route('coupon_templates.store') }}">
            @csrf
            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_main') }}</h2>
                <div class="row row2">
                    <div class="col-md-6">
                        <label>{{ __('subscriptions.tpl_label_name') }}</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="150">
                    </div>
                    <div class="col-md-3">
                        <label>{{ __('subscriptions.coupon_tpl_label_pct') }}</label>
                        <input type="number" name="discount_pct" value="{{ old('discount_pct', 10) }}" min="1" max="100" required>
                    </div>
                    <div class="col-md-3">
                        <label>{{ __('subscriptions.coupon_tpl_label_uses') }}</label>
                        <input type="number" name="uses_per_coupon" value="{{ old('uses_per_coupon', 1) }}" min="1">
                    </div>
                    <div class="col-md-12">
                        <label>{{ __('subscriptions.tpl_label_description') }}</label>
                        <textarea name="description" rows="2" maxlength="1000">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>
            <div class="ramka">
                <h2 class="-mt-05">{{ __('subscriptions.tpl_section_events') }}</h2>
                <div class="card">
                    <ul class="list f-14 mb-2"><li>{{ __('subscriptions.coupon_tpl_events_hint') }}</li></ul>
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
                <h2 class="-mt-05">{{ __('subscriptions.coupon_tpl_section_term') }}</h2>
                <div class="row row2">
                    <div class="col-md-3"><label>{{ __('subscriptions.coupon_tpl_label_valid_from') }}</label><input type="date" name="valid_from" value="{{ old('valid_from') }}"></div>
                    <div class="col-md-3"><label>{{ __('subscriptions.coupon_tpl_label_valid_until') }}</label><input type="date" name="valid_until" value="{{ old('valid_until') }}"></div>
                    <div class="col-md-3"><label>{{ __('subscriptions.coupon_tpl_label_issue_limit') }}</label><input type="number" name="issue_limit" value="{{ old('issue_limit') }}" min="1"></div>
                    <div class="col-md-3"><label>{{ __('subscriptions.coupon_tpl_label_cancel_hours') }}</label><input type="number" name="cancel_hours_before" value="{{ old('cancel_hours_before', 0) }}" min="0"></div>
                    <div class="col-md-12">
                        <label class="checkbox-item">
                            <input type="hidden" name="transfer_enabled" value="0">
                            <input type="checkbox" name="transfer_enabled" value="1" @checked(old('transfer_enabled'))>
                            <div class="custom-checkbox"></div>
                            <span>{{ __('subscriptions.coupon_tpl_transfer_enabled') }}</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="ramka text-center">
                <a href="{{ route('coupon_templates.index') }}" class="btn btn-secondary mr-2">{{ __('subscriptions.tpl_btn_back') }}</a>
                <button type="submit" class="btn">{{ __('subscriptions.tpl_btn_create_short') }}</button>
            </div>
        </form>
        </div>
    </div>
</x-voll-layout>
