<x-voll-layout body_class="rating-page">
<x-slot name="title">{{ __('players.rating_title') }}</x-slot>
<x-slot name="h1">{{ __('players.rating_title') }}</x-slot>
<x-slot name="t_description">OpenSkill — честный рейтинг с учётом опыта и силы соперников</x-slot>
<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ __('players.rating_title') }}</span>
        <meta itemprop="position" content="2">
    </li>
</x-slot>
<x-slot name="d_description">
    <div class="d-flex gap-1 mt-2 flex-wrap">
        <a href="{{ route('players.teams') }}" class="btn btn-secondary">🤝 {{ __('players.teams_title') }}</a>
        <a href="{{ route('pages.rating_info') }}" class="btn btn-secondary">❓ {{ __('players.how_rating_works') }}</a>
    </div>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Вкладки направления --}}
    <div class="filter-tabs mb-1">
        <a href="{{ route('players.rating', array_merge(request()->query(), ['direction'=>'beach'])) }}"
           class="filter-tab {{ $direction === 'beach' ? 'active' : '' }}">{{ __('players.beach') }}</a>
        <a href="{{ route('players.rating', array_merge(request()->query(), ['direction'=>'classic'])) }}"
           class="filter-tab {{ $direction === 'classic' ? 'active' : '' }}">{{ __('players.classic') }}</a>
    </div>
    @if(!$isSeasonMode)
    <div class="f-13 mb-3" style="opacity:.5">{{ $direction === 'beach' ? '2x2 · 3x3 · 4x4' : '4x4 · 4x2 · 5x1 · 5x1+либеро' }}</div>
    @else
    <div class="mb-3"></div>
    @endif

    {{-- Сезонный фильтр + поиск --}}
    <form method="GET" action="{{ route('players.rating') }}" class="d-flex gap-1 mb-3 flex-wrap">
        <input type="hidden" name="direction" value="{{ $direction }}">
        <select name="season_id" class="form-select f-14" style="max-width:200px" onchange="this.form.submit()">
            <option value="">Карьерный рейтинг</option>
            @foreach($seasons as $s)
                <option value="{{ $s->id }}" {{ $seasonId == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
            @endforeach
        </select>
        <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('players.search_placeholder') }}"
               class="form-select f-14" style="max-width:200px">
        <input type="hidden" name="sort" value="{{ $sort }}">
        <button type="submit" class="btn btn-outline btn-small">{{ __('profile.rating_btn_apply') }}</button>
    </form>

    {{-- Сортировка --}}
    @php
        function ratingSortUrl($field, $current, $currentDir, $params) {
            $d = ($field === $current && $currentDir === 'desc') ? 'asc' : 'desc';
            return url()->current() . '?' . http_build_query(array_merge($params, ['sort' => $field, 'dir' => $d]));
        }
        $qp = request()->except(['sort','dir']);
    @endphp
    <div class="f-13 mb-05" style="opacity:.5">Сортировка:</div>
    <div class="filter-tabs mb-3">
        @foreach([
            'rating'   => __('players.conservative_rating'),
            'mu'       => __('players.mu'),
            'delta7'   => __('players.delta_7d'),
            'wins'     => __('players.wins'),
            'meetings' => __('players.meetings'),
            'games'    => __('players.games'),
        ] as $key => $label)
        <a href="{{ ratingSortUrl($key, $sort, $dir, $qp) }}"
           class="filter-tab {{ $sort === $key ? 'active' : '' }}">{{ $label }}{{ $sort === $key ? ($dir === 'desc' ? ' ↓' : ' ↑') : '' }}</a>
        @endforeach
    </div>

    {{-- Таблица --}}
    @if($players->isEmpty())
        <div class="alert alert-info">{{ __('players.no_data') }}</div>
    @else
    <div class="table-scrollable">
        <table class="table f-14">
            <thead>
                <tr>
                    <th style="width:32px">{{ __('players.rank') }}</th>
                    <th>{{ __('players.player') }}</th>
                    @if($isSeasonMode)
                        <th>Дивизион</th>
                        <th class="text-center">Туры</th>
                    @endif
                    <th class="b-600 text-center">{{ __('players.conservative_rating') }}</th>
                    @if(!$isSeasonMode)
                        <th class="text-center">{{ __('players.delta_7d') }}</th>
                        <th class="text-center">{{ __('players.mu') }}</th>
                        <th class="hm text-center">{{ __('players.sigma') }}</th>
                    @endif
                    <th class="text-center">{{ __('players.games') }}</th>
                    <th class="text-center">{{ __('players.wins') }}</th>
                    <th class="hm text-center">WinRate</th>
                    <th class="hm text-center">{{ __('players.meetings') }}</th>
                    <th class="hm text-center">{{ __('players.oz_op') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($players as $i => $stat)
                @php
                    $rank = $players->firstItem() + $i;
                    $cr = $isSeasonMode
                        ? max(0, ($stat->mu_season ?? 25) - 3 * ($stat->sigma_season ?? 8.333))
                        : max(0, ($stat->mu ?? 25) - 3 * ($stat->sigma ?? 8.333));
                    $uid   = $isSeasonMode ? $stat->user_id : $stat->user_id;
                    $fname = $isSeasonMode ? ($stat->user->first_name ?? '') : ($stat->first_name ?? '');
                    $lname = $isSeasonMode ? ($stat->user->last_name ?? '') : ($stat->last_name ?? '');
                    $matches = $isSeasonMode ? $stat->matches_played : $stat->total_matches;
                    $wins    = $isSeasonMode ? $stat->matches_won    : $stat->total_wins;
                    $wr      = $matches > 0 ? round($wins / $matches * 100, 1) : 0;
                    $pd      = $isSeasonMode
                        ? ($stat->points_scored ?? 0) - ($stat->points_conceded ?? 0)
                        : (($stat->total_points_scored ?? 0) - ($stat->total_points_conceded ?? 0));
                    $scored    = $isSeasonMode ? ($stat->points_scored ?? 0) : ($stat->total_points_scored ?? 0);
                    $conceded  = $isSeasonMode ? ($stat->points_conceded ?? 0) : ($stat->total_points_conceded ?? 0);
                    $ratio = $conceded > 0 ? round($scored / $conceded, 2) : 1.0;
                    $delta7 = $stat->delta_7d ?? 0;
                @endphp
                <tr>
                    <td>
                        @if($rank <= 3)
                            <span class="f-16">{{ ['🥇','🥈','🥉'][$rank - 1] }}</span>
                        @else
                            <span style="opacity:.5">{{ $rank }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('users.show', $uid) }}" class="blink b-600">
                            {{ trim($lname . ' ' . $fname) ?: '#'.$uid }}
                        </a>
                    </td>
                    @if($isSeasonMode)
                        <td class="f-13" style="opacity:.7">{{ $stat->league?->name ?? '—' }}</td>
                        <td class="text-center">{{ $stat->rounds_played }}</td>
                    @endif
                    <td class="text-center">
                        <span class="b-700 f-16" style="color:#E7612F">{{ number_format($cr, 1) }}</span>
                    </td>
                    @if(!$isSeasonMode)
                        <td class="text-center">
                            @if($delta7 > 0)
                                <span class="cs b-600">+{{ number_format($delta7, 1) }}</span>
                            @elseif($delta7 < 0)
                                <span class="red">{{ number_format($delta7, 1) }}</span>
                            @else
                                <span style="opacity:.3">—</span>
                            @endif
                        </td>
                        <td class="f-13 text-center" style="opacity:.7">{{ number_format($stat->mu ?? 25, 2) }}</td>
                        <td class="f-13 hm text-center" style="opacity:.5">{{ number_format($stat->sigma ?? 8.333, 2) }}</td>
                    @endif
                    <td class="text-center">{{ $matches }}</td>
                    <td class="cs b-600 text-center">{{ $wins }}</td>
                    <td class="hm f-13 text-center">{{ $wr }}%</td>
                    <td class="hm f-13 text-center" style="opacity:.7">{{ $isSeasonMode ? '—' : ($stat->unique_opponents ?? 0) }}</td>
                    <td class="hm f-13 text-center {{ $ratio >= 1.0 ? 'cs' : 'red' }}">{{ number_format($ratio, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    {{ $players->links() }}
    @endif

</div>
</div>
</x-voll-layout>
