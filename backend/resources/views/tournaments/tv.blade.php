<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                        Тур {{ $loop->iteration }} ({{ $occDate->format('d.m') }})
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
                <div>Открыть на телефоне</div>
            </div>
        </div>
    </div>

    <div class="tv-body" id="tvBody">
        @foreach($stages as $stage)
            @if($stage->groups->isNotEmpty())
                {{-- Группы --}}
                @foreach($stage->groups as $group)
                    <div class="tv-panel" data-group-id="{{ $group->id }}">
                        <h3>{{ $group->name }}</h3>
                        @if($group->standings->isNotEmpty())
                            <table class="tv-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Команда</th>
                                        <th class="tc">И</th>
                                        <th class="tc">В</th>
                                        <th class="tc">П</th>
                                        <th class="tc">Сеты</th>
                                        <th class="tc">Оч.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group->standings->sortBy('rank') as $s)
                                        <tr>
                                            <td class="rank">{{ $s->rank }}</td>
                                            <td>{{ $s->team->name ?? '—' }}</td>
                                            <td class="tc">{{ $s->played }}</td>
                                            <td class="tc win">{{ $s->wins }}</td>
                                            <td class="tc loss">{{ $s->losses }}</td>
                                            <td class="tc">{{ $s->sets_won }}:{{ $s->sets_lost }}</td>
                                            <td class="tc pts">{{ $s->rating_points }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif

                        {{-- Матчи группы --}}
                        <div style="margin-top:16px">
                            @foreach($stage->matches->where('group_id', $group->id)->sortBy(['round','match_number']) as $m)
                                <div class="tv-match" data-match-id="{{ $m->id }}">
                                    <div class="team right {{ $m->winner_team_id === $m->team_home_id ? 'winner' : '' }}">
                                        {{ $m->teamHome->name ?? 'TBD' }}
                                    </div>
                                    <div class="score">
                                        @if($m->isCompleted())
                                            {{ $m->sets_home }}:{{ $m->sets_away }}
                                        @elseif($m->status === 'live')
                                            <span class="status-badge live-badge">LIVE</span>
                                        @else
                                            <span class="status-badge wait-badge">—</span>
                                        @endif
                                    </div>
                                    <div class="team {{ $m->winner_team_id === $m->team_away_id ? 'winner' : '' }}">
                                        {{ $m->teamAway->name ?? 'TBD' }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            @else
                {{-- Bracket / матчи без групп --}}
                <div class="tv-panel" style="flex:2">
                    <h3>{{ $stage->name }}</h3>
                    @foreach($stage->matches->sortBy(['round','match_number']) as $m)
                        <div class="tv-match">
                            <div class="team right {{ $m->winner_team_id === $m->team_home_id ? 'winner' : '' }}">
                                {{ $m->teamHome->name ?? 'TBD' }}
                            </div>
                            <div class="score">
                                @if($m->isCompleted())
                                    {{ $m->sets_home }}:{{ $m->sets_away }}
                                @elseif($m->status === 'live')
                                    <span class="status-badge live-badge">LIVE</span>
                                @else
                                    <span class="status-badge wait-badge">R{{ $m->round }}</span>
                                @endif
                            </div>
                            <div class="team {{ $m->winner_team_id === $m->team_away_id ? 'winner' : '' }}">
                                {{ $m->teamAway->name ?? 'TBD' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endforeach
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
