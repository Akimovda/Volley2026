<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
@php
    $fontNormal = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    $fontBold   = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    $logoPath   = (isset($brand) && $brand && $brand->logo_day_path) ? public_path($brand->logo_day_path) : public_path('icons/web-app-manifest-192x192.png');
    $logoB64    = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<style>
@@font-face { font-family: 'DejaVu Sans'; src: url("{{ $fontNormal }}") format('truetype'); font-weight: normal; font-style: normal; }
@@font-face { font-family: 'DejaVu Sans'; src: url("{{ $fontBold }}") format('truetype'); font-weight: bold; font-style: normal; }
@@page { size: A4 landscape; margin: 12mm 10mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #222; }
.header { display: table; width: 100%; border-bottom: 2px solid #1a56db; padding-bottom: 8px; margin-bottom: 10px; }
.header-logo { display: table-cell; width: 48px; vertical-align: middle; padding-right: 10px; }
.header-logo img { width: 40px; height: 40px; }
.header-info { display: table-cell; vertical-align: middle; }
.title { font-size: 15px; font-weight: bold; color: #1a56db; margin-bottom: 3px; }
.meta { font-size: 9px; color: #555; margin-bottom: 1px; }
table { width: 100%; border-collapse: collapse; margin-top: 6px; }
thead tr { background: #1a56db; color: #fff; }
thead th { padding: 5px 4px; text-align: left; font-size: 8px; }
tbody tr:nth-child(even) { background: #f4f7ff; }
tbody tr { border-bottom: 1px solid #e2e8f0; }
tbody td { padding: 4px; vertical-align: top; font-size: 8px; }
.footer { margin-top: 10px; font-size: 8px; color: #888; border-top: 1px solid #e2e8f0; padding-top: 5px; }
</style>
</head>
<body>
<div class="header">
    @if($logoB64)<div class="header-logo"><img src="{{ $logoB64 }}" alt="logo"></div>@endif
    <div class="header-info">
        <div class="title">{{ __('subscriptions.rep_title') }}</div>
        <div class="meta"><strong>{{ __('subscriptions.rep_filters') }}:</strong> {{ $filters }}</div>
        <div class="meta"><strong>{{ __('subscriptions.rep_total') }}:</strong> {{ count($rows) }}</div>
    </div>
</div>
<table>
    <thead><tr>@foreach($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
    <tbody>
    @forelse($rows as $r)
        <tr>
            <td>#{{ $r['id'] }}</td><td>{{ $r['name'] }}</td><td>{{ $r['phone'] }}</td><td>{{ $r['template'] }}</td>
            <td>{{ $r['visits'] }}</td><td>{{ $r['status'] }}</td><td>{{ $r['starts'] }}</td><td>{{ $r['expires'] }}</td>
            <td>{{ $r['extended'] }}</td><td>{{ $r['purchase_n'] }}</td><td>{{ $r['repeat'] }}</td>
        </tr>
    @empty
        <tr><td colspan="{{ count($headers) }}" style="text-align:center;color:#999;padding:10px;">{{ __('subscriptions.org_empty') }}</td></tr>
    @endforelse
    </tbody>
</table>
<div class="footer">{{ __('subscriptions.rep_note') }}<br>{{ now()->format('d.m.Y H:i') }} &middot; volleyplay.club</div>
</body>
</html>
