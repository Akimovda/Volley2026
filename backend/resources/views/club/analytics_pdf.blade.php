<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="UTF-8">
@php
    $fontNormal = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    $fontBold   = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    $logoPath   = (isset($brand) && $brand && $brand->logo_day_path) ? public_path($brand->logo_day_path) : public_path('icons/web-app-manifest-192x192.png');
    $logoB64    = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
    $fmtMoney = fn ($v) => number_format((float) $v, 0, ',', ' ') . ' ₽';
    $fmtHours = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ' '), '0'), ',');
    $lvl = fn ($p) => $p < 30 ? '#9E9E9E' : ($p > 70 ? '#34C759' : '#E7612F');
    $center = $stats['center'];
    $allCourts = array_merge(...array_map(fn ($d) => $d['courts'], $stats['directions'] ?: [[]]));
    $busyHours = array_sum(array_column($allCourts, 'occupied_hours'));
    $totalHours = array_sum(array_column($allCourts, 'available_hours'));
@endphp
<style>
@@font-face { font-family: 'DejaVu Sans'; src: url("{{ $fontNormal }}") format('truetype'); font-weight: normal; font-style: normal; }
@@font-face { font-family: 'DejaVu Sans'; src: url("{{ $fontBold }}") format('truetype'); font-weight: bold; font-style: normal; }
@@page { size: A4 portrait; margin: 16mm 14mm; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #222; }
.header { width: 100%; border-bottom: 2px solid #E7612F; padding-bottom: 8px; margin-bottom: 12px; }
.header td { vertical-align: middle; }
.title { font-size: 17px; font-weight: bold; }
.sub { font-size: 10px; color: #666; margin-top: 3px; }
.kpis { width: 100%; border-collapse: separate; border-spacing: 6px 0; margin: 0 -6px 14px; }
.kpi { border: 1px solid #ddd; border-radius: 6px; padding: 8px 10px; width: 25%; }
.kpi .l { font-size: 8.5px; color: #777; }
.kpi .v { font-size: 15px; font-weight: bold; margin-top: 3px; }
.kpi .s { font-size: 8.5px; color: #888; margin-top: 2px; }
h3 { font-size: 12px; margin: 14px 0 6px; padding: 5px 8px; background: #fdf0ea; border-left: 3px solid #E7612F; }
table.t { width: 100%; border-collapse: collapse; }
table.t th { text-align: left; font-size: 8.5px; color: #666; border-bottom: 1px solid #ccc; padding: 4px 5px; }
table.t td { padding: 5px; border-bottom: 1px solid #eee; vertical-align: middle; }
.r { text-align: right; }
.bar { width: 100%; height: 7px; background: #e5e7eb; border-radius: 4px; }
.fill { height: 7px; border-radius: 4px; }
.foot { margin-top: 14px; font-size: 8.5px; color: #999; }
</style>
</head>
<body>
<table class="header"><tr>
    <td style="width:60px">@if($logoB64)<img src="{{ $logoB64 }}" style="height:40px">@endif</td>
    <td>
        <div class="title">{{ __('club.report_title') }}</div>
        <div class="sub">{{ $location->name }} · {{ $periodLabel }}</div>
    </td>
    <td class="r sub">{{ __('club.report_generated', ['date' => now($location->effectiveTimezone())->format('d.m.Y H:i')]) }}</td>
</tr></table>

<table class="kpis"><tr>
    <td class="kpi"><div class="l">{{ __('club.kpi_occupancy') }}</div><div class="v">{{ $center['occupancy_pct'] }}%</div></td>
    <td class="kpi"><div class="l">{{ __('club.kpi_revenue') }}</div><div class="v">{{ $fmtMoney($center['revenue']) }}</div></td>
    <td class="kpi"><div class="l">{{ __('club.kpi_online') }} / {{ __('club.kpi_on_site') }}</div><div class="v" style="font-size:11px">{{ $fmtMoney($center['revenue_online']) }} / {{ $fmtMoney($center['revenue_on_site']) }}</div></td>
    <td class="kpi"><div class="l">{{ __('club.kpi_hours') }}</div><div class="v">{{ $fmtHours($busyHours) }}</div><div class="s">{{ __('club.kpi_hours_of', ['busy' => $fmtHours($busyHours), 'total' => $fmtHours($totalHours)]) }}</div></td>
</tr></table>

@forelse($stats['directions'] as $dir)
    <h3>{{ $dir['direction'] === 'beach' ? __('club.direction_beach') : __('club.direction_classic') }}
        — {{ $dir['occupancy_pct'] }}% · {{ $fmtMoney($dir['revenue']) }}</h3>
    <table class="t">
        <tr>
            <th style="width:22%">{{ __('club.xl_court') }}</th>
            <th style="width:26%">{{ __('club.xl_occupancy') }}</th>
            <th class="r" style="width:8%">%</th>
            <th class="r" style="width:16%">{{ __('club.xl_hours_busy') }} / {{ __('club.xl_hours_total') }}</th>
            <th class="r" style="width:14%">{{ __('club.kpi_revenue') }}</th>
            <th class="r" style="width:14%">{{ __('club.kpi_online') }}</th>
        </tr>
        @foreach($dir['courts'] as $c)
        <tr>
            <td>{{ $c['name'] }}</td>
            <td><div class="bar"><div class="fill" style="width: {{ min(100, max(1, $c['occupancy_pct'])) }}%; background: {{ $lvl($c['occupancy_pct']) }}"></div></div></td>
            <td class="r"><b>{{ $c['occupancy_pct'] }}%</b></td>
            <td class="r">{{ $fmtHours($c['occupied_hours']) }} / {{ $fmtHours($c['available_hours']) }}</td>
            <td class="r">{{ $fmtMoney($c['revenue']) }}</td>
            <td class="r">{{ $fmtMoney($c['revenue_online']) }}</td>
        </tr>
        @endforeach
    </table>
@empty
    <p>{{ __('club.analytics_no_courts') }}</p>
@endforelse

<div class="foot">{{ __('club.legend_title') }}: <span style="color:#9E9E9E">■</span> {{ __('club.legend_low') }} · <span style="color:#E7612F">■</span> {{ __('club.legend_mid') }} · <span style="color:#34C759">■</span> {{ __('club.legend_high') }}</div>
</body>
</html>
