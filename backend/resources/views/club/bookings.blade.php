<x-voll-layout body_class="club-bookings-page">
<x-slot name="title">{{ __('club.bookings_title') }}</x-slot>
<x-slot name="h1">{{ __('club.bookings_title') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('club.bookings_title') }}</span>
        <meta itemprop="position" content="2">
    </li>
</x-slot>

@php
$statusLabels = [
    'pending'   => __('club.status_pending'),
    'confirmed' => __('club.status_confirmed'),
    'paid'      => __('club.status_paid'),
    'cancelled' => __('club.status_cancelled'),
    'expired'   => __('club.status_expired'),
];
$statusColors = [
    'pending'   => '#8E8E93',
    'confirmed' => '#4A9EFF',
    'paid'      => '#34C759',
    'cancelled' => '#FF3B30',
    'expired'   => '#8E8E93',
];

$paymentBadge = function ($booking) {
    if ($booking->payment_mode === 'prepaid' && $booking->status === 'paid') {
        return ['label' => __('club.payment_mode_online'), 'color' => '#34C759'];
    }
    if ($booking->payment_mode === 'prepaid' && $booking->status === 'pending') {
        return ['label' => __('club.payment_mode_awaiting'), 'color' => '#FF9500'];
    }
    return ['label' => __('club.payment_mode_on_site'), 'color' => '#8E8E93'];
};

$renderBooking = function ($booking) use ($statusLabels, $statusColors, $paymentBadge) {
    $court = $booking->court;
    $location = $court?->direction?->location;
    $tz = $location?->effectiveTimezone() ?? 'Europe/Moscow';
    $startsLocal = $booking->starts_at?->copy()->setTimezone($tz);
    $endsLocal = $booking->ends_at?->copy()->setTimezone($tz);
    return [
        'id' => $booking->id,
        'court_id' => $court->id ?? null,
        'direction_id' => $court->direction_id ?? null,
        'court_name' => $court->name ?? '—',
        'location_name' => $location->name ?? '—',
        'date' => $startsLocal?->toDateString(),
        'time_from' => $startsLocal?->format('H:i'),
        'time_to' => $endsLocal?->format('H:i'),
        'starts_at' => $startsLocal?->format('d.m.Y H:i'),
        'ends_at' => $endsLocal?->format('H:i'),
        'user_name' => $booking->booker_name,
        'is_guest' => $booking->isGuestBooking(),
        'organizer_id' => $booking->user_id,
        'guest_name' => $booking->guest_name,
        'guest_phone' => $booking->guest_phone,
        'title' => $booking->title,
        'color' => $booking->color,
        'event' => $booking->event,
        'price' => $booking->price_total,
        'status' => $booking->status,
        'status_label' => $statusLabels[$booking->status] ?? $booking->status,
        'status_color' => $statusColors[$booking->status] ?? '#8E8E93',
        'payment_badge' => $paymentBadge($booking),
        'is_series' => $booking->parent_booking_id !== null,
        'can_manage' => $booking->event_id === null,
    ];
};
@endphp

