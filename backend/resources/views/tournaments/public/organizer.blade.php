<x-voll-layout body_class="tournament-organizer-page">
<x-slot name="title">Турниры — {{ $organizer->displayName() }}</x-slot>
<x-slot name="h1">Турниры организатора</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('users.show', $organizer) }}" itemprop="item"><span itemprop="name">{{ $organizer->displayName() }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">Турниры</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Сводка --}}
    <div class="row mb-3">
        <div class="col-md-3 mb-2">
            <div class="card p-3 text-center">
                <div class="f-24 b-800" style="color:#E7612F">{{ $tournaments->total() }}</div>
                <div class="f-12" style="opacity:.5">Турниров</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card p-3 text-center">
                <div class="f-24 b-800">{{ $totalTeams }}</div>
                <div class="f-12" style="opacity:.5">Команд</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card p-3 text-center">
                <div class="f-24 b-800">{{ $totalMatches }}</div>
                <div class="f-12" style="opacity:.5">Матчей</div>
            </div>
        </div>
        <div class="col-md-3 mb-2">
            <div class="card p-3 text-center">
                <div class="f-24 b-800">{{ $topPlayers->count() > 0 ? $topPlayers->sum('agg_played') : 0 }}</div>
                <div class="f-12" style="opacity:.5">Игр сыграно</div>
            </div>
        </div>
    </div>

    {{-- Топ игроков --}}
    @if($topPlayers->isNotEmpty())
    <div class="card p-3 mb-3">
        <div class="b-700 f-16 mb-2">🏆 Рейтинг игроков</div>
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                    <th class="p-1" style="text-align:left">#</th>
                    <th class="p-1" style="text-align:left">Игрок</th>
                    <th class="p-1" style="text-align:center">Матчи</th>
                    <th class="p-1" style="text-align:center">Победы</th>
                    <th class="p-1" style="text-align:center">WinRate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topPlayers as $i => $tp)
                    @php $wr = $tp->agg_played > 0 ? round($tp->agg_won / $tp->agg_played * 100, 1) : 0; @endphp
                    <tr style="border-bottom:1px solid rgba(128,128,128,.1)">
                        <td class="p-1 b-700">{{ $i + 1 }}</td>
                        <td class="p-1">
                            <a href="{{ route('users.show', $tp->user_id) }}" class="blink">{{ $tp->user->displayName() }}</a>
                        </td>
                        <td class="p-1" style="text-align:center">{{ $tp->agg_played }}</td>
                        <td class="p-1" style="text-align:center;color:#10b981">{{ $tp->agg_won }}</td>
                        <td class="p-1 b-700" style="text-align:center;color:#E7612F">{{ $wr }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Список турниров --}}
    <div class="b-700 f-16 mb-2">Все турниры</div>
    @foreach($tournaments as $tourn)
        @php
            $matchesCount = $tourn->tournamentStages->sum('matches_count');
            $isActive = $tourn->tournamentStages->where('status', 'in_progress')->isNotEmpty();
            $isComplete = $tourn->tournamentStages->isNotEmpty() && $tourn->tournamentStages->every(fn($s) => $s->status === 'completed');
        @endphp
        <div class="card p-3 mb-2">
            <div class="d-flex between fvc" style="flex-wrap:wrap;gap:8px">
                <div style="flex:1;min-width:200px">
                    <a href="{{ route('tournament.public.show', $tourn) }}" class="blink b-600 f-16">{{ $tourn->title }}</a>
                    <div class="f-13" style="opacity:.5">
                        {{ $tourn->starts_at ? $tourn->starts_at->format('d.m.Y') : '' }}
                        · {{ $tourn->direction === 'beach' ? 'Пляж' : 'Классика' }}
                        @if($tourn->location) · {{ $tourn->location->name }} @endif
                        · {{ $matchesCount }} матчей
                    </div>
                </div>
                @if($isActive)
                    <span class="f-12 p-1 px-2 b-600" style="background:rgba(16,185,129,.15);border-radius:6px;color:#10b981">LIVE</span>
                @elseif($isComplete)
                    <span class="f-12 p-1 px-2 b-600" style="background:rgba(128,128,128,.1);border-radius:6px">Завершён</span>
                @else
                    <span class="f-12 p-1 px-2 b-600" style="background:rgba(255,193,7,.15);border-radius:6px;color:#ca8a04">В процессе</span>
                @endif
            </div>
        </div>
    @endforeach

    @if($tournaments->isEmpty())
        <div style="text-align:center;opacity:.5;padding:30px 0">Турниров пока нет.</div>
    @endif

    {{ $tournaments->links() }}

</div>
</div>

</x-voll-layout>
