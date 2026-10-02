<x-voll-layout body_class="teams-page">
<x-slot name="title">{{ __('players.teams_title') }}</x-slot>
<x-slot name="h1">{{ __('players.teams_title') }}</x-slot>
<x-slot name="t_description">Статистика постоянных пар и команд по результатам турниров</x-slot>
<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('players.rating') }}" itemprop="item"><span itemprop="name">{{ __('players.rating_title') }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('players.teams_title') }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>
<x-slot name="d_description">
    <div class="d-flex gap-1 mt-2 flex-wrap">
        <a href="{{ route('players.rating') }}" class="btn btn-secondary">← {{ __('players.rating_title') }}</a>
    </div>
</x-slot>

@php
    $isTeamMode = isset($teamRosters);
    $gamesHeads = [];
    $schemeLabel = fn($v) => $v === '5x1_libero' ? '5x1 ' . __('events.libero_word') : $v;
@endphp
<x-slot name="style">
    <style>
        /* Колонка «Связки/Команды»: каждый игрок пары — на отдельной строке. Вёрстка БЕЗ flex (display:table + table-cell):
           в Safari/WebKit ячейка с flex-контейнерами внутри auto-таблицы получает ширину меньше содержимого,
           и фамилия вылезает в соседнюю колонку. Табличная вёрстка даёт min-content = самое длинное слово + аватар. */
        .pair-cell { display:block; }
        .pair-player { display:table; margin:0 0 .6rem; }
        .pair-cell .pair-player:last-child { margin-bottom:0; }
        .pair-ava, .pair-name { display:table-cell; vertical-align:middle; }
        .pair-ava { width:3.2rem; padding-right:.8rem; box-sizing:content-box; }
        .pair-name { overflow-wrap:break-word; }
        /* max-width:none — глобальное img{max-width:100%} в ячейке таблицы с шириной по содержимому сворачивало аватар в 0 */
        .pair-avatar { display:block; width:3.2rem; height:3.2rem; max-width:none; border-radius:50%; object-fit:cover; }
        .pair-sep { display:none; }
        @media (min-width: 768px) {
            /* ширина колонки — по самому длинному имени, без переноса внутри имени */
            .pair-player, .team-roster-link { white-space: nowrap; }
            th.pair-col, td.pair-col { width: 1%; white-space: nowrap; }
            th.pair-col { text-align: left; }
        }
        .roster-row { display:flex; align-items:center; gap:1.2rem; padding:.8rem 0; border-bottom:1px solid rgba(128,128,128,.15); }
        .roster-row:last-child { border-bottom:0; }
        .roster-info { display:flex; flex-direction:column; }
        .roster-pos { opacity:.6; }
    </style>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Направление --}}
    <div class="filter-tabs mb-2">
        <a href="{{ route('players.teams', array_merge(request()->query(), ['direction'=>'beach', 'scheme'=>null])) }}"
           class="filter-tab {{ $direction === 'beach' ? 'active' : '' }}">{{ __('players.beach') }}</a>
        <a href="{{ route('players.teams', array_merge(request()->query(), ['direction'=>'classic', 'scheme'=>null])) }}"
           class="filter-tab {{ $direction === 'classic' ? 'active' : '' }}">{{ __('players.classic') }}</a>
    </div>

    {{-- Схема --}}
    <div class="filter-tabs mb-3">
        <a href="{{ route('players.teams', array_merge(request()->query(), ['scheme'=>null])) }}"
           class="filter-tab {{ !$scheme ? 'active' : '' }}">{{ __('players.all_schemes') }}</a>
        @foreach($availableSchemes as $s)
        <a href="{{ route('players.teams', array_merge(request()->query(), ['scheme'=>$s])) }}"
           class="filter-tab {{ $scheme === $s ? 'active' : '' }}">{{ $schemeLabel($s) }}</a>
        @endforeach
    </div>

    {{-- Поиск + сортировка --}}
    <form method="GET" action="{{ route('players.teams') }}" class="d-flex gap-1 mb-3 flex-wrap">
        <input type="hidden" name="direction" value="{{ $direction }}">
        @if($scheme)<input type="hidden" name="scheme" value="{{ $scheme }}">@endif
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('players.search_placeholder') }}"
               class="form-select f-14" style="max-width:220px">
        <select name="sort" class="form-select f-14" style="max-width:170px" onchange="this.form.submit()">
            <option value="winrate"  {{ $sort==='winrate'  ? 'selected':'' }}>{{ __('players.sort_winrate') }}</option>
            <option value="wins"     {{ $sort==='wins'     ? 'selected':'' }}>{{ __('players.sort_wins') }}</option>
            <option value="matches"  {{ $sort==='matches'  ? 'selected':'' }}>{{ __('players.sort_matches') }}</option>
        </select>
        <button type="submit" class="btn btn-outline btn-small">Найти</button>
    </form>

    {{-- Таблица --}}
    @if($pairs->isEmpty())
        <div class="alert alert-info">{{ __('players.no_data') }}
            @if(!$scheme) — попробуй ввести схему игры @endif
        </div>
    @else
    <div class="table-scrollable">
        <table class="table f-14">
            <thead>
                <tr>
                    <th style="width:32px">#</th>
                    <th class="pair-col">{{ __('players.pair_or_team') }}</th>
                    <th class="text-center">{{ __('players.scheme') }}</th>
                    <th class="text-center">{{ __('players.matches_together') }}</th>
                    <th class="b-600 text-center">{{ __('players.wins') }}</th>
                    <th class="text-center">{{ __('players.losses') }}</th>
                    <th class="b-600 text-center">%{{ __('players.wins') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($pairs as $i => $pair)
                @php
                    $rank  = $pairs->firstItem() + $i;
                    $wr    = (float) $pair->winrate;
                    $losses = (int) $pair->matches_together - (int) $pair->wins_together;
                    $wrClass = $wr >= 60 ? 'cs' : ($wr >= 40 ? '' : 'red');
                @endphp
                <tr>
                    <td><span style="opacity:.5">{{ $rank }}</span></td>
                    <td class="pair-col">
                        @if($isTeamMode)
                            @php $capAva = $captainAvatars[$pair->last_team_id] ?? null; @endphp
                            <div class="pair-player">
                                <span class="pair-ava"><img src="{{ $capAva }}" alt="" class="pair-avatar" loading="lazy"></span><span class="pair-name">
                                    <a href="javascript:void(0)" class="blink b-600 team-roster-link" data-team="{{ $pair->last_team_id }}">{{ $pair->team_name ?: __('players.team_col') . ' #' . $pair->last_team_id }}</a>
                                    <div class="f-13" style="opacity:.6">{{ __('players.team_size', ['n' => (int) $pair->size]) }}</div>
                                </span>
                            </div>
                        @else
                        <div class="pair-cell">
                            @foreach([[$pair->player1_id, $pair->p1_last, $pair->p1_first], [$pair->player2_id, $pair->p2_last, $pair->p2_first]] as $pi => [$pid, $plast, $pfirst])
                            @if($pi === 1)<span class="pair-sep">×</span>@endif
                            <a href="{{ route('users.show', $pid) }}" class="pair-player blink">
                                <span class="pair-ava"><img src="{{ ($pairUsers[$pid] ?? null)?->profile_photo_url }}" alt="" class="pair-avatar" loading="lazy"></span><span class="pair-name">{{ trim($plast . ' ' . $pfirst) ?: '#'.$pid }}</span>
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </td>
                    <td class="text-center">
                        @if($pair->game_scheme)
                            <span class="f-12 b-600 px-2 py-1" style="background:rgba(128,128,128,.1);border-radius:4px">{{ $schemeLabel($pair->game_scheme) }}</span>
                        @else
                            <span style="opacity:.3">—</span>
                        @endif
                    </td>
                    @php
                        $gamesHeads[$isTeamMode ? (string) $pair->roster : ($pair->player1_id . '-' . $pair->player2_id)] = $isTeamMode
                            ? [[($pair->team_name ?: __('players.team_col')), $captainAvatars[$pair->last_team_id] ?? null]]
                            : [
                                [trim($pair->p1_last . ' ' . $pair->p1_first), ($pairUsers[$pair->player1_id] ?? null)?->profile_photo_url],
                                [trim($pair->p2_last . ' ' . $pair->p2_first), ($pairUsers[$pair->player2_id] ?? null)?->profile_photo_url],
                              ];
                        $gKey   = $isTeamMode ? (string) $pair->roster : ($pair->player1_id . '-' . $pair->player2_id);
                        $gTitle = $isTeamMode
                            ? ($pair->team_name ?: __('players.team_col'))
                            : (trim($pair->p1_last . ' ' . $pair->p1_first) . ' × ' . trim($pair->p2_last . ' ' . $pair->p2_first));
                    @endphp
                    <td class="text-center"><a href="javascript:void(0)" class="blink b-600 games-link" data-key="{{ $gKey }}" data-title="{{ $gTitle }}" data-count="{{ (int) $pair->matches_together }}">{{ (int) $pair->matches_together }}</a></td>
                    <td class="cs b-600 text-center">{{ (int) $pair->wins_together }}</td>
                    <td class="red text-center">{{ $losses }}</td>
                    <td class="b-600 text-center {{ $wrClass }}">{{ number_format($wr, 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $pairs->links() }}
    @endif

</div>
</div>
@include('players._games_modal')
@if($isTeamMode)
<div id="team-roster-modal" style="display:none;max-width:48rem;width:100%">
    <h3 class="-mt-05 mb-2" id="team-roster-title"></h3>
    <div id="team-roster-list"></div>
</div>
@endif
<x-slot name="script">
    <script src="/assets/fas.js"></script>
    @if($isTeamMode)
    <script>
    (function() {
        var rosters = @json($teamRosters);
        var emptyText = @json(__('players.team_roster_empty'));
        var captainText = @json(__('players.team_captain'));
        function el(tag, cls, text) {
            var e = document.createElement(tag);
            if (cls) e.className = cls;
            if (text != null) e.textContent = text;
            return e;
        }
        document.querySelectorAll('.team-roster-link').forEach(function(link) {
            link.addEventListener('click', function(ev) {
                ev.preventDefault();
                var list = rosters[link.dataset.team] || [];
                document.getElementById('team-roster-title').textContent = link.textContent.trim();
                var box = document.getElementById('team-roster-list');
                box.innerHTML = '';
                if (!list.length) box.appendChild(el('div', 'f-14', emptyText));
                list.forEach(function(m) {
                    var row = el('a', 'roster-row blink');
                    row.href = m.url;
                    var img = el('img', 'pair-avatar');
                    img.src = m.avatar || '';
                    img.alt = '';
                    row.appendChild(img);
                    var info = el('span', 'roster-info');
                    info.appendChild(el('span', 'b-600', m.name + (m.captain ? ' · ' + captainText : '')));
                    if (m.position) info.appendChild(el('span', 'f-13 roster-pos', m.position));
                    row.appendChild(info);
                    box.appendChild(row);
                });
                jQuery.fancybox.open({ src: '#team-roster-modal', type: 'inline' });
            });
        });
    })();
    </script>
    @endif
    @include('players._games_script')
</x-slot>
</x-voll-layout>
