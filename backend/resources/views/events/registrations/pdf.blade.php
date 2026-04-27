<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
@php
    $fontNormal = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf');
    $fontBold   = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
@endphp
<style>
@@font-face {
    font-family: 'DejaVu Sans';
    src: url("{{ $fontNormal }}") format('truetype');
    font-weight: normal;
    font-style: normal;
}
@@font-face {
    font-family: 'DejaVu Sans';
    src: url("{{ $fontBold }}") format('truetype');
    font-weight: bold;
    font-style: normal;
}
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
    .header { border-bottom: 2px solid #1a56db; padding-bottom: 10px; margin-bottom: 14px; }
    .title { font-size: 18px; font-weight: bold; color: #1a56db; margin-bottom: 4px; }
    .meta { font-size: 11px; color: #555; margin-bottom: 2px; }
    .meta strong { color: #222; }
    .stats { margin: 12px 0 16px; font-size: 11px; color: #444; }
    table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    thead tr { background: #1a56db; color: #fff; }
    thead th { padding: 7px 8px; text-align: left; font-weight: bold; font-size: 10px; }
    tbody tr:nth-child(even) { background: #f4f7ff; }
    tbody tr { border-bottom: 1px solid #e2e8f0; }
    tbody td { padding: 6px 8px; vertical-align: top; font-size: 10px; }
    .num { color: #999; }
    .note { color: #555; font-style: italic; }
    .pos { color: #444; }
    .footer { margin-top: 18px; font-size: 9px; color: #aaa; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>
@php
    $posLabels = [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный',
        'libero'   => 'Либеро',
        'reserve'  => 'Резерв',
    ];
    $dateLine = '—';
    if ($startsLocal) {
        $dateLine = $startsLocal->format('d.m.Y') . ' · ' . $startsLocal->format('H:i');
        if ($endsLocal) $dateLine .= '–' . $endsLocal->format('H:i');
        $dateLine .= ' (' . $tz . ')';
    }
    $locationLine = '—';
    if ($location) {
        $parts = array_filter([$location->city_name ?? null, $location->address ?? null, $location->name ?? null]);
        $locationLine = implode(', ', $parts) ?: '—';
    }
@endphp

<div class="header">
    <div class="title">{{ $event->title }}</div>
    <div class="meta"><strong>Дата:</strong> {{ $dateLine }}</div>
    <div class="meta"><strong>Место:</strong> {{ $locationLine }}</div>
</div>

<div class="stats">
    Участников: <strong>{{ $registrations->count() }}</strong>
</div>

<table>
    <thead>
        <tr>
            <th style="width:24px">#</th>
            <th>Имя</th>
            <th style="width:105px">Телефон</th>
            <th style="width:90px">Позиция</th>
            <th>Комментарий</th>
        </tr>
    </thead>
    <tbody>
        @forelse($registrations as $i => $r)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>{{ $r->name ?: ('User #' . $r->user_id) }}@if(!empty($r->is_bot)) (бот)@endif</td>
            <td>{{ $r->phone ?: '—' }}</td>
            <td class="pos">{{ $posLabels[$r->position ?? ''] ?? ($r->position ?: '—') }}</td>
            <td class="note">{{ $r->organizer_note ?: '' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="5" style="text-align:center;color:#999;padding:12px;">Нет активных регистраций</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Сформировано: {{ now()->format('d.m.Y H:i') }} &middot; volleyplay.club
</div>

</body>
</html>
