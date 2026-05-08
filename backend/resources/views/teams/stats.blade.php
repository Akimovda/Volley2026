<x-voll-layout body_class="team-stats-page">
<x-slot name="title">{{ $team->name }} — {{ __('profile.team_stats_title') }}</x-slot>
<x-slot name="h1">{{ $team->name }}</x-slot>

<x-slot name="breadcrumbs">
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <a href="{{ route('events.show', $team->event) }}" itemprop="item"><span itemprop="name">{{ $team->event->title }}</span></a>
        <meta itemprop="position" content="2">
    </li>
    <li itemprop="itemListElement" itemscope itemtype="http://schema.org/ListItem">
        <span itemprop="name">{{ $team->name }}</span>
        <meta itemprop="position" content="3">
    </li>
</x-slot>

<div class="container">
<div class="ramka">

    {{-- Общая статистика --}}
    <div class="row mb-4">
        <div class="col-6 col-md-3 text-center mb-3">
            <div class="f-28 fw-bold">{{ $teamStats['matches_played'] }}</div>
            <div class="text-muted f-14">{{ __('profile.team_stats_matches') }}</div>
        </div>
        <div class="col-6 col-md-3 text-center mb-3">
            <div class="f-28 fw-bold text-success">{{ $teamStats['wins'] }}</div>
            <div class="text-muted f-14">{{ __('profile.team_stats_wins') }}</div>
        </div>
        <div class="col-6 col-md-3 text-center mb-3">
            <div class="f-28 fw-bold text-danger">{{ $teamStats['losses'] }}</div>
            <div class="text-muted f-14">{{ __('profile.team_stats_losses') }}</div>
        </div>
        <div class="col-6 col-md-3 text-center mb-3">
            <div class="f-28 fw-bold">{{ $teamStats['match_win_rate'] }}%</div>
            <div class="text-muted f-14">WinRate</div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-4 text-center">
            <span class="f-14 text-muted">{{ __('profile.team_stats_sets') }}</span>
            <strong>{{ $teamStats['sets_won'] }}:{{ $teamStats['sets_lost'] }}</strong>
        </div>
        <div class="col-md-4 text-center">
            <span class="f-14 text-muted">{{ __('profile.team_stats_points') }}</span>
            <strong>{{ $teamStats['points_scored'] }}:{{ $teamStats['points_conceded'] }}</strong>
        </div>
        <div class="col-md-4 text-center">
            @php $pd = $teamStats['points_scored'] - $teamStats['points_conceded']; @endphp
            <span class="f-14 text-muted">{{ __('profile.team_stats_diff') }}</span>
            <strong class="{{ $pd >= 0 ? 'text-success' : 'text-danger' }}">{{ $pd >= 0 ? '+' : '' }}{{ $pd }}</strong>
        </div>
    </div>

    {{-- Состав команды --}}
    <h3 class="f-18 fw-bold mb-3">{{ __('profile.team_stats_lineup') }}</h3>
    <div class="row mb-4">
        @foreach($team->members as $member)
            <div class="col-6 col-md-3 mb-2">
                <div class="d-flex align-items-center gap-2">
                    @if($member->user)
                        <a href="{{ route('users.show', $member->user->id) }}">{{ $member->user->name }}</a>
                    @else
                        {{ __('profile.team_stats_player_n', ['id' => $member->user_id]) }}
                    @endif
                    @if($member->user_id === $team->captain_user_id)
                        <span class="badge bg-warning text-dark">{{ __('profile.team_stats_captain_short') }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- Индивидуальная статистика --}}
    @if($playerStats->isNotEmpty())
        <h3 class="f-18 fw-bold mb-3">{{ __('profile.team_stats_player_stats') }}</h3>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-hover">
                <thead>
                    <tr><th>{{ __('profile.rating_col_player') }}</th><th>{{ __('profile.team_stats_matches') }}</th><th>{{ __('profile.team_stats_wins') }}</th><th>{{ __('profile.team_stats_winrate') }}</th><th>{{ __('profile.team_stats_sets') }}</th><th>{{ __('profile.rating_col_pts_diff') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($playerStats as $ps)
                        <tr>
                            <td>
                                @if($ps->user)
                                    <a href="{{ route('users.show', $ps->user->id) }}">{{ $ps->user->name }}</a>
                                @else
                                    {{ __('profile.team_stats_player_n', ['id' => $ps->user_id]) }}
                                @endif
                            </td>
                            <td>{{ $ps->matches_played }}</td>
                            <td>{{ $ps->matches_won }}</td>
                            <td><strong>{{ number_format($ps->match_win_rate, 1) }}%</strong></td>
                            <td>{{ $ps->sets_won }}:{{ $ps->sets_lost }}</td>
                            <td class="{{ $ps->point_diff >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ $ps->point_diff >= 0 ? '+' : '' }}{{ $ps->point_diff }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- История матчей --}}
    @if($matches->isNotEmpty())
        <h3 class="f-18 fw-bold mb-3">{{ __('profile.team_stats_match_history') }}</h3>
        <div class="table-responsive mb-4">
            <table class="table table-sm">
                <thead>
                    <tr><th>{{ __('profile.team_stats_col_stage') }}</th><th>{{ __('profile.team_stats_col_opp') }}</th><th>{{ __('profile.team_stats_col_score') }}</th><th>{{ __('profile.team_stats_col_details') }}</th><th>{{ __('profile.team_stats_col_result') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($matches as $m)
                        @php
                            $isHome = $m->team_home_id === $team->id;
                            $opponent = $isHome ? $m->teamAway : $m->teamHome;
                            $won = $m->winner_team_id === $team->id;
                        @endphp
                        <tr>
                            <td class="f-14 text-muted">{{ $m->stage?->name ?? '—' }}</td>
                            <td>{{ $opponent?->name ?? 'TBD' }}</td>
                            <td><strong>{{ $isHome ? $m->setsScore() : $m->sets_away . ':' . $m->sets_home }}</strong></td>
                            <td class="f-14">{{ $m->detailedScore() }}</td>
                            <td>
                                <span class="badge bg-{{ $won ? 'success' : 'danger' }}">
                                    {{ $won ? __('profile.team_stats_won') : ($m->status === 'forfeit' ? __('profile.team_stats_tech') : __('profile.team_stats_lost')) }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Турнирные таблицы --}}
    @if($standings->isNotEmpty())
        <h3 class="f-18 fw-bold mb-3">{{ __('profile.team_stats_positions') }}</h3>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr><th>{{ __('profile.team_stats_col_stage') }}</th><th>{{ __('profile.team_stats_col_group') }}</th><th>{{ __('profile.team_stats_col_pos') }}</th><th>{{ __('tournaments.standings_col_played') }}</th><th>{{ __('tournaments.standings_col_w') }}</th><th>{{ __('tournaments.standings_col_l') }}</th><th>{{ __('profile.rating_col_sets') }}</th><th>{{ __('tournaments.tv_pts_col') }}</th></tr>
                </thead>
                <tbody>
                    @foreach($standings as $s)
                        <tr>
                            <td>{{ $s->stage?->name ?? '—' }}</td>
                            <td>{{ $s->group?->name ?? '—' }}</td>
                            <td><strong>{{ $s->rank }}</strong></td>
                            <td>{{ $s->played }}</td>
                            <td class="text-success">{{ $s->wins }}</td>
                            <td class="text-danger">{{ $s->losses }}</td>
                            <td>{{ $s->sets_won }}:{{ $s->sets_lost }}</td>
                            <td>{{ $s->points_scored }}:{{ $s->points_conceded }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Кнопка Excel --}}
    @if($team->event)
        <div class="mt-4">
            <a href="{{ route('tournament.excel.results', $team->event) }}" class="btn btn-outline-success">
                {{ __('profile.team_stats_btn_csv') }}
            </a>
        </div>
    @endif

</div>
</div>

</x-voll-layout>
