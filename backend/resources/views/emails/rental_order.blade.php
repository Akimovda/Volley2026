@php($isClient = !$isOwner)
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<body style="margin:0;padding:20px;background:#f6f7f9;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1a1a1a">
<div style="max-width:560px;margin:0 auto;background:#fff;border-radius:14px;padding:24px">
    <h2 style="margin:0 0 6px;font-size:20px">{{ __('club.rm_title_' . $kind) }}</h2>
    @if($location)<div style="color:#6b7280;font-size:14px;margin-bottom:14px">{{ $location->name }}@if($location->address), {{ $location->address }}@endif</div>@endif

    <p style="font-size:15px;line-height:1.5;margin:0 0 12px">{{ __('club.rm_text_' . $kind) }}</p>
    @if($reason)<p style="font-size:14px;color:#b91c1c;margin:0 0 12px">{{ __('club.rm_reason') }}: {{ $reason }}</p>@endif

    <table style="width:100%;border-collapse:collapse;font-size:15px">
        @foreach($bookings as $b)
            @php($tz = $b->court?->direction?->location?->effectiveTimezone() ?? 'Europe/Moscow')
            <tr style="border-top:1px solid #e5e7eb">
                <td style="padding:10px 0">{{ $b->court?->name }}<br><span style="color:#6b7280;font-size:13px">{{ $b->starts_at->copy()->setTimezone($tz)->translatedFormat('d F Y, H:i') }}–{{ $b->ends_at->copy()->setTimezone($tz)->format('H:i') }}</span></td>
                <td style="padding:10px 0;text-align:right;white-space:nowrap">@if($b->price_total){{ number_format($b->price_total, 0, ',', ' ') }} ₽@endif</td>
            </tr>
        @endforeach
        @if($total > 0)
            <tr style="border-top:2px solid #e5e7eb"><td style="padding:10px 0;font-weight:700">{{ __('club.rb_total') }}</td><td style="padding:10px 0;text-align:right;font-weight:700">{{ number_format($total, 0, ',', ' ') }} ₽</td></tr>
        @endif
    </table>

    @if($isOwner && $first)
        <div style="margin-top:14px;padding:12px;background:#f9fafb;border-radius:10px;font-size:14px;line-height:1.6">
            <b>{{ __('club.rm_client') }}:</b> {{ $first->guest_name }}<br>
            {{ __('club.rb_f_phone') }}: {{ $first->guest_phone ? '+' . $first->guest_phone : '—' }}<br>
            Email: {{ $first->guest_email ?: '—' }}
            @if($first->guest_comment)<br>{{ __('club.rm_comment') }}: {{ $first->guest_comment }}@endif
        </div>
        <p style="margin:16px 0 0"><a href="{{ $panelUrl }}" style="display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;font-weight:700;padding:11px 18px;border-radius:10px">{{ __('club.rm_open_panel') }}</a></p>
    @else
        <p style="margin:16px 0 0"><a href="{{ $statusUrl }}" style="display:inline-block;background:#f59e0b;color:#fff;text-decoration:none;font-weight:700;padding:11px 18px;border-radius:10px">{{ __('club.rm_open_order') }}</a></p>
    @endif
    <p style="margin:18px 0 0;font-size:12px;color:#9ca3af">{{ __('club.rm_footer') }}</p>
</div>
</body>
</html>
