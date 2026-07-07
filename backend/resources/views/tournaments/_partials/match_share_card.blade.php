@php
/**
 * Карточка матча для шаринга (1200x630 PNG через Browsershot).
 * Полностью самодостаточный HTML — никаких внешних ресурсов (аватары инлайнятся
 * base64, стили инлайн) — Browsershot рендерит переданную строку без сети.
 *
 * @var \App\Models\TournamentMatch $match
 */

$teamInitials = function (?string $name): string {
    $name = trim((string) $name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/u', $name);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
};

$avatarDataUri = function (?\App\Models\User $captain): ?string {
    if (!$captain || !$captain->avatar_media_id) return null;
    $media = $captain->getMedia('photos')->firstWhere('id', $captain->avatar_media_id);
    if (!$media || !$media->hasGeneratedConversion('thumb')) return null;
    $path = $media->getPath('thumb');
    if (!is_file($path)) return null;
    $mime = mime_content_type($path) ?: 'image/jpeg';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
};

$captainName = function (?\App\Models\User $captain): ?string {
    if (!$captain) return null;
    $name = trim(($captain->last_name ?? '') . ' ' . ($captain->first_name ?? ''));
    return $name !== '' ? $name : null;
};

$homeTeam = $match->teamHome;
$awayTeam = $match->teamAway;
$homeAvatar = $avatarDataUri($homeTeam?->captain);
$awayAvatar = $avatarDataUri($awayTeam?->captain);
$homeCaptainName = $captainName($homeTeam?->captain);
$awayCaptainName = $captainName($awayTeam?->captain);

$homeWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_home_id;
$awayWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_away_id;

$setsRendered = [];
if ($match->score_home && $match->score_away) {
    foreach ($match->score_home as $i => $h) {
        $a = $match->score_away[$i] ?? 0;
        $setsRendered[] = ['home' => $h, 'away' => $a, 'home_win' => $h > $a];
    }
}

// Логотип: полный SVG (мяч + текст VolleyPlay), инлайним содержимое файла —
// Browsershot рендерит без сети, внешние <img src="/assets/..."> не подтянутся.
$logoPath = public_path('assets/logo_long.svg');
$logoSvg = is_file($logoPath)
    ? preg_replace('/^<\?xml[^>]*\?>\s*/', '', file_get_contents($logoPath))
    : '';

// Локация и дата матча: берём occurrence турнира (если есть), иначе — само событие
$stage = $match->stage;
$event = $stage?->event;
$occurrence = $stage?->occurrence_id
    ? \App\Models\EventOccurrence::with('location')->find($stage->occurrence_id)
    : null;

$location = $occurrence?->location ?? $event?->location;
$matchDate = $occurrence?->starts_at_local;
if (!$matchDate && $event?->starts_at) {
    $matchDate = \App\Support\DateTime::utcToLocal($event->getRawOriginal('starts_at'), $event->timezone ?: 'UTC');
}
if ($matchDate) {
    \Carbon\Carbon::setLocale('ru');
    $matchDateFormatted = $matchDate->isoFormat('D MMMM YYYY') . ' г.';
} else {
    $matchDateFormatted = null;
}

$locationLine = $location ? trim($location->name . ($location->address ? ', ' . $location->address : '')) : null;
$metaLine = trim(implode(' · ', array_filter([$locationLine, $matchDateFormatted])));
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; }
    body {
        width: 1200px;
        height: 630px;
        background: linear-gradient(135deg, #0f1729 0%, #1a2744 100%);
        color: #fff;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        position: relative;
        overflow: hidden;
    }
    .header { position: absolute; top: 40px; left: 56px; right: 56px; display: flex; align-items: center; justify-content: space-between; }
    .logo svg { height: 52px; width: auto; display: block; }
    .stage { font-size: 22px; opacity: .55; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; text-align: right; }
    .meta { position: absolute; bottom: 40px; left: 50%; transform: translateX(-50%); font-size: 20px; opacity: .55; white-space: nowrap; }
    .row { display: flex; align-items: center; justify-content: center; gap: 70px; margin-top: 30px; }
    .team { display: flex; flex-direction: column; align-items: center; width: 340px; }
    .avatar { width: 128px; height: 128px; border-radius: 50%; object-fit: cover; border: 4px solid rgba(255, 255, 255, .15); }
    .avatar-fallback {
        width: 128px; height: 128px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 44px; font-weight: 800; color: #fff;
        border: 4px solid rgba(255, 255, 255, .15);
    }
    .avatar--home { background: #2967BA; }
    .avatar--away { background: #E7612F; }
    .team-name {
        margin-top: 22px;
        font-size: 32px;
        font-weight: 500;
        text-align: center;
        line-height: 1.25;
        max-width: 340px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .team-name--winner { font-weight: 700; }
    .captain-name { margin-top: 6px; font-size: 18px; opacity: .6; text-align: center; max-width: 340px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .score-block { display: flex; flex-direction: column; align-items: center; width: 260px; }
    .score { font-size: 100px; font-weight: 800; color: #4a9eff; line-height: 1; }
    .sets { margin-top: 18px; font-size: 32px; white-space: nowrap; }
    .sets .win { color: #ff8a5c; font-weight: 700; }
    .sets .dim { opacity: .5; font-weight: 400; }
    .sep { margin: 0 10px; opacity: .5; }
</style>
</head>
<body>
    <div class="header">
        <div class="logo">{!! $logoSvg !!}</div>
        @if($stage)
        <div class="stage">{{ $stage->name }}</div>
        @endif
    </div>

    <div class="row">
        <div class="team">
            @if($homeAvatar)
            <img src="{{ $homeAvatar }}" class="avatar" alt="">
            @else
            <div class="avatar-fallback avatar--home">{{ $teamInitials($homeTeam->name ?? '?') }}</div>
            @endif
            <div class="team-name{{ $homeWon ? ' team-name--winner' : '' }}">{{ $homeTeam->name ?? '?' }}</div>
            @if($homeCaptainName)
            <div class="captain-name">{{ $homeCaptainName }}</div>
            @endif
        </div>

        <div class="score-block">
            <div class="score">{{ $match->setsScore() }}</div>
            @if(!empty($setsRendered))
            <div class="sets">
                @foreach($setsRendered as $i => $s)
                @if($i > 0)<span class="sep">·</span>@endif
                <span class="{{ $s['home_win'] ? 'win' : 'dim' }}">{{ $s['home'] }}</span>:<span class="{{ !$s['home_win'] ? 'win' : 'dim' }}">{{ $s['away'] }}</span>
                @endforeach
            </div>
            @endif
        </div>

        <div class="team">
            @if($awayAvatar)
            <img src="{{ $awayAvatar }}" class="avatar" alt="">
            @else
            <div class="avatar-fallback avatar--away">{{ $teamInitials($awayTeam->name ?? '?') }}</div>
            @endif
            <div class="team-name{{ $awayWon ? ' team-name--winner' : '' }}">{{ $awayTeam->name ?? '?' }}</div>
            @if($awayCaptainName)
            <div class="captain-name">{{ $awayCaptainName }}</div>
            @endif
        </div>
    </div>

    @if($metaLine !== '')
    <div class="meta">{{ $metaLine }}</div>
    @endif
</body>
</html>
