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
        .wr { color: #E7612F; font-weight: bold; }
        .win { color: #059669; }
        .loss { color: #dc2626; }
        .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <h1>{{ $event->title }} — Итоги</h1>
    <div class="meta">
        {{ $event->direction === 'beach' ? 'Пляжный волейбол' : 'Классический волейбол' }}
        @if($event->starts_at) · {{ $event->starts_at->format('d.m.Y') }} @endif
    </div>


    {{-- Итоговая классификация --}}
    @php
        $classification = app(\App\Services\TournamentStatsService::class)->calculateFinalClassification($event);
    @endphp
    @if(!empty($classification))
        <h2>Итоговая классификация</h2>
        <table>
            <thead>
                <tr>
                    <th class="tc">Место</th>
                    <th>Команда</th>
                </tr>
            </thead>
            <tbody>
                @foreach($classification as $c)
                    <tr>
                        <td class="tc bold" style="{{ $c['place'] <= 3 ? 'color:#E7612F' : '' }}">
                            {{ $c['place'] === 1 ? '🥇' : ($c['place'] === 2 ? '🥈' : ($c['place'] === 3 ? '🥉' : $c['place'])) }}
                        </td>
                        <td class="{{ $c['place'] <= 3 ? 'bold' : '' }}">{{ $c['team_name'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach($stages as $stage)
        @if($stage->groups->isNotEmpty())
            <h2>{{ $stage->name }}</h2>
            @foreach($stage->groups as $group)
                <h3>{{ $group->name }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="tc">#</th>
                            <th>Команда</th>
                            <th class="tc">И</th>
                            <th class="tc">В</th>
                            <th class="tc">П</th>
                            <th class="tc">Сеты</th>
                            <th class="tc">Разница</th>
                            <th class="tc">Очки</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group->standings->sortBy('rank') as $s)
                            <tr>
                                <td class="tc bold">{{ $s->rank }}</td>
                                <td>{{ $s->team->name ?? '—' }}</td>
                                <td class="tc">{{ $s->played }}</td>
                                <td class="tc win">{{ $s->wins }}</td>
                                <td class="tc loss">{{ $s->losses }}</td>
                                <td class="tc">{{ $s->sets_won }}:{{ $s->sets_lost }}</td>
                                <td class="tc">{{ $s->pointDiff() > 0 ? '+' : '' }}{{ $s->pointDiff() }}</td>
                                <td class="tc bold">{{ $s->rating_points }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif

        @if($stage->matches->isNotEmpty())
            <h2>{{ $stage->name }} — Матчи</h2>
            <table>
                <thead>
                    <tr>
                        <th class="tc">#</th>
                        <th class="tc">Тур</th>
                        <th>Дома</th>
                        <th>Гости</th>
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
                            <td class="tc bold">{{ $m->scoreFormatted() ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach


    {{-- Bracket сетка (для single/double elim) --}}
    @foreach($stages as $stage)
        @if(in_array($stage->type, ['single_elim', 'double_elim']) && $stage->matches->isNotEmpty())
            <h2>{{ $stage->name }} — Сетка</h2>
            @php
                $totalRounds = $stage->matches->max('round') ?? 0;
            @endphp
            @for($r = 1; $r <= $totalRounds; $r++)
                @php
                    $roundLabel = $r === $totalRounds ? 'Финал' : ($r === $totalRounds - 1 ? 'Полуфинал' : 'Раунд ' . $r);
                    $roundMatches = $stage->matches->where('round', $r)->sortBy('match_number');
                @endphp
                <h3>{{ $roundLabel }}</h3>
                <table>
                    <thead>
                        <tr>
                            <th class="tc">#</th>
                            <th>Команда 1</th>
                            <th class="tc">Счёт</th>
                            <th>Команда 2</th>
                            <th class="tc">Победитель</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($roundMatches as $m)
                            <tr>
                                <td class="tc">{{ $m->match_number }}</td>
                                <td class="{{ $m->winner_team_id === $m->team_home_id ? 'bold' : '' }}">
                                    {{ $m->teamHome->name ?? 'TBD' }}
                                </td>
                                <td class="tc bold">{{ $m->scoreFormatted() ?? '—' }}</td>
                                <td class="{{ $m->winner_team_id === $m->team_away_id ? 'bold' : '' }}">
                                    {{ $m->teamAway->name ?? 'TBD' }}
                                </td>
                                <td class="tc bold">{{ $m->winner->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endfor
        @endif
    @endforeach

    @if($topPlayers->isNotEmpty())
        <div class="page-break"></div>
        <h2>Рейтинг игроков</h2>
        <table>
            <thead>
                <tr>
                    <th class="tc">#</th>
                    <th>Игрок</th>
                    <th>Команда</th>
                    <th class="tc">Матчи</th>
                    <th class="tc">Победы</th>
                    <th class="tc">WinRate</th>
                    <th class="tc">Сеты</th>
                    <th class="tc">Разница</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topPlayers as $i => $ps)
                    <tr>
                        <td class="tc bold">{{ $i + 1 }}</td>
                        <td>{{ $ps->user->displayName() }}</td>
                        <td>{{ $ps->team->name ?? '—' }}</td>
                        <td class="tc">{{ $ps->matches_played }}</td>
                        <td class="tc win">{{ $ps->matches_won }}</td>
                        <td class="tc wr">{{ $ps->match_win_rate }}%</td>
                        <td class="tc">{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
                        <td class="tc">{{ $ps->point_diff > 0 ? '+' : '' }}{{ $ps->point_diff }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">VolleyPlay.Club · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
