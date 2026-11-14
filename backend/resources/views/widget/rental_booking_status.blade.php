<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ __('club.rb_status_title') }}</title>
    <style>
        body{margin:0;padding:16px;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f6f7f9;color:#1a1a1a}
        .box{max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:20px;box-shadow:0 2px 12px rgba(0,0,0,.08)}
        h1{font-size:20px;margin:0 0 4px}.loc{color:#6b7280;margin-bottom:14px;font-size:14px}
        .row{display:flex;justify-content:space-between;gap:12px;padding:10px 0;border-top:1px solid #e5e7eb;font-size:15px}
        .row small{display:block;color:#6b7280}.st{font-weight:700;white-space:nowrap}
        .st.paid,.st.confirmed{color:#059669}.st.pending{color:#d97706}.st.cancelled,.st.expired{color:#dc2626}
        .tot{display:flex;justify-content:space-between;font-size:18px;font-weight:800;padding-top:12px;border-top:2px solid #e5e7eb}
        .btn{display:inline-block;margin-top:14px;padding:12px 18px;border:0;border-radius:10px;background:#f59e0b;color:#fff;font-weight:700;font-size:15px;cursor:pointer;text-decoration:none}
        .btn.sec{background:#e5e7eb;color:#1a1a1a}.note{margin-top:12px;color:#6b7280;font-size:13px}.err{color:#dc2626;margin-bottom:10px}
    </style>
    @if($canPay && $bookings->where('status','pending')->isNotEmpty())<meta http-equiv="refresh" content="30">@endif
</head>
<body>
<div class="box">
    <h1>{{ __('club.rb_status_title') }}</h1>
    @if($location)<div class="loc">{{ $location->name }}@if($location->address), {{ $location->address }}@endif</div>@endif
    @if(session('error'))<div class="err">{{ session('error') }}</div>@endif
    @foreach($bookings as $b)
        @php
            $tz = $b->court?->direction?->location?->effectiveTimezone() ?? 'Europe/Moscow';
            $label = match(true) {
                $b->status === 'pending' && $b->payment_mode === 'prepaid' => __('club.rb_status_pending_pay'),
                $b->status === 'pending' => __('club.rb_pending'),
                default => __('club.rb_status_' . $b->status),
            };
        @endphp
        <div class="row">
            <div>{{ $b->court?->name }}<small>{{ $b->starts_at->copy()->setTimezone($tz)->translatedFormat('d F Y, H:i') }}–{{ $b->ends_at->copy()->setTimezone($tz)->format('H:i') }}@if($b->price_total) · {{ number_format($b->price_total, 0, ',', ' ') }} ₽@endif</small></div>
            <div class="st {{ $b->status }}">{{ $label }}</div>
        </div>
    @endforeach
    <div class="tot"><span>{{ __('club.rb_total') }}</span><span>{{ number_format($total, 0, ',', ' ') }} ₽</span></div>
    @if($canPay)
        @php $exp = $bookings->first()->expires_at?->copy()->setTimezone($bookings->first()->court?->direction?->location?->effectiveTimezone() ?? 'Europe/Moscow'); @endphp
        <form method="POST" action="{{ route("rental_booking.pay", ["token" => $token, "lang" => app()->getLocale()]) }}">@csrf
            <button class="btn" type="submit">{{ __('club.rb_pay') }}</button>
        </form>
        <div class="note">{{ __('club.rb_pay_until', ['time' => $exp?->format('H:i')]) }}</div>
    @elseif($bookings->first()->payment_mode !== 'prepaid' && $bookings->where('status','pending')->isNotEmpty())
        <div class="note">{{ __('club.rb_status_pending_request') }}</div>
    @endif
    <div class="note">{{ __('club.rb_keep_link') }}</div>
    <a class="btn sec" href="{{ route('rental_booking.status', ['token' => $token, 'lang' => app()->getLocale()]) }}">{{ __('club.rb_refresh') }}</a>
</div>
</body>
</html>
