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

$homeTeam = $match->teamHome;
$awayTeam = $match->teamAway;
$homeAvatar = $avatarDataUri($homeTeam?->captain);
$awayAvatar = $avatarDataUri($awayTeam?->captain);

$homeWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_home_id;
$awayWon = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_away_id;

$setsRendered = [];
if ($match->score_home && $match->score_away) {
    foreach ($match->score_home as $i => $h) {
        $a = $match->score_away[$i] ?? 0;
        $setsRendered[] = ['home' => $h, 'away' => $a, 'home_win' => $h > $a];
    }
}
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
    .logo { position: absolute; top: 44px; left: 50%; transform: translateX(-50%); }
    .logo svg { width: 200px; height: auto; display: block; }
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
    .score-block { display: flex; flex-direction: column; align-items: center; width: 260px; }
    .score { font-size: 100px; font-weight: 800; color: #4a9eff; line-height: 1; }
    .sets { margin-top: 18px; font-size: 32px; white-space: nowrap; }
    .sets .win { color: #ff8a5c; font-weight: 700; }
    .sets .dim { opacity: .5; font-weight: 400; }
    .sep { margin: 0 10px; opacity: .5; }
    .stage { position: absolute; bottom: 44px; left: 50%; transform: translateX(-50%); font-size: 22px; opacity: .55; text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
</style>
</head>
<body>
    <div class="logo">
        <svg xmlns="http://www.w3.org/2000/svg" width="297px" height="88px" viewBox="0 0 297 88" version="1.1">
            <defs><linearGradient x1="73.168378%" y1="4.69814595%" x2="73.168378%" y2="105.568873%" id="shareLogoGrad"><stop stop-color="#FFB171" offset="0%"></stop><stop stop-color="#E7612F" offset="100%"></stop></linearGradient></defs>
            <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                <g transform="translate(0.529457, 0.168880)">
                    <path d="M20.4616712,21.2653614 C43.5995828,11.4565515 63.9873842,14.9226858 77.3336873,19.9599868 L77.7359609,20.1131011 L79.9411208,20.957837 C81.612304,25.1583828 82.6757033,29.479172 83.1255988,33.8256509 C73.2484161,29.7775841 55.6173261,25.8306724 35.2544697,35.7139277 C28.7394221,31.3463466 23.9393424,26.3940322 20.4616712,21.2653614 Z M15.9816651,83.2785026 C25.6226037,75.5385008 36.1868466,62.3236963 37.7048912,41.2399692 C45.8708331,37.2453017 53.5149691,35.732692 60.3198675,35.6202698 C56.532537,60.6769039 42.9725819,76.5578623 31.8465358,85.6089348 L31.5101404,85.8811215 L30.179405,86.94998 C25.2859047,86.4551262 20.5042122,85.2107275 15.9816651,83.2785026 Z M25.8189366,62.0328424 C2.04683068,43.8434466 -0.223921947,18.8783035 0.015479381,8.40402649 L0.101491241,5.21520633 C1.90384389,3.34284074 3.86809204,1.59368609 5.99889661,-7.10542736e-15 C7.53094883,10.6345296 13.0290472,28.0658987 31.684203,40.6549873 C31.0946364,49.1383987 28.8821205,56.189305 25.8189366,62.0328424 Z" fill="url(#shareLogoGrad)"></path>
                </g>
            </g>
        </svg>
    </div>

    <div class="row">
        <div class="team">
            @if($homeAvatar)
            <img src="{{ $homeAvatar }}" class="avatar" alt="">
            @else
            <div class="avatar-fallback avatar--home">{{ $teamInitials($homeTeam->name ?? '?') }}</div>
            @endif
            <div class="team-name{{ $homeWon ? ' team-name--winner' : '' }}">{{ $homeTeam->name ?? '?' }}</div>
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
        </div>
    </div>

    @if($match->stage)
    <div class="stage">{{ $match->stage->name }}</div>
    @endif
</body>
</html>
