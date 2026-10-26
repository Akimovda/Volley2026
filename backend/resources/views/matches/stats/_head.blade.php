@php
    /** @var array $stats */
    $tz = $event->timezone ?? 'Europe/Moscow';
    $h = $stats['header'];
    $teams = $stats['teams'];
    $setScores = collect($stats['sets'])->map(fn ($s) => $s['score']['home'] . ':' . $s['score']['away'])->implode(' · ');
    $homeWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $teams['home']['id'];
    $awayWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $teams['away']['id'];
@endphp
<div class="mx-meta">
    @if($isLive ?? false)<span class="mx-live">{{ __('match_stats.live_badge') }}</span>@endif
    @if($h['event_title'])<span><b>{{ __('match_stats.h_event') }}:</b> {{ $h['event_title'] }}</span>@endif
    @if($h['stage'])<span><b>{{ __('match_stats.h_stage') }}:</b> {{ $h['stage'] }}</span>@endif
    @if($h['venue'])<span><b>{{ __('match_stats.h_venue') }}:</b> {{ $h['venue'] }}</span>@endif
    @if($h['minutes'])<span><b>{{ __('match_stats.h_duration') }}:</b> {{ __('match_stats.minutes_short', ['n' => $h['minutes']]) }}</span>@endif
    @if($h['scheduled'])<span>{{ $h['scheduled']->copy()->setTimezone($tz)->format('d.m.Y H:i') }}</span>@endif
</div>
<div class="mx-head">
    <div class="mx-head-team mx-head-team--home {{ $homeWon ? 'is-winner' : '' }}">{{ $teams['home']['name'] }}</div>
    <div class="mx-head-score">
        <div class="mx-head-score-main">{{ $match->setsScore() }}</div>
        <div class="mx-head-score-sets">{{ $setScores }}</div>
    </div>
    <div class="mx-head-team mx-head-team--away {{ $awayWon ? 'is-winner' : '' }}">{{ $teams['away']['name'] }}</div>
</div>
