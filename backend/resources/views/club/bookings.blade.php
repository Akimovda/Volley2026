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

$renderBooking = function ($booking) use ($statusLabels, $statusColors) {
    $court = $booking->court;
    $location = $court?->direction?->location;
    $user = $booking->user;
    $userName = $user ? (trim(($user->last_name ?? '') . ' ' . ($user->first_name ?? '')) ?: $user->name) : '—';
    return [
        'id' => $booking->id,
        'court_name' => $court->name ?? '—',
        'location_name' => $location->name ?? '—',
        'starts_at' => $booking->starts_at?->format('d.m.Y H:i'),
        'ends_at' => $booking->ends_at?->format('H:i'),
        'user_name' => $userName,
        'event' => $booking->event,
        'price' => $booking->price_total,
        'status' => $booking->status,
        'status_label' => $statusLabels[$booking->status] ?? $booking->status,
        'status_color' => $statusColors[$booking->status] ?? '#8E8E93',
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
                            <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}</div>
                            <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                            <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}</div>
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
                    <div class="b-700">{{ $row['location_name'] }} · {{ $row['court_name'] }}
                        <span class="f-12 p-1 px-2" style="background:{{ $row['status_color'] }}22;color:{{ $row['status_color'] }};border-radius:6px">{{ $row['status_label'] }}</span>
                    </div>
                    <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                    <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}</div>
                    @if($row['event'])
                    <div class="f-14"><a href="{{ route('events.show', $row['event']) }}" class="blink">{{ $row['event']->title }}</a></div>
                    @endif
                    <div class="f-14">{{ __('club.booking_price') }}: {{ $row['price'] !== null ? $row['price'] . ' ₽' : __('club.price_free') }}</div>
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
                    </div>
                    <div class="f-14">{{ $row['starts_at'] }}–{{ $row['ends_at'] }}</div>
                    <div class="f-14">{{ __('club.booking_by') }}: {{ $row['user_name'] }}</div>
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
    </script>
</x-slot>
</x-voll-layout>
