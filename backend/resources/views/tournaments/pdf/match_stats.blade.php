<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 20px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 16px; margin: 16px 0 8px; color: #2967BA; }
        .meta { font-size: 11px; color: #666; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th { background: #f3f4f6; padding: 6px 8px; text-align: left; font-size: 11px; border-bottom: 2px solid #d1d5db; }
        td { padding: 5px 8px; border-bottom: 1px solid #e5e7eb; }
        .tc { text-align: center; }
        .bold { font-weight: bold; }
        .wr { color: #E7612F; font-weight: bold; }
        .score-block { text-align: center; margin-bottom: 16px; }
        .score-teams { font-size: 16px; font-weight: bold; }
        .score-teams .winner { color: #059669; }
        .score-main { font-size: 26px; font-weight: bold; margin: 6px 0; }
        .score-sets { font-size: 12px; color: #666; }
        .hero-block { margin-bottom: 10px; }
        .hero-name { font-weight: bold; }
        .hero-points { color: #E7612F; font-weight: bold; }
        .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
    </style>
</head>
<body>
    @php
        $getVal = fn($totals, $f) => is_array($totals) ? ($totals[$f] ?? 0) : ($totals->$f ?? 0);

        $enrichSide = function (array $rows) use ($getVal) {
            $out = [];
            foreach ($rows as $row) {
                $out[] = [
                    'user_name'    => $row['user_name'],
                    'attack'       => (int) $getVal($row['totals'], 'kills'),
                    'block'        => (int) $getVal($row['totals'], 'blocks'),
                    'serve'        => (int) $getVal($row['totals'], 'aces'),
                    'serve_errors' => (int) $getVal($row['totals'], 'serve_errors'),
                    'errors'       => (int) $getVal($row['totals'], 'attack_errors')
                                     + (int) $getVal($row['totals'], 'block_errors')
                                     + (int) $getVal($row['totals'], 'reception_errors'),
                    'points'       => (int) $getVal($row['totals'], 'points_scored'),
                ];
            }
            usort($out, fn($a, $b) => $b['points'] <=> $a['points'] ?: $b['attack'] <=> $a['attack']);
            return $out;
        };

        $homePlayers = $enrichSide($statsData['home'] ?? []);
        $awayPlayers = $enrichSide($statsData['away'] ?? []);
        $homeHero = $homePlayers[0] ?? null;
        $awayHero = $awayPlayers[0] ?? null;

        $sumField = fn(array $players, string $f) => array_sum(array_column($players, $f));
        $bars = [
            ['label' => 'Атака',      'home' => $sumField($homePlayers, 'attack'),       'away' => $sumField($awayPlayers, 'attack')],
            ['label' => 'Блок',       'home' => $sumField($homePlayers, 'block'),        'away' => $sumField($awayPlayers, 'block')],
            ['label' => 'Подача',     'home' => $sumField($homePlayers, 'serve'),        'away' => $sumField($awayPlayers, 'serve')],
            ['label' => 'Ош. подачи', 'home' => $sumField($homePlayers, 'serve_errors'), 'away' => $sumField($awayPlayers, 'serve_errors')],
            ['label' => 'Ошибки',     'home' => $sumField($homePlayers, 'errors'),       'away' => $sumField($awayPlayers, 'errors')],
        ];

        $homeTeam = $match->teamHome;
        $awayTeam = $match->teamAway;
        $homeWonMatch = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_home_id;
        $awayWonMatch = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_away_id;

        $setsRendered = [];
        if ($match->score_home && $match->score_away) {
            foreach ($match->score_home as $i => $h) {
                $a = $match->score_away[$i] ?? 0;
                $setsRendered[] = ['home' => $h, 'away' => $a];
            }
        }
    @endphp

    <h1>{{ $event->title }} — Статистика матча</h1>
    <div class="meta">
        {{ $stage->name }} · {{ __('tournaments.score_match_n', ['n' => $match->match_number]) }}
        @if($match->scheduled_at) · {{ $match->scheduled_at->format('d.m.Y H:i') }} @endif
    </div>

    <div class="score-block">
        <div class="score-teams">
            <span class="{{ $homeWonMatch ? 'winner' : '' }}">{{ $homeTeam->name ?? '?' }}</span>
            —
            <span class="{{ $awayWonMatch ? 'winner' : '' }}">{{ $awayTeam->name ?? '?' }}</span>
        </div>
        <div class="score-main">{{ $match->setsScore() }}</div>
        @if(!empty($setsRendered))
        <div class="score-sets">
            @foreach($setsRendered as $i => $s)
                @if($i > 0) · @endif{{ $s['home'] }}:{{ $s['away'] }}
            @endforeach
        </div>
        @endif
    </div>

    @if(!$homePlayers && !$awayPlayers)
        <div class="meta" style="text-align:center">Нет данных статистики по игрокам для этого матча.</div>
    @else

        <h2>Герои матча</h2>
        <table>
            <thead>
                <tr>
                    <th>Команда</th>
                    <th>Игрок</th>
                    <th class="tc">Очки</th>
                    <th class="tc">Атака</th>
                    <th class="tc">Блок</th>
                    <th class="tc">Подача</th>
                </tr>
            </thead>
            <tbody>
                @foreach(['home' => ['team' => $homeTeam, 'hero' => $homeHero], 'away' => ['team' => $awayTeam, 'hero' => $awayHero]] as $d)
                    <tr>
                        <td class="bold">{{ $d['team']->name ?? '?' }}</td>
                        @if($d['hero'])
                            <td class="bold">{{ $d['hero']['user_name'] }}</td>
                            <td class="tc wr">{{ $d['hero']['points'] }}</td>
                            <td class="tc">{{ $d['hero']['attack'] }}</td>
                            <td class="tc">{{ $d['hero']['block'] }}</td>
                            <td class="tc">{{ $d['hero']['serve'] }}</td>
                        @else
                            <td colspan="5" class="tc">—</td>
                        @endif
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h2>Сравнение команд</h2>
        <table>
            <thead>
                <tr>
                    <th class="tc">{{ $homeTeam->name ?? '?' }}</th>
                    <th class="tc">Показатель</th>
                    <th class="tc">{{ $awayTeam->name ?? '?' }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($bars as $bar)
                    <tr>
                        <td class="tc bold">{{ $bar['home'] }}</td>
                        <td class="tc">{{ $bar['label'] }}</td>
                        <td class="tc bold">{{ $bar['away'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @foreach(['home' => ['team' => $homeTeam, 'players' => $homePlayers], 'away' => ['team' => $awayTeam, 'players' => $awayPlayers]] as $d)
            <h2>{{ $d['team']->name ?? '?' }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Игрок</th>
                        <th class="tc">Очки</th>
                        <th class="tc">Атака</th>
                        <th class="tc">Блок</th>
                        <th class="tc">Подача</th>
                        <th class="tc">Ош. подачи</th>
                        <th class="tc">Ошибки</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($d['players'] as $p)
                        <tr>
                            <td>{{ $p['user_name'] }}</td>
                            <td class="tc wr">{{ $p['points'] }}</td>
                            <td class="tc">{{ $p['attack'] }}</td>
                            <td class="tc">{{ $p['block'] }}</td>
                            <td class="tc">{{ $p['serve'] }}</td>
                            <td class="tc">{{ $p['serve_errors'] }}</td>
                            <td class="tc">{{ $p['errors'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="tc">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach

    @endif

    <div class="footer">VolleyPlay.Club · {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>
