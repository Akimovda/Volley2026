{{-- Автозапись по абонементу. Ожидает: $sub, $autoEvents (список доступных мероприятий для настройки) --}}
@php
    $abEvents = $autoEvents[$sub->id] ?? [];
    $abRows = $sub->autoBookings;
    $abMax = \App\Http\Controllers\SubscriptionAutoBookingController::MAX_PER_SUBSCRIPTION;
@endphp
<div class="mt-3" id="sab-block-{{ $sub->id }}">
    <h3 class="mb-05">{{ __('subscriptions.ab_title') }}</h3>
    <div class="alert alert-info f-14 mb-2">{!! __('subscriptions.ab_desc') !!}</div>

    @if($abRows->isEmpty())
        <div class="f-14 mb-2" style="opacity:.5">{{ __('subscriptions.ab_empty') }}</div>
    @else
        @foreach($abRows as $ab)
            <div class="d-flex between fvc mb-1">
                <div class="f-15">
                    <a href="{{ route('events.show', $ab->event_id) }}" class="cd b-600">#{{ $ab->event_id }} {{ $ab->event?->title }}</a>
                    @if($ab->position && $ab->position !== 'player')
                        <span style="opacity:.6"> — {{ __('subscriptions.ab_position') }}: {{ __('events.positions.' . $ab->position) }}</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('subscriptions.auto_bookings.destroy', $ab->id) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-secondary btn-small">{{ __('subscriptions.ab_delete') }}</button>
                </form>
            </div>
        @endforeach
    @endif

    @if($abRows->count() < $abMax)
        @if(empty($abEvents))
            <div class="f-14" style="opacity:.5">{{ __('subscriptions.ab_no_events') }}</div>
        @else
            <div class="form mt-2">
                <form method="POST" action="{{ route('subscriptions.auto_bookings.store', $sub) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="f-15 mb-05">{{ __('subscriptions.ab_event_label') }}</label>
                        <select name="event_id" id="sab-event-{{ $sub->id }}" data-sub="{{ $sub->id }}" class="sab-event">
                            <option value="">{{ __('subscriptions.ab_event_placeholder') }}</option>
                            @foreach($abEvents as $ev)
                                <option value="{{ $ev['id'] }}">{{ $ev['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-2" id="sab-pos-wrap-{{ $sub->id }}" style="display:none">
                        <label class="f-15 mb-05">{{ __('subscriptions.ab_position_label') }}</label>
                        <select name="position" id="sab-pos-{{ $sub->id }}" disabled>
                            <option value="">{{ __('subscriptions.ab_position_placeholder') }}</option>
                        </select>
                        <div class="f-13 mt-05" style="opacity:.6">{{ __('subscriptions.ab_position_hint') }}</div>
                    </div>
                    <button class="btn w-100" type="submit" id="sab-submit-{{ $sub->id }}" disabled>{{ __('subscriptions.ab_btn_add') }}</button>
                </form>
            </div>
        @endif
    @endif
</div>
