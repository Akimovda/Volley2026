{{-- resources/views/games/results.blade.php — публичные результаты и статистика «Игры со статистикой» --}}
<x-voll-layout body_class="friendly-game-results-page">

    <x-slot name="title">{{ __('games.results_title') }} — {{ $event->title }}</x-slot>
    <x-slot name="h1">📊 {{ __('games.results_title') }}</x-slot>
    <x-slot name="h2">{{ $event->title }}</x-slot>
    <x-slot name="t_description">{{ __('games.results_h2') }}</x-slot>

    <x-slot name="breadcrumbs">
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <a href="{{ route('events.show', $event) }}?occurrence={{ $occurrence->id }}" itemprop="item"><span itemprop="name">{{ __('games.breadcrumb_event') }}</span></a>
            <meta itemprop="position" content="2">
        </li>
        <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
            <span itemprop="name">{{ __('games.results_title') }}</span>
            <meta itemprop="position" content="3">
        </li>
    </x-slot>

    <x-slot name="style">
        @include('games._styles')
        <style>
            .gm-res-team { display:flex; align-items:center; gap:.8rem; flex-wrap:wrap; }
            .gm-res-score { font-size:2.6rem; font-weight:700; text-align:center; }
            .gm-res-win { font-weight:700; }
        </style>
    </x-slot>

    <div class="container">

        @if($occurrences->count() > 1)
        <div class="ramka">
            <form method="GET" action="{{ route('game.results', $event) }}" class="form">
                <select name="occurrence" onchange="this.form.submit()">
                    @foreach($occurrences as $o)
                    <option value="{{ $o->id }}" @selected($o->id === $occurrence->id)>{{ $o->starts_at?->copy()->timezone($o->timezone ?? 'UTC')->format('d.m.Y H:i') }}</option>
                    @endforeach
                </select>
            </form>
        </div>
        @endif

        @if($event->stats_rated)
        <div class="ramka"><div class="f-14" style="opacity:.75">🏅 {{ __('games.rated_note') }}</div></div>
        @endif

        {{-- Вечер / серия --}}
        <div class="ramka">
            <div class="gm-actions">
                <a class="btn btn-small {{ $scope === 'evening' ? '' : 'btn-outline' }}" href="{{ route('game.results', $event) }}?occurrence={{ $occurrence->id }}">{{ __('games.scope_evening') }}</a>
                <a class="btn btn-small {{ $scope === 'series' ? '' : 'btn-outline' }}" href="{{ route('game.results', $event) }}?scope=series&period={{ $period }}&occurrence={{ $occurrence->id }}">{{ __('games.scope_series') }}</a>
            </div>
            @if($scope === 'series')
            <div class="gm-actions mt-1">
                @foreach(['all' => __('games.period_all'), '30' => __('games.period_30'), '90' => __('games.period_90'), '365' => __('games.period_365')] as $k => $label)
                <a class="btn btn-small {{ $period === (string) $k ? '' : 'btn-outline' }}" href="{{ route('game.results', $event) }}?scope=series&period={{ $k }}&occurrence={{ $occurrence->id }}">{{ $label }}</a>
                @endforeach
            </div>
            @endif
        </div>

        @if($scope === 'series')
        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.series_title') }}</h2>
            @if(!empty($series['rows']))
            <div class="f-13 mb-1" style="opacity:.6">{{ __('games.series_evenings', ['n' => $series['evenings']]) }}</div>
            @endif
            @include('games._board', ['board' => $series])
        </div>
        @else

        <div id="gm-live" data-live="{{ $isLive ? 1 : 0 }}">
        @if($visible->isEmpty())
        <div class="ramka"><div class="f-15" style="opacity:.7">{{ __('games.results_empty') }}</div></div>
        @else

        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.board_title') }}</h2>
            @include('games._board', ['board' => $board])
        </div>

        <div class="ramka">
            <h2 class="-mt-05">{{ __('games.matches_title') }}</h2>
            @foreach($visible as $m)
            @php
                $isDone   = $m->status === 'completed';
                $homeWon  = $isDone && (int) $m->winner_team_id === (int) $m->team_home_id;
                $awayWon  = $isDone && (int) $m->winner_team_id === (int) $m->team_away_id;
                $setsText = $isDone ? collect($m->score_home ?? [])->map(fn($h, $i) => $h . ':' . ($m->score_away[$i] ?? 0))->implode('  ') : '';
                $roster   = fn($team) => $team ? $team->members->map(fn($x) => trim(($x->user->last_name ?? '') . ' ' . mb_substr($x->user->first_name ?? '', 0, 1)) . ($x->user?->first_name ? '.' : ''))->implode(', ') : '';
            @endphp
            <div class="card mb-2">
                <div class="d-flex between fvc mb-1" style="flex-wrap:wrap;gap:.8rem">
                    <div class="b-700 f-16">{{ __('games.match_n', ['n' => $m->match_number]) }}</div>
                    @if(!$isDone)<span class="badge badge-sm status-live">{{ __('games.status_live') }}</span>@endif
                </div>
                <div class="row">
                    <div class="col-md-5 mb-1 {{ $homeWon ? 'gm-res-win' : '' }}">
                        @if($m->teamHome)<span class="gm-team-tag gm-c-{{ $m->teamHome->meta['color'] ?? 'white' }}">{{ $m->teamHome->name }}</span>@endif
                        <div class="f-14 mt-05" style="opacity:.85">{{ $roster($m->teamHome) }}</div>
                    </div>
                    <div class="col-md-2 mb-1" style="text-align:center">
                        @if($isDone)
                        <div class="gm-res-score">{{ $m->sets_home }} : {{ $m->sets_away }}</div>
                        <div class="f-12" style="opacity:.6">{{ $setsText }}</div>
                        @else
                        <div class="gm-res-score" style="opacity:.3">—</div>
                        @endif
                    </div>
                    <div class="col-md-5 mb-1 {{ $awayWon ? 'gm-res-win' : '' }}" style="text-align:right">
                        @if($m->teamAway)<span class="gm-team-tag gm-c-{{ $m->teamAway->meta['color'] ?? 'white' }}">{{ $m->teamAway->name }}</span>@endif
                        <div class="f-14 mt-05" style="opacity:.85">{{ $roster($m->teamAway) }}</div>
                    </div>
                </div>

                @if(!empty($matchStatsByMatchId[$m->id]['has_stats']))
                <div style="text-align:center;margin:4px 0 8px">
                    <button type="button" class="btn btn-small btn-outline" onclick="toggleMatchStats({{ $m->id }})">📊 {{ __('games.stats_toggle') }}</button>
                </div>
                <div id="match-stats-r-{{ $m->id }}" class="card mb-2" style="display:none">
                    @include('tournaments._partials.match_stats_pretty', ['statsData' => $matchStatsByMatchId[$m->id], 'match' => $m, 'stage' => $stage, 'event' => $event])
                </div>
                @endif

                @if(!empty($matchProgressByMatchId[$m->id]['has_progress']))
                <div style="text-align:center;margin:4px 0 8px">
                    <button type="button" class="btn btn-small btn-outline" onclick="toggleMatchProgress({{ $m->id }})">▶ {{ __('games.progress_toggle') }}</button>
                </div>
                <div id="match-progress-r-{{ $m->id }}" class="card mb-2" style="display:none">
                    @include('tournaments._partials.match_progress_fragment', ['matchProgress' => $matchProgressByMatchId[$m->id], 'match' => $m, 'event' => $event])
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
        </div>

        @endif

    </div>

    <x-slot name="script">
    <script>
        // Живое обновление: пока есть незавершённый матч — раз в 12 с подтягиваем свежий HTML блока #gm-live
        (function () {
            var box = document.getElementById('gm-live');
            if (!box || box.getAttribute('data-live') !== '1' || !window.jQuery) return;
            var busy = false;
            setInterval(function () {
                if (busy || document.hidden) return;
                busy = true;
                jQuery.ajax({ url: window.location.href, dataType: 'html', cache: false })
                    .done(function (html) {
                        var doc = new DOMParser().parseFromString(html, 'text/html');
                        var fresh = doc.getElementById('gm-live');
                        var cur = document.getElementById('gm-live');
                        if (!fresh || !cur) return;
                        var open = [];
                        cur.querySelectorAll('[id^="match-stats-r-"],[id^="match-progress-r-"]').forEach(function (el) {
                            if (el.style.display !== 'none') open.push(el.id);
                        });
                        if (cur.innerHTML.trim() === fresh.innerHTML.trim()) return;
                        cur.innerHTML = fresh.innerHTML;
                        cur.setAttribute('data-live', fresh.getAttribute('data-live'));
                        open.forEach(function (id) { var el = document.getElementById(id); if (el) el.style.display = ''; });
                    })
                    .always(function () { busy = false; });
            }, 12000);
        })();
        function toggleMatchStats(id) {
            var el = document.getElementById('match-stats-r-' + id);
            if (el) el.style.display = (el.style.display === 'none') ? '' : 'none';
        }
        function toggleMatchProgress(id) {
            var el = document.getElementById('match-progress-r-' + id);
            if (el) el.style.display = (el.style.display === 'none') ? '' : 'none';
        }
        function rpShowSet(matchId, setNumber) {
            var key = matchId + '-' + setNumber;
            document.querySelectorAll('[data-rp-set^="' + matchId + '-"]').forEach(function (el) {
                el.classList.toggle('rp-set--hidden', el.getAttribute('data-rp-set') !== key);
            });
            document.querySelectorAll('[data-rp-tab^="' + matchId + '-"]').forEach(function (el) {
                el.classList.toggle('rp-tab--active', el.getAttribute('data-rp-tab') === key);
            });
        }
    </script>
    </x-slot>

</x-voll-layout>
