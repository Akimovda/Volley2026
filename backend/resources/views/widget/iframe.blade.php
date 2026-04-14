<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мероприятия</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #fff; color: #1a1a1a; padding: 16px; }
        .header { font-weight: 700; font-size: 16px; margin-bottom: 14px; color: {{ $widget->getSetting('color', '#f59e0b') }}; }
        .event-card { border: 1px solid #eee; border-radius: 12px; padding: 14px; margin-bottom: 10px; }
        .event-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .event-meta { font-size: 13px; color: #666; margin-bottom: 4px; }
        .event-btn { display: inline-block; margin-top: 8px; padding: 6px 14px; background: {{ $widget->getSetting('color', '#f59e0b') }}; color: #fff; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; }
        .event-btn:hover { opacity: 0.9; }
        .empty { color: #999; font-size: 14px; padding: 20px 0; }
        .footer { margin-top: 12px; font-size: 11px; color: #ccc; text-align: right; }
        .footer a { color: #ccc; }
    </style>
</head>
<body>
    <div class="header">🏐 Ближайшие мероприятия</div>

    @forelse($events as $event)
        <div class="event-card">
            <div class="event-title">{{ $event['title'] }}</div>
            <div class="event-meta">📅 {{ $event['starts_at'] }}</div>
            @if($event['location'])
                <div class="event-meta">📍 {{ $event['location'] }}</div>
            @endif
            @if($event['slots_info'])
                <div class="event-meta">👥 {{ $event['slots_info'] }}</div>
            @endif
            <a href="{{ $event['url'] }}" target="_blank" class="event-btn">Записаться</a>
        </div>
    @empty
        <div class="empty">Нет запланированных мероприятий.</div>
    @endforelse

    <div class="footer">на базе <a href="https://volley-bot.store" target="_blank">volley-bot.store</a></div>
</body>
</html>
