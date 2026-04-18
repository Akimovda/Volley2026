<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 20px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 16px 0 8px; color: #2967BA; }
        h3 { font-size: 13px; margin: 12px 0 6px; color: #555; }
        .meta { font-size: 11px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #f3f4f6; padding: 6px 8px; text-align: left; font-size: 11px; border-bottom: 2px solid #d1d5db; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $event->title }}</h1>
    <div class="meta">
        {{ $event->direction === 'beach' ? 'Пляжный волейбол' : 'Классический волейбол' }}
        @if($event->starts_at) · {{ $event->starts_at->format('d.m.Y') }} @endif
        · Расписание турнира
    </div>

    @foreach($stages as $stage)
        <h2>{{ $stage->name }} ({{ strtoupper($stage->matchFormat()) }})</h2>

        @if($stage->groups->isNotEmpty())
            @foreach($stage->groups as $group)
                <h3>{{ $group->name }}</h3>
                <table>
                    <thead><tr><th>Команды:</th></tr></thead>
                    <tbody>
                        @foreach($group->teams as $team)
                            <tr><td>{{ $team->name }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        @if($stage->matches->isNotEmpty())
            <table>
                <thead>
                    <tr>
                        <th class="tc">#</th>
                        <th class="tc">Тур</th>
                        <th>Дома</th>
                        <th>Гости</th>
                        <th class="tc">Площадка</th>
                        <th class="tc">Время</th>
                        <th class="tc">Счёт</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($stage->matches->sortBy(['round', 'match_number']) as $m)
                        <tr>
                            <td class="tc">{{ $m->match_number }}</td>
                            <td class="tc">R{{ $m->round }}</td>
                            <td class="{{ $m->winner_team_id === $m->team_home_id ? 'bold' : '' }}">{{ $m->teamHome->name ?? 'TBD' }}</td>
                            <td class="{{ $m->winner_team_id === $m->team_away_id ? 'bold' : '' }}">{{ $m->teamAway->name ?? 'TBD' }}</td>
                            <td class="tc">{{ $m->court ?? '—' }}</td>
                            <td class="tc">{{ $m->scheduled_at ? $m->scheduled_at->format('H:i') : '—' }}</td>
                            <td class="tc bold">{{ $m->scoreFormatted() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach

    <div class="footer">VolleyPlay.Club · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
