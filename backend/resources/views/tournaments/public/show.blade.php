<x-voll-layout body_class="tournament-public-page">
<x-slot name="title">{{ $event->title }} — Турнир</x-slot>
<x-slot name="description">{{ $event->title }} — турнирная сетка, результаты матчей, статистика команд и игроков</x-slot>
<x-slot name="canonical">{{ route('tournament.public.show', $event) }}</x-slot>
<x-slot name="h1">{{ $event->title }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.index') }}" itemprop="item"><span itemprop="name">Мероприятия</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $event) }}" itemprop="item"><span itemprop="name">{{ $event->title }}</span></a>
        <meta itemprop="position" content="3">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Турнир</span>
        <meta itemprop="position" content="4">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Мета --}}
    <div class="d-flex mb-3" style="flex-wrap:wrap;gap:8px">
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">{{ $event->direction === 'beach' ? '🏖 Пляжка' : '🏐 Классика' }}</span>
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">{{ $totalTeams }} команд</span>
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(231,97,47,.15);border-radius:6px;color:#E7612F">{{ $totalMatches }} матчей сыграно</span>
    </div>


    {{-- Выбор тура (сезонный турнир) --}}
    @if($occurrences->count() > 1)
    <div class="mb-3">
        <div class="d-flex" style="gap:6px;flex-wrap:wrap">
            @foreach($occurrences as $occ)
                @php
                    $isSelected = $selectedOccurrence && $selectedOccurrence->id === $occ->id;
                    $occDate = \Carbon\Carbon::parse($occ->starts_at)->setTimezone($event->timezone ?? 'Europe/Moscow');
                @endphp
                <a href="{{ route('tournament.public.show', [$event, 'tab' => $tab, 'occurrence_id' => $occ->id]) }}"
                   class="p-2 px-3 f-13 b-600"
                   style="border-radius:8px;text-decoration:none;{{ $isSelected ? 'background:#4f46e5;color:#fff' : 'background:rgba(128,128,128,.1)' }}">
                    Тур {{ $loop->iteration }} ({{ $occDate->format('d.m') }})
                </a>
            @endforeach
        </div>
    </div>
    @endif

    {{-- TV Mode + PDF --}}
    <div class="d-flex mb-3" style="gap:8px;flex-wrap:wrap">
        <a href="{{ route('tournament.tv', $event) }}" target="_blank" class="btn btn-secondary f-13" style="padding:6px 12px">
            📺 TV Mode
        </a>
        <a href="{{ route('tournament.pdf.schedule', $event) }}" class="btn btn-secondary f-13" style="padding:6px 12px">
            📄 PDF Расписание
        </a>
        <a href="{{ route('tournament.pdf.results', $event) }}" class="btn btn-secondary f-13" style="padding:6px 12px">
            📊 PDF Результаты
        </a>
        @auth
        @if(auth()->user()->role === 'admin' || (int)($event->organizer_id ?? 0) === auth()->id())
        <a href="{{ route('tournament.setup', [$event, 'occurrence_id' => $selectedOccurrence?->id]) }}"
           class="btn btn-primary f-13" style="padding:6px 12px">
            ⚙️ Настроить турнир
        </a>
        @endif
        @endauth
    </div>

    {{-- Табы --}}
    @php
        $hasBracketStages = $stages->whereIn('type', ['single_elim', 'double_elim'])->isNotEmpty();
        $tabs = [
            'overview' => 'Обзор',
            'groups'   => 'Группы',
        ];
        if ($hasBracketStages) {
            $tabs['bracket'] = 'Сетка';
        }
        $tabs += [
            'results'  => 'Результаты',
            'stats'    => 'Статистика',
            'photos'   => 'Фото',
        ];
        if ($event->season_id) {
            $tabs['season'] = 'Итоги сезона';
        }
    @endphp

    <div class="d-flex mb-3" style="gap:4px;flex-wrap:wrap;border-bottom:2px solid rgba(128,128,128,.15);padding-bottom:0">
        @foreach($tabs as $key => $label)
            <a href="{{ route('tournament.public.show', [$event, 'tab' => $key, 'occurrence_id' => $selectedOccurrence?->id]) }}"
               class="p-2 px-3 f-14 b-600"
               style="text-decoration:none;border-bottom:3px solid {{ $tab === $key ? '#E7612F' : 'transparent' }};{{ $tab === $key ? 'color:#E7612F' : 'opacity:.6' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ============================================================
         TAB: Обзор
    ============================================================ --}}
    @if($tab === 'overview')
        @foreach($stages as $stage)
            <div class="card p-3 mb-3">
                <div class="b-700 f-16 mb-1">{{ $stage->name }}</div>
                <div class="f-13 mb-2" style="opacity:.5">
                    {{ $stage->type }} · {{ strtoupper($stage->matchFormat()) }} · до {{ $stage->setPoints() }} очков
                    ·
                    @if($stage->isCompleted()) ✅ Завершена
                    @elseif($stage->isInProgress()) 🔄 В процессе
                    @else ⏳ Ожидание
                    @endif
                </div>

                {{-- Мини-таблица групп --}}
                @if($stage->groups->isNotEmpty())
                    <div class="row">
                        @foreach($stage->groups as $group)
                            <div class="col-md-6 mb-2">
                                <div class="b-600 f-13 mb-1">{{ $group->name }}</div>
                                @if($group->standings->isNotEmpty())
                                    @foreach($group->standings->sortBy('rank')->take(3) as $s)
                                        <div class="f-13 d-flex" style="gap:8px">
                                            <span class="b-700" style="width:18px">{{ $s->rank }}.</span>
                                            <span>
    <div>{{ $s->team->name ?? '—' }}</div>
    @if($s->team && $s->team->members->count())
    <div class="f-11" style="color:#6b7280">{{ $s->team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
    @endif
</span>
                                            <span style="opacity:.5;margin-left:auto">{{ $s->wins }}В {{ $s->losses }}П</span>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="f-13" style="opacity:.5">
                                        {{ $group->teams->pluck('name')->implode(', ') }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Последние результаты --}}
                @php $recent = $stage->matches->where('status', 'completed')->sortByDesc('scored_at')->take(3); @endphp
                @if($recent->isNotEmpty())
                    <div class="mt-2">
                        <div class="f-13 b-600 mb-1" style="opacity:.6">Последние результаты</div>
                        @foreach($recent as $m)
                            <div class="f-13 d-flex" style="gap:6px;padding:3px 0;border-bottom:1px solid rgba(128,128,128,.08)">
                                <span class="{{ $m->winner_team_id === $m->team_home_id ? 'b-700' : '' }}">{{ $m->teamHome->name ?? '?' }}</span>
                                <span style="opacity:.4">vs</span>
                                <span class="{{ $m->winner_team_id === $m->team_away_id ? 'b-700' : '' }}">{{ $m->teamAway->name ?? '?' }}</span>
                                <span style="margin-left:auto;opacity:.7">{{ $m->setsScore() }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        @if($stages->isEmpty())
            <div style="text-align:center;opacity:.5;padding:30px 0">Турнир пока не настроен.</div>
        @endif

    {{-- ============================================================
         TAB: Группы
    ============================================================ --}}
    @elseif($tab === 'groups')
        @foreach($stages as $stage)
            @if($stage->groups->isNotEmpty())
                @php
                    $stageTiebreakers = \App\Models\TournamentTiebreaker::where('stage_id', $stage->id)
                        ->whereIn('status', ['pending', 'resolved'])
                        ->get()
                        ->groupBy('group_id');
                @endphp
                <div class="mb-3">
                    <div class="b-700 f-16 mb-2">{{ $stage->name }}</div>
                    <div class="row">
                        @foreach($stage->groups as $group)
                            @php
                                $groupTbs = $stageTiebreakers[$group->id] ?? collect();
                                $pendingTbTeamIds = $groupTbs->where('status', 'pending')
                                    ->flatMap(fn($tb) => [$tb->team_a_id, $tb->team_b_id])
                                    ->unique()->toArray();
                            @endphp
                            <div class="col-md-6 mb-3">
                                <div class="card p-3">
                                    <div class="b-700 f-15 mb-2">{{ $group->name }}</div>
                                    @if($group->standings->isNotEmpty())
                                        <table style="width:100%;border-collapse:collapse;font-size:13px">
                                            <thead>
                                                <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                                                    <th class="p-1" style="text-align:left">#</th>
                                                    <th class="p-1" style="text-align:left">Команда</th>
                                                    <th class="p-1" style="text-align:center">И</th>
                                                    <th class="p-1" style="text-align:center">В</th>
                                                    <th class="p-1" style="text-align:center">П</th>
                                                    <th class="p-1" style="text-align:center">Сеты</th>
                                                    <th class="p-1" style="text-align:center">Разн.</th>
                                                    <th class="p-1" style="text-align:center">Оч.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($group->standings->sortBy('rank') as $s)
                                                    @php $inTb = in_array($s->team_id, $pendingTbTeamIds); @endphp
                                                    <tr style="border-bottom:1px solid rgba(128,128,128,.1){{ $inTb ? ';background:rgba(251,191,36,.06)' : '' }}">
                                                        <td class="p-1 b-700">{{ $s->rank }}{{ $inTb ? ' 🎲' : '' }}</td>
                                                        <td class="p-1">
    <div class="b-600">{{ $s->team->name ?? '—' }}</div>
    @if($s->team && $s->team->members->count())
    <div class="f-11" style="color:#6b7280">{{ $s->team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
    @endif
</td>
                                                        <td class="p-1" style="text-align:center">{{ $s->played }}</td>
                                                        <td class="p-1" style="text-align:center;color:#10b981">{{ $s->wins }}</td>
                                                        <td class="p-1" style="text-align:center;color:#dc2626">{{ $s->losses }}</td>
                                                        <td class="p-1" style="text-align:center">{{ $s->sets_won }}:{{ $s->sets_lost }}</td>
                                                        <td class="p-1" style="text-align:center">{{ $s->pointDiff() > 0 ? '+' : '' }}{{ $s->pointDiff() }}</td>
                                                        <td class="p-1 b-700" style="text-align:center">{{ $s->rating_points }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        @if($groupTbs->where('status', 'pending')->isNotEmpty())
                                        <div class="mt-2 f-12" style="color:#d97706">
                                            🎲 Ожидается жеребьёвка между командами с равными показателями
                                        </div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Матчи групповой стадии --}}
                    @php $groupMatches = $stage->matches->whereNotNull('group_id'); @endphp
                    @if($groupMatches->isNotEmpty())
                        @foreach($groupMatches->groupBy('group_id') as $gId => $gMatches)
                        @php $gName = $stage->groups->firstWhere('id', $gId)?->name ?? ''; @endphp
                        <div class="card p-3 mt-2">
                            <div class="b-600 f-14 mb-2">{{ $gName ? 'Матчи ' . $gName : 'Матчи' }}</div>
                            @foreach($gMatches->sortBy('round')->groupBy('round') as $round => $matches)
                                <div class="b-600 f-13 mb-1 mt-2" style="opacity:.5">Тур {{ $round }}</div>
                                @foreach($matches->sortBy('match_number') as $m)
                                    <div class="d-flex f-14" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                                        <span style="flex:1;text-align:right" class="{{ $m->winner_team_id === $m->team_home_id ? 'b-700' : '' }}">
                                            {{ $m->teamHome->name ?? 'TBD' }}
                                        @if($m->teamHome && $m->teamHome->members->count())
                                        <div class="f-11" style="color:#6b7280">{{ $m->teamHome->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                        @endif
                                        </span>
                                        <span class="px-2 b-700" style="min-width:80px;text-align:center;{{ $m->isCompleted() ? '' : 'opacity:.4' }}">
                                            {{ $m->setsScore() ?? 'vs' }}
                                        </span>
                                        <span style="flex:1" class="{{ $m->winner_team_id === $m->team_away_id ? 'b-700' : '' }}">
                                            {{ $m->teamAway->name ?? 'TBD' }}
                                        @if($m->teamAway && $m->teamAway->members->count())
                                        <div class="f-11" style="color:#6b7280">{{ $m->teamAway->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                        @endif
                                        </span>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                        @endforeach
                    @endif
                </div>
            @endif
        @endforeach

    {{-- ============================================================
         TAB: Сетка (Bracket)
    ============================================================ --}}
    @elseif($tab === 'bracket')
        @foreach($stages as $stage)
            @if(in_array($stage->type, ['single_elim', 'double_elim']))
                <div class="mb-3">
                    <div class="b-700 f-16 mb-2">{{ $stage->name }}</div>
                    @include('tournaments.public._bracket', ['stage' => $stage, 'matches' => $stage->matches])
                </div>
            @endif
        @endforeach

        @php $hasBracket = $stages->whereIn('type', ['single_elim', 'double_elim'])->isNotEmpty(); @endphp
        @if(!$hasBracket)
            <div style="text-align:center;opacity:.5;padding:30px 0">Нет стадий плей-офф для отображения сетки.</div>
        @endif

    {{-- ============================================================
         TAB: Результаты
    ============================================================ --}}
    @elseif($tab === 'results')
        {{-- Итоговая классификация --}}
        @php
            $classification = app(\App\Services\TournamentStatsService::class)->calculateFinalClassification($event, $selectedOccurrence?->id);
        @endphp
        @if(!empty($classification))
            @php
                // Группируем по группам
                $hasDivisions = isset($classification[0]['division']);
                $divisions = $hasDivisions
                    ? collect($classification)->groupBy('division')
                    : collect(['all' => collect($classification)]);
            @endphp

            @if($hasDivisions)
            <div class="row mb-3" id="division_results">
                @foreach($divisions as $divName => $divTeams)
                <div class="col-md-{{ (int)(12 / $divisions->count()) }}" style="margin-bottom:16px">
                    <div class="card p-3">
                        <div class="b-700 f-16 mb-2">🏆 Итоги дивизиона {{ $divName }}</div>
                        @foreach($divTeams as $i => $c)
                            @php
                                $localPlace = $i + 1;
                                $team = \App\Models\EventTeam::with('members.user')->find($c['team_id']);
                                $members = $team ? $team->members->map(function($m) {
                                    $u = $m->user;
                                    return $u ? '<a href="' . route('users.show', $u) . '" class="blink" style="color:#6b7280">' . $u->last_name . ' ' . $u->first_name . '</a>' : '?';
                                })->implode(' / ') : '';
                            @endphp
                            <div style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.08)">
                                <div class="d-flex f-14" style="gap:8px;align-items:center">
                                    <span class="b-700" style="width:26px;{{ $localPlace <= 3 ? 'font-size:18px' : '' }}">
                                        {{ $localPlace === 1 ? '🥇' : ($localPlace === 2 ? '🥈' : ($localPlace === 3 ? '🥉' : $localPlace . '.')) }}
                                    </span>
                                    <span class="{{ $localPlace <= 3 ? 'b-700' : '' }}">{{ $c['team_name'] }}</span>
                                </div>
                                @if($members)
                                    <div class="f-12" style="margin-left:34px;color:#6b7280">{!! $members !!}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @else
            {{-- Обычный турнир без дивизионов --}}
            <div class="card p-3 mb-3">
                <div class="b-700 f-16 mb-2">🏆 Итоговая классификация</div>
                @foreach($classification as $c)
                    @php
                        $team = \App\Models\EventTeam::with('members.user')->find($c['team_id']);
                        $members = $team ? $team->members->map(function($m) {
                            $u = $m->user;
                            return $u ? '<a href="' . route('users.show', $u) . '" class="blink" style="color:#6b7280">' . $u->last_name . ' ' . $u->first_name . '</a>' : '?';
                        })->implode(' / ') : '';
                    @endphp
                    <div style="padding:6px 0;border-bottom:1px solid rgba(128,128,128,.08)">
                        <div class="d-flex f-14" style="gap:8px;align-items:center">
                            <span class="b-700" style="width:26px;{{ $c['place'] <= 3 ? 'font-size:18px' : '' }}">
                                {{ $c['place'] === 1 ? '🥇' : ($c['place'] === 2 ? '🥈' : ($c['place'] === 3 ? '🥉' : $c['place'] . '.')) }}
                            </span>
                            <span class="{{ $c['place'] <= 3 ? 'b-700' : '' }}">{{ $c['team_name'] }}</span>
                        </div>
                        @if($members)
                            <div class="f-12" style="margin-left:34px;color:#6b7280">{!! $members !!}</div>
                        @endif
                    </div>
                @endforeach
            </div>
            @endif

            {{-- MVP --}}
            @if($event->tournament_mvp_user_id)
                @php $mvp = \App\Models\User::find($event->tournament_mvp_user_id); @endphp
                @if($mvp)
                    <div class="card p-3 mb-3" style="text-align:center">
                        <div class="f-13 b-600 mb-1" style="opacity:.5">MVP турнира</div>
                        <div class="f-20 b-800">⭐ {{ $mvp->displayName() }}</div>
                    </div>
                @endif
            @endif
        @endif


        @foreach($stages as $stage)
            <div class="card p-3 mb-3">
                <div class="b-700 f-16 mb-2">{{ $stage->name }}</div>
                @foreach($stage->matches->where('status', 'completed')->sortBy(['round', 'match_number']) as $m)
                    <div class="d-flex f-14" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                        <span class="f-12" style="opacity:.4;width:30px">R{{ $m->round }}</span>
                        <span style="flex:1;text-align:right" class="{{ $m->winner_team_id === $m->team_home_id ? 'b-700' : '' }}">
                            {{ $m->teamHome->name ?? '?' }}
                                @if($m->teamHome && $m->teamHome->members->count())
                                <div class="f-11" style="color:#6b7280">{{ $m->teamHome->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                @endif
                        </span>
                        <span class="px-2 b-700" style="min-width:100px;text-align:center">
                            {{ $m->setsScore() }}
                        </span>
                        <span style="flex:1" class="{{ $m->winner_team_id === $m->team_away_id ? 'b-700' : '' }}">
                            {{ $m->teamAway->name ?? '?' }}
                                @if($m->teamAway && $m->teamAway->members->count())
                                <div class="f-11" style="color:#6b7280">{{ $m->teamAway->members->map(fn($mm) => $mm->user->last_name ?? '?')->implode(' / ') }}</div>
                                @endif
                        </span>
                    </div>
                @endforeach

                @if($stage->matches->where('status', 'completed')->isEmpty())
                    <div class="f-13" style="opacity:.5">Нет завершённых матчей.</div>
                @endif
            </div>
        @endforeach

    {{-- ============================================================
         TAB: Статистика
    ============================================================ --}}
    @elseif($tab === 'stats')
        @php
            $topPlayers = app(\App\Services\TournamentStatsService::class)->getTopPlayers($event->id, 20);
        @endphp

        <div class="card p-3 mb-3">
            <div class="b-700 f-16 mb-2">Рейтинг игроков</div>

            @if($topPlayers->isNotEmpty())
                <div class="d-none-desktop f-12 mb-1" style="opacity:.4;display:none">👆 Свайпайте таблицу влево</div>
                <style>
                    @media (max-width:768px) { .d-none-desktop { display:block !important; } }
                </style>
                <div style="overflow-x:auto;-webkit-overflow-scrolling:touch">
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:560px">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                            <th class="p-1" style="text-align:left">#</th>
                            <th class="p-1" style="text-align:left">Игрок</th>
                            <th class="p-1" style="text-align:left">Команда</th>
                            <th class="p-1" style="text-align:center">Матчи</th>
                            <th class="p-1" style="text-align:center">Победы</th>
                            <th class="p-1" style="text-align:center">WinRate</th>
                            <th class="p-1" style="text-align:center">Сеты</th>
                            <th class="p-1" style="text-align:center">Разн.</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topPlayers as $i => $ps)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                                <td class="p-1 b-700">{{ $i + 1 }}</td>
                                <td class="p-1">
                                    <a href="{{ route('users.show', $ps->user_id) }}" class="blink">
                                        {{ $ps->user->displayName() }}
                                    </a>
                                </td>
                                <td class="p-1" style="opacity:.7">{{ $ps->team->name ?? '—' }}</td>
                                <td class="p-1" style="text-align:center">{{ $ps->matches_played }}</td>
                                <td class="p-1" style="text-align:center;color:#10b981">{{ $ps->matches_won }}</td>
                                <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $ps->match_win_rate }}%</td>
                                <td class="p-1" style="text-align:center">{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
                                <td class="p-1" style="text-align:center">{{ $ps->point_diff > 0 ? '+' : '' }}{{ $ps->point_diff }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            @else
                <div class="f-13" style="opacity:.5">Нет данных. Статистика появится после первых сыгранных матчей.</div>
            @endif
        </div>

    {{-- ============================================================
         TAB: Фото
    ============================================================ --}}
    @elseif($tab === 'photos')
        @php $tournamentPhotos = $event->getMedia('tournament_photos'); @endphp

        @if($tournamentPhotos->isNotEmpty())
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
                @foreach($tournamentPhotos as $media)
                    <a href="{{ $media->getUrl() }}" data-fancybox="tournament-gallery">
                        <img src="{{ $media->getUrl('thumb') }}" style="width:100%;height:180px;object-fit:cover;border-radius:10px" loading="lazy">
                    </a>
                @endforeach
            </div>
        @else
            <div style="text-align:center;opacity:.5;padding:30px 0">Фотографии пока не добавлены.</div>
        @endif

    @elseif($tab === 'season')
        @if($event->season_id && $seasonStats->isNotEmpty())
            @php
                $season = \App\Models\TournamentSeason::find($event->season_id);
                $playedRounds = $seasonStats->max('rounds_played');
            @endphp
            <div class="card p-3 mb-3">
                <div class="b-700 f-16 mb-1">🏆 {{ $season->name ?? 'Сезон' }}</div>
                <div class="f-13 mb-3" style="opacity:.5">
                    Сыграно туров: {{ $playedRounds }}
                    · Игроков: {{ $seasonStats->count() }}
                </div>

                <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                            <th class="p-1" style="text-align:left">#</th>
                            <th class="p-1" style="text-align:left">Игрок</th>
                            <th class="p-1" style="text-align:center">Туров</th>
                            <th class="p-1" style="text-align:center">Матчей</th>
                            <th class="p-1" style="text-align:center">Побед</th>
                            <th class="p-1" style="text-align:center">WinRate</th>
                            <th class="p-1" style="text-align:center">Сеты</th>
                            <th class="p-1" style="text-align:center">Разн. оч.</th>
                            <th class="p-1" style="text-align:center">Серия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($seasonStats as $i => $ss)
                            <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                                <td class="p-1 b-700">{{ $i + 1 }}</td>
                                <td class="p-1">
                                    @if($ss->user)
                                        <a href="{{ route('users.show', $ss->user_id) }}" class="blink">
                                            {{ $ss->user->last_name }} {{ $ss->user->first_name }}
                                        </a>
                                    @else
                                        ?
                                    @endif
                                </td>
                                <td class="p-1" style="text-align:center">{{ $ss->rounds_played }}</td>
                                <td class="p-1" style="text-align:center">{{ $ss->matches_played }}</td>
                                <td class="p-1" style="text-align:center;color:#10b981">{{ $ss->matches_won }}</td>
                                <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ number_format($ss->match_win_rate, 1) }}%</td>
                                <td class="p-1" style="text-align:center">{{ $ss->sets_won }}:{{ $ss->sets_lost }}</td>
                                <td class="p-1" style="text-align:center">{{ ($ss->points_scored - $ss->points_conceded) > 0 ? '+' : '' }}{{ $ss->points_scored - $ss->points_conceded }}</td>
                                <td class="p-1" style="text-align:center">
                                    @if($ss->current_streak > 0)
                                        <span style="color:#10b981">W{{ $ss->current_streak }}</span>
                                    @elseif($ss->current_streak < 0)
                                        <span style="color:#dc2626">L{{ abs($ss->current_streak) }}</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>

            @if($season)
            <div class="mt-2" style="text-align:center">
                <a href="{{ route('seasons.show.slug', [$season->league?->slug ?? 'league', $season->slug]) }}" class="btn btn-secondary f-13">
                    Страница сезона →
                </a>
            </div>
            @endif
        @else
            <div style="text-align:center;opacity:.5;padding:30px 0">
                {{ $event->season_id ? 'Нет данных по сезону.' : 'Этот турнир не привязан к сезону.' }}
            </div>
        @endif

    @endif

</div>
</div>

{{-- Live polling --}}
<x-slot name="script">
<script>
(function() {
    var liveUrl = @json(route('tournament.public.live', $event));
    var pollInterval = 15000; // 15 сек

    function pollLive() {
        fetch(liveUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // Для MVP: просто перезагружаем при изменениях
                // В будущем — точечное обновление DOM
                console.log('[Tournament Live]', data.stages.length, 'stages');
            })
            .catch(function(e) { console.warn('[Tournament Live] Error:', e); });
    }

    // Запускаем polling только если турнир в процессе
    @if($stages->where('status', 'in_progress')->isNotEmpty())
        setInterval(pollLive, pollInterval);
    @endif
})();
</script>
</x-slot>

</x-voll-layout>
