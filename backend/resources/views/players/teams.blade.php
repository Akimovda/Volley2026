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

<div class="container">
<div class="ramka">

    {{-- Направление --}}
    <div class="d-flex gap-1 mb-3 flex-wrap">
        <a href="{{ route('players.teams', array_merge(request()->query(), ['direction'=>'beach', 'scheme'=>null])) }}"
           class="btn {{ $direction === 'beach' ? 'btn-primary' : 'btn-secondary' }} btn-small">
            🏖 {{ __('players.beach') }}
        </a>
        <a href="{{ route('players.teams', array_merge(request()->query(), ['direction'=>'classic', 'scheme'=>null])) }}"
           class="btn {{ $direction === 'classic' ? 'btn-primary' : 'btn-secondary' }} btn-small">
            🏐 {{ __('players.classic') }}
        </a>
    </div>

    {{-- Схема --}}
    <div class="d-flex gap-1 mb-3 flex-wrap f-13">
        <a href="{{ route('players.teams', array_merge(request()->query(), ['scheme'=>null])) }}"
           class="btn btn-small {{ !$scheme ? '' : 'btn-secondary' }}" style="padding:2px 10px">
            {{ __('players.all_schemes') }}
        </a>
        @foreach($availableSchemes as $s)
        <a href="{{ route('players.teams', array_merge(request()->query(), ['scheme'=>$s])) }}"
           class="btn btn-small {{ $scheme === $s ? '' : 'btn-secondary' }}" style="padding:2px 10px">
            {{ $s }}
        </a>
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
        <button type="submit" class="btn btn-secondary btn-small">Найти</button>
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
                    <th>{{ __('players.pair_or_team') }}</th>
                    <th>{{ __('players.scheme') }}</th>
                    <th>{{ __('players.matches_together') }}</th>
                    <th class="b-600">{{ __('players.wins') }}</th>
                    <th>{{ __('players.losses') }}</th>
                    <th class="b-600">%{{ __('players.wins') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($pairs as $i => $pair)
                @php
                    $rank  = $pairs->firstItem() + $i;
                    $wr    = (float) $pair->winrate;
                    $losses = $pair->matches_together - $pair->wins_together;
                    $wrClass = $wr >= 60 ? 'cs' : ($wr >= 40 ? '' : 'red');
                @endphp
                <tr>
                    <td><span style="opacity:.5">{{ $rank }}</span></td>
                    <td>
                        <a href="{{ route('users.show', $pair->player1_id) }}" class="blink">
                            {{ trim($pair->p1_last . ' ' . $pair->p1_first) ?: '#'.$pair->player1_id }}
                        </a>
                        <span style="opacity:.4"> × </span>
                        <a href="{{ route('users.show', $pair->player2_id) }}" class="blink">
                            {{ trim($pair->p2_last . ' ' . $pair->p2_first) ?: '#'.$pair->player2_id }}
                        </a>
                    </td>
                    <td>
                        @if($pair->game_scheme)
                            <span class="f-12 b-600 px-2 py-1" style="background:rgba(128,128,128,.1);border-radius:4px">{{ $pair->game_scheme }}</span>
                        @else
                            <span style="opacity:.3">—</span>
                        @endif
                    </td>
                    <td>{{ $pair->matches_together }}</td>
                    <td class="cs b-600">{{ $pair->wins_together }}</td>
                    <td class="red">{{ $losses }}</td>
                    <td class="b-600 {{ $wrClass }}">{{ number_format($wr, 1) }}%</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $pairs->links() }}
    @endif

</div>
</div>
</x-voll-layout>
