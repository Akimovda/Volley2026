<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Мероприятия</title>
    <link href="/assets/lib.css" rel="stylesheet">
    <link href="/assets/style.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: transparent;
            padding: 12px;
            margin: 0;
        }
        .widget-header {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 14px;
            color: {{ $widget->getSetting('color', '#f59e0b') }};
        }
        .widget-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
        }
        @media (max-width: 900px) {
            .widget-cards { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 560px) {
            .widget-cards { grid-template-columns: 1fr; }
        }
        .widget-card {
            border-radius: 14px;
            border: 1px solid var(--border, #eee);
            background: var(--card-bg, #fff);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .widget-card-body {
            flex: 1;
        }
        .widget-card-body {
            padding: 14px 16px 10px;
        }
        .widget-card-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text, #1a1a1a);
            text-decoration: none;
            display: block;
            margin-bottom: 8px;
            line-height: 1.3;
        }
        .widget-card-title:hover { opacity: .8; }
        .widget-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 10px;
        }
        .widget-meta-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-size: 14px;
            color: var(--text-muted, #666);
        }
        .widget-meta-row .emo { flex-shrink: 0; }
        .widget-direction {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .widget-direction.beach   { background: #fef3c7; color: #92400e; }
        .widget-direction.classic { background: #dbeafe; color: #1e40af; }
        .widget-levels {
            display: flex;
            gap: 4px;
            align-items: center;
            margin-bottom: 8px;
        }
        .widget-btn {
            display: block;
            width: 100%;
            padding: 10px;
            background: {{ $widget->getSetting('color', '#f59e0b') }};
            color: #fff;
            text-align: center;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 0 0 14px 14px;
            transition: opacity .15s;
            margin-top: auto;
        }
        .widget-btn:hover { opacity: .88; }
        .widget-slots {
            font-size: 13px;
            color: var(--text-muted, #666);
        }
        .widget-slots strong { color: var(--text, #1a1a1a); }
        .widget-empty {
            color: var(--text-muted, #999);
            font-size: 14px;
            padding: 20px 0;
            text-align: center;
        }
        .widget-footer {
            margin-top: 14px;
            font-size: 11px;
            color: #ccc;
            text-align: right;
        }
        .widget-footer a { color: #ccc; text-decoration: none; }
        .widget-footer a:hover { text-decoration: underline; }
        .widget-price {
            display: inline-block;
            background: #f0fdf4;
            color: #166534;
            padding: 1px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>

<div class="widget-header">🏐 Ближайшие мероприятия</div>

@if(count($events) === 0)
    <div class="widget-empty">Нет запланированных мероприятий.</div>
@else
<div class="widget-cards">
    @foreach($events as $ev)
    <div class="widget-card">
        <div class="widget-card-body">

            {{-- Направление --}}
            @if(($ev['direction'] ?? '') === 'beach')
                <span class="widget-direction beach">🏖 Пляжка</span>
            @else
                <span class="widget-direction classic">🏐 Классика</span>
            @endif

            {{-- Название --}}
            <a href="{{ $ev['url'] }}" target="_blank" class="widget-card-title">
                @if($ev['is_private'])<span title="Приватное">🙈</span> @endif
                {{ $ev['title'] }}
            </a>

            <div class="widget-meta">
                {{-- Дата и время --}}
                <div class="widget-meta-row">
                    <span class="emo">📅</span>
                    <span>{{ $ev['date_long'] }}, {{ $ev['time_range'] }}</span>
                </div>

                {{-- Адрес --}}
                @if(!empty($ev['address']))
                <div class="widget-meta-row">
                    <span class="emo">📍</span>
                    <span>{{ $ev['address'] }}</span>
                </div>
                @endif

                {{-- Уровень --}}
                @if(!is_null($ev['level_min']) || !is_null($ev['level_max']))
                <div class="widget-meta-row">
                    <span class="emo">🎚</span>
                    <span>
                        Уровень:
                        @if(!is_null($ev['level_min']))
                            <span class="levelmark level-{{ $ev['level_min'] }}">{{ $ev['level_min'] }}</span>
                        @endif
                        —
                        @if(!is_null($ev['level_max']))
                            <span class="levelmark level-{{ $ev['level_max'] }}">{{ $ev['level_max'] }}</span>
                        @endif
                    </span>
                </div>
                @endif

                {{-- Места --}}
                @if(!empty($ev['slots_info']))
                <div class="widget-meta-row">
                    <span class="emo">👥</span>
                    <span class="widget-slots">
                        Осталось мест: <strong>{{ $ev['slots_info']['free'] }}</strong>
                        из <strong>{{ $ev['slots_info']['max'] }}</strong>
                    </span>
                </div>
                @endif

                {{-- Цена --}}
                @if(!empty($ev['price']))
                <div class="widget-meta-row">
                    <span class="emo">💰</span>
                    <span class="widget-price">{{ $ev['price'] }}</span>
                </div>
                @endif
            </div>
        </div>

        <a href="{{ $ev['url'] }}" target="_blank" class="widget-btn">
            Записаться на сайте
        </a>
    </div>
    @endforeach
</div>
@endif

<div class="widget-footer">
    на базе <a href="https://volley-bot.store" target="_blank">volley-bot.store</a>
</div>

</body>
</html>