<div class="container">
    <div class="ramka">

        @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        @if($locations->isNotEmpty())
        @include('club._partials.booking_modal', ['locations' => $locations])
        @include('club._partials.booking_details_modal')
        @include('club._partials.timeline', ['locations' => $locations, 'showAddButton' => false])
        @endif

        <div class="d-flex gap-1 mb-2" style="flex-wrap:wrap">
            @if($locations->isNotEmpty())
            <button type="button" class="btn btn-small btn-primary" onclick="window.__openAddBookingModal && window.__openAddBookingModal()">➕ {{ __('club.add_booking') }}</button>
            @endif
            <button type="button" class="btn btn-small btn-primary" id="cbTabPending">{{ __('club.tab_pending') }} ({{ $pending->count() }})</button>
            <button type="button" class="btn btn-small btn-secondary" id="cbTabActive">{{ __('club.tab_active') }} ({{ $active->count() }})</button>
            <button type="button" class="btn btn-small btn-secondary" id="cbTabHistory">{{ __('club.tab_history') }} ({{ $history->count() }})</button>
        </div>

        <div id="cbPanelPending">
            @forelse($pending as $b)
                @php $row = $renderBooking($b); @endphp
                <div class="card mb-2">
                    <div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
                        <div>
                            <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                                <span class="f-12 p-1 px-2" style="background:{{ $row['payment_badge']['color'] }}22;color:{{ $row['payment_badge']['color'] }};border-radius:6px">{{ $row['payment_badge']['label'] }}</span>
                            </div>
                            <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                            <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}@if($row['is_guest']) <span class="f-12 cd">({{ __('club.guest') }}@if($row['guest_phone']), {{ $row['guest_phone'] }}@endif)</span>@endif</div>
                            @if($row['event'])
                            <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                            @endif
                            <div class="f-14">{{ __('club.booking_price') }}: {{ $row['price'] !== null ? $row['price'] . ' ₽' : __('club.price_free') }}</div>
                        </div>
                        <div class="d-flex gap-1" style="flex-wrap:wrap">
                            <form method="POST" action="{{ route('club.bookings.confirm', $b) }}">
                                @csrf
                                <button type="submit" class="btn btn-small btn-primary">{{ __('club.btn_confirm') }}</button>
                            </form>
                            <button type="button" class="btn btn-small btn-secondary" onclick="document.getElementById('cbRejectForm{{ $b->id }}').style.display='flex'">{{ __('club.btn_reject') }}</button>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('club.bookings.reject', $b) }}" id="cbRejectForm{{ $b->id }}" class="form mt-1" style="display:none;gap:6px;align-items:center;flex-wrap:wrap">
                        @csrf
                        <input type="text" name="reason" placeholder="{{ __('club.reject_reason_label') }}" style="flex:1;min-width:12rem">
                        <button type="submit" class="btn btn-small btn-secondary">{{ __('club.btn_reject_confirm') }}</button>
                    </form>
                </div>
            @empty
            <div class="alert alert-info">{{ __('club.no_bookings') }}</div>
            @endforelse
        </div>

        <div id="cbPanelActive" style="display:none">
            @forelse($active as $b)
                @php $row = $renderBooking($b); @endphp
                <div class="card mb-2">
                    <div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
                        <div>
                            <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                                <span class="f-12 p-1 px-2" style="background:{{ $row['status_color'] }}22;color:{{ $row['status_color'] }};border-radius:6px">{{ $row['status_label'] }}</span>
                                <span class="f-12 p-1 px-2" style="background:{{ $row['payment_badge']['color'] }}22;color:{{ $row['payment_badge']['color'] }};border-radius:6px">{{ $row['payment_badge']['label'] }}</span>
                            </div>
                            <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                            <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}@if($row['is_guest']) <span class="f-12 cd">({{ __('club.guest') }}@if($row['guest_phone']), {{ $row['guest_phone'] }}@endif)</span>@endif</div>
                            @if($row['event'])
                            <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                            @endif
                            <div class="f-14">{{ __('club.booking_price') }}: {{ $row['price'] !== null ? $row['price'] . ' ₽' : __('club.price_free') }}</div>
                        </div>
                        @if($row['can_manage'])
                        <div class="d-flex gap-1 cb-booking-actions" style="flex-wrap:wrap" data-booking="{{ json_encode($row) }}">
                            <button type="button" class="btn btn-small btn-secondary cb-edit-btn">{{ __('club.edit_booking') }}</button>
                            <button type="button" class="btn btn-small btn-secondary cb-copy-btn">{{ __('club.copy_booking') }}</button>
                            <button type="button" class="btn btn-small btn-secondary cb-cancel-btn">{{ __('club.cancel_booking') }}</button>
                        </div>
                        @endif
                    </div>
                </div>
            @empty
            <div class="alert alert-info">{{ __('club.no_bookings') }}</div>
            @endforelse
        </div>

        <div id="cbPanelHistory" style="display:none">
            @forelse($history as $b)
                @php $row = $renderBooking($b); @endphp
                <div class="card mb-2">
                    <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                        <span class="f-12 p-1 px-2" style="background:{{ $row['status_color'] }}22;color:{{ $row['status_color'] }};border-radius:6px">{{ $row['status_label'] }}</span>
                                <span class="f-12 p-1 px-2" style="background:{{ $row['payment_badge']['color'] }}22;color:{{ $row['payment_badge']['color'] }};border-radius:6px">{{ $row['payment_badge']['label'] }}</span>
                    </div>
                    <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                    <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}@if($row['is_guest']) <span class="f-12 cd">({{ __('club.guest') }}@if($row['guest_phone']), {{ $row['guest_phone'] }}@endif)</span>@endif</div>
                    @if($row['event'])
                    <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                    @endif
                </div>
            @empty
            <div class="alert alert-info">{{ __('club.no_bookings') }}</div>
            @endforelse
        </div>

    </div>
</div>

<x-slot name="script">
    <script src="/assets/fas.js"></script>
    <script>
        (function () {
            var tabs = {
                pending: { btn: document.getElementById('cbTabPending'), panel: document.getElementById('cbPanelPending') },
                active:  { btn: document.getElementById('cbTabActive'), panel: document.getElementById('cbPanelActive') },
                history: { btn: document.getElementById('cbTabHistory'), panel: document.getElementById('cbPanelHistory') },
            };
            function selectTab(key) {
                Object.keys(tabs).forEach(function (k) {
                    var isActive = k === key;
                    tabs[k].panel.style.display = isActive ? '' : 'none';
                    tabs[k].btn.classList.toggle('btn-primary', isActive);
                    tabs[k].btn.classList.toggle('btn-secondary', !isActive);
                });
            }
            tabs.pending.btn.addEventListener('click', function () { selectTab('pending'); });
            tabs.active.btn.addEventListener('click', function () { selectTab('active'); });
            tabs.history.btn.addEventListener('click', function () { selectTab('history'); });
        })();

        (function () {
            function toModalBooking(row) {
                return {
                    id: row.id,
                    court_id: row.court_id,
                    direction_id: row.direction_id,
                    date: row.date,
                    time_from: row.time_from,
                    time_to: row.time_to,
                    title: row.title,
                    color: row.color,
                    is_guest: row.is_guest,
                    organizer_id: row.organizer_id,
                    organizer_label: row.user_name,
                    booker_name: row.user_name,
                    guest_name: row.guest_name,
                    guest_phone: row.guest_phone,
                    status: row.status,
                    is_series: row.is_series,
                };
            }

            document.querySelectorAll('.cb-booking-actions').forEach(function (wrap) {
                var row = JSON.parse(wrap.getAttribute('data-booking'));
                var booking = toModalBooking(row);

                var editBtn = wrap.querySelector('.cb-edit-btn');
                var copyBtn = wrap.querySelector('.cb-copy-btn');
                var cancelBtn = wrap.querySelector('.cb-cancel-btn');

                if (editBtn) editBtn.addEventListener('click', function () {
                    if (window.__openBookingModalForEdit) window.__openBookingModalForEdit(booking);
                });
                if (copyBtn) copyBtn.addEventListener('click', function () {
                    if (window.__openBookingModalForCopy) window.__openBookingModalForCopy(booking);
                });
                if (cancelBtn) cancelBtn.addEventListener('click', function () {
                    if (window.__openBookingCancelModal) window.__openBookingCancelModal(booking);
                });
            });
        })();
    </script>
</x-slot>
</x-voll-layout>
