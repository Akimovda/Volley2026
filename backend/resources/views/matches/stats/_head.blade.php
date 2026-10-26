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
@php
    $isPrint = $print ?? false;
    $match->loadMissing(['teamHome.captain', 'teamAway.captain']);
    $initials = function (?string $name): string {
        $parts = preg_split('/\s+/u', trim((string) $name));
        return count($parts) >= 2 ? mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1)) : mb_strtoupper(mb_substr((string) $name, 0, 2));
    };
    // аватар капитана — только настоящий (с миниатюрой); в PDF картинка вшивается base64 (без сети)
    $captainAvatar = function (?\App\Models\User $c) use ($isPrint): ?string {
        if (!$c || !$c->avatar_media_id) return null;
        $media = $c->getMedia('photos')->firstWhere('id', $c->avatar_media_id);
        if (!$media || !$media->hasGeneratedConversion('thumb')) return null;
        if (!$isPrint) return $c->profile_photo_url;
        $path = $media->getPath('thumb');
        return is_file($path) ? 'data:' . (mime_content_type($path) ?: 'image/jpeg') . ';base64,' . base64_encode(file_get_contents($path)) : null;
    };
    $side = [];
    foreach (['home' => $match->teamHome, 'away' => $match->teamAway] as $k => $t) {
        $c = $t?->captain;
        $side[$k] = [
            'avatar'  => $captainAvatar($c),
            'captain' => $c ? trim(($c->last_name ?? '') . ' ' . ($c->first_name ?? '')) : '',
        ];
    }
@endphp
<div class="mx-head">
    @foreach(['home' => $homeWon, 'away' => $awayWon] as $k => $won)
    @if($k === 'away')
    <div class="mx-head-score">
        <div class="mx-head-score-main">{{ $match->setsScore() }}</div>
        <div class="mx-head-score-sets">{{ $setScores }}</div>
    </div>
    @endif
    <div class="mx-head-team mx-head-team--{{ $k }}">
        @if($side[$k]['avatar'])
        <img src="{{ $side[$k]['avatar'] }}" class="mx-head-ava" alt="">
        @else
        <div class="mx-head-ava mx-head-ava--{{ $k }}">{{ $initials($teams[$k]['name']) }}</div>
        @endif
        <div class="mx-head-team-txt">
            <div class="mx-head-team-name {{ $won ? 'is-winner' : '' }}">{{ $teams[$k]['name'] }}</div>
            @if($side[$k]['captain'])<div class="mx-head-captain">{{ $side[$k]['captain'] }}</div>@endif
        </div>
    </div>
    @endforeach
</div>
