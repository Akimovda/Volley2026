<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
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
    .footer { margin-top: 18px; font-size: 9px; color: #aaa; border-top: 1px solid #e2e8f0; padding-top: 6px; }
</style>
</head>
<body>

<div class="header">
    <div class="title">{{ $event->title }}</div>
    @php
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
    <div class="meta">📅 <strong>Дата:</strong> {{ $dateLine }}</div>
    <div class="meta">📍 <strong>Место:</strong> {{ $locationLine }}</div>
</div>

<div class="stats">
    Участников: <strong>{{ $registrations->count() }}</strong>
</div>

<table>
    <thead>
        <tr>
            <th style="width:28px">#</th>
            <th>Имя</th>
            <th style="width:110px">Телефон</th>
            <th>Комментарий</th>
        </tr>
    </thead>
    <tbody>
        @forelse($registrations as $i => $r)
        <tr>
            <td class="num">{{ $i + 1 }}</td>
            <td>
                {{ $r->name ?: ('User #' . $r->user_id) }}
                @if(!empty($r->is_bot)) <span style="color:#888">(бот)</span> @endif
            </td>
            <td>{{ $r->phone ?: '—' }}</td>
            <td class="note">{{ $r->organizer_note ?: '' }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="4" style="text-align:center;color:#999;padding:12px;">Нет активных регистраций</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="footer">
    Сформировано: {{ now()->format('d.m.Y H:i') }} · volleyplay.club
</div>

</body>
</html>
