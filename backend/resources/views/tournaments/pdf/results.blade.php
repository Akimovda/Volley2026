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
        .mvp-block { font-size: 15px; font-weight: bold; color: #E7612F; margin-bottom: 10px; }
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
    @php
        // dompdf не рендерит emoji (нет глифов в DejaVu Sans) и не рендерит SVG —
        // только растровые PNG по локальному пути внутри chroot (base_path()).
        $medalIcon = [
            1 => public_path('assets/pdf-icons/medal-gold.png'),
            2 => public_path('assets/pdf-icons/medal-silver.png'),
            3 => public_path('assets/pdf-icons/medal-bronze.png'),
        ];
        $placeColor = [
            1 => '#B8860B',
            2 => '#8C8C8C',
            3 => '#B5651D',
        ];
        $mvpIcon = public_path('assets/pdf-icons/mvp.png');
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
                        <td class="tc bold" style="{{ $c['place'] <= 3 ? 'color:' . $placeColor[$c['place']] : '' }}">
                            @if($c['place'] <= 3)
                                <img src="{{ $medalIcon[$c['place']] }}" width="16" height="16" style="vertical-align:middle">
                            @endif
                            {{ $c['place'] }}
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
                            <td class="tc bold">{{ $m->setsScore() ?? '—' }}</td>
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
                                <td class="tc bold">{{ $m->setsScore() ?? '—' }}</td>
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

    @php
        // MVP назначается организатором вручную на странице управления турниром
        // (setup.blade.php → tournament.mvp), не считается автоматически.
        $mvpUser = $event->tournament_mvp_user_id ? \App\Models\User::find($event->tournament_mvp_user_id) : null;
    @endphp
    @if(!empty($ratingData['rows']))
        <div class="page-break"></div>
        <h2>Рейтинг игроков</h2>

        @if($mvpUser)
            <div class="mvp-block">
                <img src="{{ $mvpIcon }}" width="20" height="20" style="vertical-align:middle">
                MVP турнира: {{ $mvpUser->displayName() }}
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th class="tc" rowspan="2">#</th>
                    <th rowspan="2">Игрок</th>
                    <th class="tc" colspan="{{ $ratingData['hasPoints'] ? 3 : 2 }}">Турнир</th>
                    <th class="tc" colspan="3">Общий рейтинг</th>
                </tr>
                <tr>
                    <th class="tc">И</th>
                    <th class="tc">П</th>
                    @if($ratingData['hasPoints'])
                        <th class="tc">Очки</th>
                    @endif
                    <th class="tc">Рейтинг</th>
                    <th class="tc">И</th>
                    <th class="tc">Win%</th>
                </tr>
            </thead>
            <tbody>
                @foreach($ratingData['rows'] as $i => $r)
                    @php $isMvp = $event->tournament_mvp_user_id && (int) $event->tournament_mvp_user_id === (int) $r['user_id']; @endphp
                    <tr @if($isMvp) style="background:#fff8d6" @endif>
                        <td class="tc bold">{{ $i + 1 }}</td>
                        <td>
                            {{ $r['user']?->displayName() ?? '#' . $r['user_id'] }}
                            @if($isMvp)
                                <img src="{{ $mvpIcon }}" width="14" height="14" style="vertical-align:middle">
                            @endif
                        </td>
                        <td class="tc">{{ $r['t_games'] }}</td>
                        <td class="tc win">{{ $r['t_wins'] }}</td>
                        @if($ratingData['hasPoints'])
                            <td class="tc wr">{{ $r['t_points'] }}</td>
                        @endif
                        <td class="tc wr">{{ number_format($r['cr'], 1) }}</td>
                        <td class="tc">{{ $r['o_games'] }}</td>
                        <td class="tc">{{ $r['o_winrate'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if($ratingData['hiddenCount'] > 0)
            <div class="meta">Ещё {{ $ratingData['hiddenCount'] }} игроков без сыгранных матчей</div>
        @endif
    @endif

    <div class="footer">
        VolleyPlay.Club · {{ now()->format('d.m.Y H:i') }}<br>
        Иконки: Icons8 (icons8.com)
    </div>
</body>
</html>
