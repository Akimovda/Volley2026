<x-voll-layout body_class="tournament-public-page">
<x-slot name="title">{{ $event->title }} — Турнир</x-slot>
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
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(41,103,186,.15);border-radius:6px">{{ $event->direction === 'beach' ? '🏖 Пляж' : '🏐 Классика' }}</span>
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">{{ $totalTeams }} команд</span>
        <span class="p-1 px-2 f-12 b-600" style="background:rgba(231,97,47,.15);border-radius:6px;color:#E7612F">{{ $totalMatches }} матчей сыграно</span>
    </div>


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
    </div>

    {{-- Табы --}}
    @php
        $tabs = [
            'overview' => 'Обзор',
            'groups'   => 'Группы',
            'bracket'  => 'Сетка',
            'results'  => 'Результаты',
            'stats'    => 'Статистика',
            'photos'   => 'Фото',
        ];
    @endphp

    <div class="d-flex mb-3" style="gap:4px;flex-wrap:wrap;border-bottom:2px solid rgba(128,128,128,.15);padding-bottom:0">
        @foreach($tabs as $key => $label)
            <a href="{{ route('tournament.public.show', [$event, 'tab' => $key]) }}"
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
                                            <span>{{ $s->team->name ?? '—' }}</span>
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
                                <span style="margin-left:auto;opacity:.7">{{ $m->scoreFormatted() }}</span>
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
                <div class="mb-3">
                    <div class="b-700 f-16 mb-2">{{ $stage->name }}</div>
                    <div class="row">
                        @foreach($stage->groups as $group)
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
                                                    <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                                                        <td class="p-1 b-700">{{ $s->rank }}</td>
                                                        <td class="p-1">{{ $s->team->name ?? '—' }}</td>
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
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Матчи групповой стадии --}}
                    @php $groupMatches = $stage->matches->whereNotNull('group_id'); @endphp
                    @if($groupMatches->isNotEmpty())
                        <div class="card p-3 mt-2">
                            <div class="b-600 f-14 mb-2">Матчи</div>
                            @foreach($groupMatches->groupBy('round') as $round => $matches)
                                <div class="b-600 f-13 mb-1 mt-2" style="opacity:.5">Тур {{ $round }}</div>
                                @foreach($matches as $m)
                                    <div class="d-flex f-14" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                                        <span style="flex:1;text-align:right" class="{{ $m->winner_team_id === $m->team_home_id ? 'b-700' : '' }}">
                                            {{ $m->teamHome->name ?? 'TBD' }}
                                        </span>
                                        <span class="px-2 b-700" style="min-width:80px;text-align:center;{{ $m->isCompleted() ? '' : 'opacity:.4' }}">
                                            {{ $m->scoreFormatted() ?? 'vs' }}
                                        </span>
                                        <span style="flex:1" class="{{ $m->winner_team_id === $m->team_away_id ? 'b-700' : '' }}">
                                            {{ $m->teamAway->name ?? 'TBD' }}
                                        </span>
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
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
            $classification = app(\App\Services\TournamentStatsService::class)->calculateFinalClassification($event);
        @endphp
        @if(!empty($classification))
            <div class="card p-3 mb-3">
                <div class="b-700 f-16 mb-2">🏆 Итоговая классификация</div>
                @foreach($classification as $c)
                    <div class="d-flex f-14" style="padding:5px 0;border-bottom:1px solid rgba(128,128,128,.08);gap:8px;align-items:center">
                        <span class="b-700" style="width:30px;{{ $c['place'] <= 3 ? 'color:#E7612F;font-size:18px' : '' }}">
                            {{ $c['place'] === 1 ? '🥇' : ($c['place'] === 2 ? '🥈' : ($c['place'] === 3 ? '🥉' : $c['place'] . '.')) }}
                        </span>
                        <span class="{{ $c['place'] <= 3 ? 'b-700' : '' }}">{{ $c['team_name'] }}</span>
                    </div>
                @endforeach
            </div>

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
                        </span>
                        <span class="px-2 b-700" style="min-width:100px;text-align:center">
                            {{ $m->scoreFormatted() }}
                        </span>
                        <span style="flex:1" class="{{ $m->winner_team_id === $m->team_away_id ? 'b-700' : '' }}">
                            {{ $m->teamAway->name ?? '?' }}
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
                <table style="width:100%;border-collapse:collapse;font-size:13px">
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
