<x-voll-layout body_class="player-bookings-page">
<x-slot name="title">{{ __('club.my_bookings') }}</x-slot>
<x-slot name="h1">{{ __('club.my_bookings') }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('club.my_bookings') }}</span>
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

$courtBookingService = app(\App\Services\CourtBookingService::class);

$renderMyBooking = function ($booking) use ($statusLabels, $statusColors, $courtBookingService) {
    $court = $booking->court;
    $location = $court?->direction?->location;
    $tz = $location?->effectiveTimezone() ?? 'Europe/Moscow';
    $startsLocal = $booking->starts_at?->copy()->setTimezone($tz);
    $endsLocal = $booking->ends_at?->copy()->setTimezone($tz);
    $cancelHours = $location?->booking_cancel_hours ?? 24;
    $canCancel = in_array($booking->status, ['pending', 'confirmed', 'paid'], true)
        && now()->lt($booking->starts_at->copy()->subHours($cancelHours));
    $canPay = $booking->status === 'pending'
        && $booking->payment_mode === 'prepaid'
        && $booking->expires_at
        && now()->lt($booking->expires_at);
    $refundWarning = $booking->status === 'paid'
        && $booking->payment_mode === 'prepaid'
        && $canCancel
        && !$courtBookingService->refundWouldApply($booking);

    return [
        'id' => $booking->id,
        'location_name' => $location->name ?? '—',
        'court_name' => $court->name ?? '—',
        'starts_at' => $startsLocal?->format('d.m.Y H:i'),
        'ends_at' => $endsLocal?->format('H:i'),
        'price' => $booking->price_total,
        'status' => $booking->status,
        'status_label' => $statusLabels[$booking->status] ?? $booking->status,
        'status_color' => $statusColors[$booking->status] ?? '#8E8E93',
        'event' => $booking->event,
        'can_cancel' => $canCancel,
        'cancel_hours' => $cancelHours,
        'can_pay' => $canPay,
        'expires_at_iso' => $canPay ? $booking->expires_at->toIso8601String() : null,
        'refund_warning' => $refundWarning,
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

        <div class="d-flex gap-1 mb-2" style="flex-wrap:wrap">
            <button type="button" class="btn btn-small btn-primary" id="pmbTabActive">{{ __('club.tab_active') }} ({{ $active->count() }})</button>
            <button type="button" class="btn btn-small btn-secondary" id="pmbTabHistory">{{ __('club.tab_history') }} ({{ $history->count() }})</button>
        </div>

        <div id="pmbPanelActive">
            @forelse($active as $b)
                @php $row = $renderMyBooking($b); @endphp
                <div class="card mb-2">
                    <div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
                        <div>
                            <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                                <span class="f-12 p-1 px-2" style="background:{{ $row['status_color'] }}22;color:{{ $row['status_color'] }};border-radius:6px">{{ $row['status_label'] }}</span>
                            </div>
                            <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                            @if($row['event'])
                            <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                            @endif
                            <div class="f-14">{{ __('club.booking_price') }}: {{ $row['price'] !== null ? number_format((float) $row['price'], 0, ',', ' ') . ' ₽' : __('club.price_free') }}</div>
                            @if($row['status'] === 'pending' && !$row['can_pay'])
                            <div class="f-14 cd">{{ __('club.booking_pending_info') }}</div>
                            @endif
                            @if($row['can_pay'])
                            <div class="f-14 cd" data-pay-countdown="{{ $row['expires_at_iso'] }}">{{ __('club.booking_awaiting_payment') }}</div>
                            @endif
                        </div>
                        <div class="d-flex gap-1" style="flex-wrap:wrap">
                            @if($row['can_pay'])
                            <form method="POST" action="{{ route('player.bookings.pay', $b) }}">
                                @csrf
                                <button type="submit" class="btn btn-small btn-primary">{{ __('club.booking_pay_button', ['amount' => number_format((float) $row['price'], 0, ',', ' ')]) }}</button>
                            </form>
                            @endif
                            @if($row['can_cancel'])
                            <form method="POST" action="{{ route('player.bookings.cancel', $b) }}" onsubmit="return confirm({{ json_encode($row['refund_warning'] ? __('club.cancel_booking') . "\n\n" . __('club.cancel_no_refund_warning') : __('club.cancel_booking')) }} + '?')">
                                @csrf
                                <button type="submit" class="btn btn-small btn-secondary">{{ __('club.cancel_booking') }}</button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
            <div class="alert alert-info">{{ __('club.no_bookings') }}</div>
            @endforelse
        </div>

        <div id="pmbPanelHistory" style="display:none">
            @forelse($history as $b)
                @php $row = $renderMyBooking($b); @endphp
                <div class="card mb-2">
                    <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                        <span class="f-12 p-1 px-2" style="background:{{ $row['status_color'] }}22;color:{{ $row['status_color'] }};border-radius:6px">{{ $row['status_label'] }}</span>
                    </div>
                    <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                    @if($row['event'])
                    <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                    @endif
                    <div class="f-14">{{ __('club.booking_price') }}: {{ $row['price'] !== null ? number_format((float) $row['price'], 0, ',', ' ') . ' ₽' : __('club.price_free') }}</div>
                </div>
            @empty
            <div class="alert alert-info">{{ __('club.no_bookings') }}</div>
            @endforelse
        </div>

    </div>
</div>

<x-slot name="script">
    <script>
        (function () {
            var tabs = {
                active:  { btn: document.getElementById('pmbTabActive'), panel: document.getElementById('pmbPanelActive') },
                history: { btn: document.getElementById('pmbTabHistory'), panel: document.getElementById('pmbPanelHistory') },
            };
            function selectTab(key) {
                Object.keys(tabs).forEach(function (k) {
                    var isActive = k === key;
                    tabs[k].panel.style.display = isActive ? '' : 'none';
                    tabs[k].btn.classList.toggle('btn-primary', isActive);
                    tabs[k].btn.classList.toggle('btn-secondary', !isActive);
                });
            }
            tabs.active.btn.addEventListener('click', function () { selectTab('active'); });
            tabs.history.btn.addEventListener('click', function () { selectTab('history'); });

            // Обратный отсчёт до expires_at на pending-prepaid бронях
            var countdownEls = document.querySelectorAll('[data-pay-countdown]');
            if (countdownEls.length) {
                var labelTpl = @json(__('club.booking_pay_countdown_label'));
                var expiredTpl = @json(__('club.booking_pay_countdown_expired'));
                function tick() {
                    countdownEls.forEach(function (el) {
                        var expiresAt = new Date(el.getAttribute('data-pay-countdown')).getTime();
                        var diffMs = expiresAt - Date.now();
                        if (diffMs <= 0) {
                            el.textContent = expiredTpl;
                            return;
                        }
                        var mins = Math.floor(diffMs / 60000);
                        var secs = Math.floor((diffMs % 60000) / 1000);
                        el.textContent = labelTpl.replace(':mins', mins).replace(':secs', String(secs).padStart(2, '0'));
                    });
                }
                tick();
                setInterval(tick, 1000);
            }
        })();
    </script>
</x-slot>
</x-voll-layout>
