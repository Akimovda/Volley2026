<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $event->title }} — TV Mode</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            background: #0f1117;
            color: #e5e7eb;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            overflow: hidden;
            height: 100vh;
        }

        .tv-header {
            background: linear-gradient(135deg, #1a1d2e, #111827);
            padding: 20px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 3px solid #E7612F;
        }
        .tv-title { font-size: 28px; font-weight: 800; }
        .tv-title span { color: #E7612F; }
        .tv-live { background: #dc2626; color: #fff; padding: 6px 16px; border-radius: 8px; font-weight: 700; font-size: 14px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity:1; } 50% { opacity:.6; } }
        .tv-qr { text-align: center; }
        .tv-qr img { width: 80px; height: 80px; border-radius: 8px; }
        .tv-qr div { font-size: 11px; opacity: .5; margin-top: 4px; }

        .tv-body {
            display: flex;
            gap: 24px;
            padding: 24px 40px;
            height: calc(100vh - 90px);
            overflow: hidden;
        }

        .tv-panel {
            flex: 1;
            background: #1a1d2e;
            border-radius: 16px;
            padding: 20px;
            overflow-y: auto;
        }
        .tv-panel h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #E7612F;
        }

        .tv-table { width: 100%; border-collapse: collapse; font-size: 16px; }
        .tv-table th { padding: 8px 6px; text-align: left; border-bottom: 2px solid rgba(255,255,255,.1); font-weight: 600; opacity: .6; font-size: 13px; }
        .tv-table td { padding: 8px 6px; border-bottom: 1px solid rgba(255,255,255,.05); }
        .tv-table .tc { text-align: center; }
        .tv-table .win { color: #10b981; font-weight: 700; }
        .tv-table .loss { color: #dc2626; }
        .tv-table .rank { font-weight: 800; font-size: 18px; }
        .tv-table .pts { font-weight: 800; font-size: 18px; color: #E7612F; }

        .tv-match {
            display: flex;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,.05);
            font-size: 16px;
        }
        .tv-match .team { flex: 1; }
        .tv-match .team.right { text-align: right; }
        .tv-match .score { min-width: 100px; text-align: center; font-weight: 800; font-size: 18px; }
        .tv-match .winner { color: #10b981; font-weight: 700; }
        .tv-match .status-badge { font-size: 12px; padding: 3px 8px; border-radius: 6px; }
        .tv-match .live-badge { background: #dc2626; color: #fff; }
        .tv-match .done-badge { background: rgba(16,185,129,.2); color: #10b981; }
        .tv-match .wait-badge { opacity: .3; }

        .updated-flash { animation: flash .5s ease; }
        @keyframes flash { 0%{ background:rgba(231,97,47,.2); } 100%{ background:transparent; } }
    </style>
</head>
<body>

    <div class="tv-header">
        <div>
            <div class="tv-title"><span>▶</span> {{ $event->title }}</div>
            @if(isset($occurrences) && $occurrences->count() > 1)
            <div style="display:flex;gap:6px;margin-top:8px">
                @foreach($occurrences as $occ)
                    @php
                        $isSelected = isset($selectedOccurrence) && $selectedOccurrence && $selectedOccurrence->id === $occ->id;
                        $occDate = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
                    @endphp
                    <a href="{{ route('tournament.tv', $event) }}?occurrence_id={{ $occ->id }}"
                       style="padding:4px 12px;border-radius:6px;font-size:13px;font-weight:600;text-decoration:none;{{ $isSelected ? 'background:#E7612F;color:#fff' : 'background:rgba(255,255,255,.1);color:#9ca3af' }}">
                        {{ __('tournaments.tv_round_n_short', ['n' => $loop->iteration, 'date' => $occDate->format('d.m')]) }}
                    </a>
                @endforeach
            </div>
            @endif
        </div>
        <div style="display:flex;align-items:center;gap:20px">
            @if($stages->where('status','in_progress')->isNotEmpty())
                <div class="tv-live">● LIVE</div>
            @endif
            <div class="tv-qr">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=160x160&data={{ urlencode(route('tournament.public.show', $event)) }}" alt="QR">
                <div>{{ __('tournaments.tv_open_phone') }}</div>
            </div>
        </div>
    </div>

    <div class="tv-body" id="tvBody">
        @php
            $inProgress = $stages->firstWhere('status', 'in_progress');
            $allCompleted = $stages->isNotEmpty() && $stages->every(fn($s) => $s->status === 'completed');
            $lastCompleted = $stages->where('status', 'completed')->last();
            $activeStage = $inProgress ?? ($allCompleted ? null : $lastCompleted);
        @endphp

        @if($allCompleted)
            {{-- Итоги турнира --}}
            @php
                $classification = app(\App\Services\TournamentStatsService::class)
                    ->calculateFinalClassification($event, $selectedOccurrence?->id ?? null);
                $hasDivisions = isset($classification[0]['division']);
                $divisions = $hasDivisions
                    ? collect($classification)->groupBy('division')
                    : collect(['all' => collect($classification)]);
            @endphp

            @if($hasDivisions)
                @foreach($divisions as $divName => $divTeams)
                <div class="tv-panel">
                    <h3>🏆 {{ $divName }}</h3>
                    @foreach($divTeams as $i => $c)
                        @php
                            $place = $i + 1;
                            $team = \App\Models\EventTeam::with('members.user')->find($c['team_id']);
                            $members = $team ? $team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') : '';
                        @endphp
                        <div class="tv-match" style="border-bottom:1px solid rgba(255,255,255,.05);padding:12px 0">
                            <div style="width:40px;font-size:22px;text-align:center">
                                {{ $place === 1 ? '🥇' : ($place === 2 ? '🥈' : ($place === 3 ? '🥉' : $place . '.')) }}
                            </div>
                            <div style="flex:1">
                                <div style="font-weight:700;font-size:18px;{{ $place <= 3 ? 'color:#E7612F' : '' }}">{{ $c['team_name'] }}</div>
                                <div style="font-size:13px;opacity:.5">{{ $members }}</div>
                            </div>
                            <div style="text-align:right;font-size:14px">
                                <div class="b-700" style="color:#E7612F">{{ $c['rating_points'] ?? 0 }} {{ __('tournaments.tv_pts_short') }}</div>
                                <div style="opacity:.5;font-size:12px">{{ $c['wins'] ?? 0 }}{{ __('tournaments.tv_w_short') }} {{ $c['losses'] ?? 0 }}{{ __('tournaments.tv_l_short') }} &middot; {{ $c['points_scored'] ?? 0 }}:{{ $c['points_conceded'] ?? 0 }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @endforeach
            @else
                <div class="tv-panel" style="flex:2">
                    <h3>🏆 {{ __('tournaments.tv_results') }}</h3>
                    @foreach($classification as $c)
                        @php
                            $team = \App\Models\EventTeam::with('members.user')->find($c['team_id']);
                            $members = $team ? $team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') : '';
                        @endphp
                        <div class="tv-match" style="border-bottom:1px solid rgba(255,255,255,.05);padding:12px 0">
                            <div style="width:40px;font-size:22px;text-align:center">
                                {{ $c['place'] === 1 ? '🥇' : ($c['place'] === 2 ? '🥈' : ($c['place'] === 3 ? '🥉' : $c['place'] . '.')) }}
                            </div>
                            <div style="flex:1">
                                <div style="font-weight:700;font-size:18px;{{ $c['place'] <= 3 ? 'color:#E7612F' : '' }}">{{ $c['team_name'] }}</div>
                                <div style="font-size:13px;opacity:.5">{{ $members }}</div>
                            </div>
                            <div style="text-align:right;font-size:14px">
                                <div class="b-700" style="color:#E7612F">{{ $c['rating_points'] ?? 0 }} {{ __('tournaments.tv_pts_short') }}</div>
                                <div style="opacity:.5;font-size:12px">{{ $c['wins'] ?? 0 }}{{ __('tournaments.tv_w_short') }} {{ $c['losses'] ?? 0 }}{{ __('tournaments.tv_l_short') }} &middot; {{ $c['points_scored'] ?? 0 }}:{{ $c['points_conceded'] ?? 0 }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- MVP --}}
            @if($event->tournament_mvp_user_id)
                @php $mvp = \App\Models\User::find($event->tournament_mvp_user_id); @endphp
                <div class="tv-panel" style="flex:0.5;text-align:center;display:flex;flex-direction:column;justify-content:center">
                    <div style="font-size:14px;opacity:.5;margin-bottom:8px">{{ __('tournaments.tv_mvp') }}</div>
                    <div style="font-size:48px;margin-bottom:8px">⭐</div>
                    <div style="font-size:22px;font-weight:800;color:#E7612F">{{ $mvp?->last_name }} {{ $mvp?->first_name }}</div>
                </div>
            @endif

        @elseif($activeStage)
            {{-- Текущий этап --}}
            @if($activeStage->groups->isNotEmpty())
                @foreach($activeStage->groups as $group)
                    <div class="tv-panel" data-group-id="{{ $group->id }}">
                        <h3>{{ $activeStage->name }}: {{ $group->name }}</h3>
                        @if($group->standings->isNotEmpty())
                            <table class="tv-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>{{ __('tournaments.standings_col_team') }}</th>
                                        <th class="tc">{{ __('tournaments.standings_col_played') }}</th>
                                        <th class="tc">{{ __('tournaments.standings_col_w') }}</th>
                                        <th class="tc">{{ __('tournaments.standings_col_l') }}</th>
                                        <th class="tc">{{ __('tournaments.tv_pts_col') }}</th>
                                        <th class="tc">{{ __('tournaments.tv_diff_col') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group->standings->sortBy('rank') as $s)
                                        <tr>
                                            <td class="rank">{{ $s->rank }}</td>
                                            <td>
                                                <div>{{ $s->team->name ?? '—' }}</div>
                                                @if($s->team && $s->team->members->count())
                                                <div style="font-size:12px;opacity:.5">{{ $s->team->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                                @endif
                                            </td>
                                            <td class="tc">{{ $s->played }}</td>
                                            <td class="tc win">{{ $s->wins }}</td>
                                            <td class="tc loss">{{ $s->losses }}</td>
                                            <td class="tc pts">{{ $s->rating_points }}</td>
                                            <td class="tc">{{ $s->points_scored - $s->points_conceded > 0 ? '+' : '' }}{{ $s->points_scored - $s->points_conceded }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        <div style="margin-top:16px">
                            @foreach($activeStage->matches->where('group_id', $group->id)->sortBy(['round','match_number']) as $m)
                                <div class="tv-match" data-match-id="{{ $m->id }}">
                                    <div class="team right {{ $m->winner_team_id === $m->team_home_id ? 'winner' : '' }}">
                                        {{ $m->teamHome->name ?? 'TBD' }}
                                        @if($m->teamHome && $m->teamHome->members->count())
                                        <div style="font-size:11px;opacity:.4">{{ $m->teamHome->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                        @endif
                                    </div>
                                    <div class="score">
                                        @if($m->isCompleted())
                                            {{ $m->setsScore() }}
                                            <div style="font-size:11px;opacity:.5">{{ $m->detailedScore() }}</div>
                                        @elseif($m->status === 'live')
                                            <span class="status-badge live-badge">LIVE</span>
                                        @else
                                            <span class="status-badge wait-badge">—</span>
                                        @endif
                                    </div>
                                    <div class="team {{ $m->winner_team_id === $m->team_away_id ? 'winner' : '' }}">
                                        {{ $m->teamAway->name ?? 'TBD' }}
                                        @if($m->teamAway && $m->teamAway->members->count())
                                        <div style="font-size:11px;opacity:.4">{{ $m->teamAway->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                <div class="tv-panel" style="flex:2">
                    <h3>{{ $activeStage->name }}</h3>
                    @foreach($activeStage->matches->sortBy(['round','match_number']) as $m)
                        <div class="tv-match">
                            <div class="team right {{ $m->winner_team_id === $m->team_home_id ? 'winner' : '' }}">
                                {{ $m->teamHome->name ?? 'TBD' }}
                                @if($m->teamHome && $m->teamHome->members->count())
                                <div style="font-size:11px;opacity:.4">{{ $m->teamHome->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                @endif
                            </div>
                            <div class="score">
                                @if($m->isCompleted())
                                    {{ $m->setsScore() }}
                                    <div style="font-size:11px;opacity:.5">{{ $m->detailedScore() }}</div>
                                @elseif($m->status === 'live')
                                    <span class="status-badge live-badge">LIVE</span>
                                @else
                                    <span class="status-badge wait-badge">R{{ $m->round }}</span>
                                @endif
                            </div>
                            <div class="team {{ $m->winner_team_id === $m->team_away_id ? 'winner' : '' }}">
                                {{ $m->teamAway->name ?? 'TBD' }}
                                @if($m->teamAway && $m->teamAway->members->count())
                                <div style="font-size:11px;opacity:.4">{{ $m->teamAway->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @else
            <div class="tv-panel" style="flex:1;text-align:center;display:flex;align-items:center;justify-content:center">
                <div>
                    <div style="font-size:48px;margin-bottom:16px">🏐</div>
                    <div style="font-size:22px;font-weight:700">{{ __('tournaments.tv_starting_soon') }}</div>
                </div>
            </div>
        @endif
    </div>

    <script>
    (function() {
        var liveUrl = @json($liveUrl);
        var interval = 12000;

        function poll() {
            fetch(liveUrl)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    // Для MVP: перезагрузка страницы при изменениях
                    // В будущем — точечное обновление DOM с анимациями
                    var hasLive = data.stages.some(function(s) { return s.status === 'in_progress'; });
                    if (hasLive) {
                        location.reload();
                    }
                })
                .catch(function() {});
        }

        setInterval(poll, interval);
    })();
    </script>

</body>
</html>
