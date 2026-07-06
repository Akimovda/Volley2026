@php
/**
 * Красивый экран статистики матча (в стиле myvolley.ru, наш дизайн).
 *
 * @var array $statsData ['home' => [...], 'away' => [...], 'sets_count' => int, 'has_stats' => bool]
 * @var \App\Models\TournamentMatch $match
 * @var \App\Models\TournamentStage|null $stage
 * @var \App\Models\Event|null $event
 */

$getVal = fn($totals, $f) => is_array($totals) ? ($totals[$f] ?? 0) : ($totals->$f ?? 0);

$posAbbr = [
    'setter'   => __('tournaments.pos_abbr_setter'),
    'outside'  => __('tournaments.pos_abbr_outside'),
    'opposite' => __('tournaments.pos_abbr_opposite'),
    'middle'   => __('tournaments.pos_abbr_middle'),
    'libero'   => __('tournaments.pos_abbr_libero'),
];

$teamInitials = function (?string $name): string {
    $name = trim((string) $name);
    if ($name === '') return '?';
    $parts = preg_split('/\s+/u', $name);
    if (count($parts) >= 2) {
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return mb_strtoupper(mb_substr($name, 0, 2));
};

$hasRealAvatar = function (?\App\Models\User $u): bool {
    if (!$u || !$u->avatar_media_id) return false;
    $media = $u->getMedia('photos')->firstWhere('id', $u->avatar_media_id);
    return $media && $media->hasGeneratedConversion('thumb');
};

$resolvePosition = function ($member) use ($posAbbr) {
    if (!$member) return null;
    $code = $member->position_code ?: null;
    if (!$code && in_array($member->role_code, ['outside', 'opposite', 'middle', 'setter', 'libero'], true)) {
        $code = $member->role_code;
    }
    return $code ? ($posAbbr[$code] ?? null) : null;
};

$homeTeam = $match->teamHome;
$awayTeam = $match->teamAway;
$homeMembers = $homeTeam?->members->keyBy('user_id') ?? collect();
$awayMembers = $awayTeam?->members->keyBy('user_id') ?? collect();

$enrichSide = function (array $rows, $membersByUser) use ($getVal, $resolvePosition) {
    $out = [];
    foreach ($rows as $row) {
        $member = $membersByUser->get($row['user_id']);
        $user = $member?->user ?: \App\Models\User::find($row['user_id']);
        $out[] = [
            'user_id'       => $row['user_id'],
            'user_name'     => $row['user_name'],
            'user'          => $user,
            'badge'         => $resolvePosition($member),
            'is_reserve'    => $member?->role_code === 'reserve',
            'is_libero'     => $member?->position_code === 'libero',
            'attack'        => (int) $getVal($row['totals'], 'kills'),
            'block'         => (int) $getVal($row['totals'], 'blocks'),
            'serve'         => (int) $getVal($row['totals'], 'aces'),
            'serve_errors'  => (int) $getVal($row['totals'], 'serve_errors'),
            'errors'        => (int) $getVal($row['totals'], 'attack_errors')
                              + (int) $getVal($row['totals'], 'block_errors')
                              + (int) $getVal($row['totals'], 'reception_errors'),
            'points'        => (int) $getVal($row['totals'], 'points_scored'),
        ];
    }
    usort($out, fn($a, $b) => $b['points'] <=> $a['points'] ?: $b['attack'] <=> $a['attack']);
    return $out;
};

$homePlayers = $enrichSide($statsData['home'] ?? [], $homeMembers);
$awayPlayers = $enrichSide($statsData['away'] ?? [], $awayMembers);

$homeHero = $homePlayers[0] ?? null;
$awayHero = $awayPlayers[0] ?? null;

$sumField = fn(array $players, string $f) => array_sum(array_column($players, $f));

$bars = [
    ['label' => 'Атака',      'error' => false, 'home' => $sumField($homePlayers, 'attack'),       'away' => $sumField($awayPlayers, 'attack')],
    ['label' => 'Блок',       'error' => false, 'home' => $sumField($homePlayers, 'block'),        'away' => $sumField($awayPlayers, 'block')],
    ['label' => 'Подача',     'error' => false, 'home' => $sumField($homePlayers, 'serve'),        'away' => $sumField($awayPlayers, 'serve')],
    ['label' => 'Ош. подачи', 'error' => true,  'home' => $sumField($homePlayers, 'serve_errors'), 'away' => $sumField($awayPlayers, 'serve_errors')],
    ['label' => 'Ошибки',     'error' => true,  'home' => $sumField($homePlayers, 'errors'),       'away' => $sumField($awayPlayers, 'errors')],
];

$splitSections = function (array $players): array {
    return [
        'main'    => array_values(array_filter($players, fn($p) => !$p['is_reserve'] && !$p['is_libero'])),
        'libero'  => array_values(array_filter($players, fn($p) => $p['is_libero'] && !$p['is_reserve'])),
        'reserve' => array_values(array_filter($players, fn($p) => $p['is_reserve'])),
    ];
};
$homeSections = $splitSections($homePlayers);
$awaySections = $splitSections($awayPlayers);
$sectionLabels = ['main' => null, 'libero' => 'Либеро', 'reserve' => 'Запасные'];

$tz = $event->timezone ?? 'Europe/Moscow';

$setsRendered = [];
if ($match->score_home && $match->score_away) {
    foreach ($match->score_home as $i => $h) {
        $a = $match->score_away[$i] ?? 0;
        $setsRendered[] = ['home' => $h, 'away' => $a, 'home_win' => $h > $a];
    }
}
@endphp

<div class="ms-wrap">

    {{-- Блок 1: хедер матча --}}
    <div class="ms-header">
        @if($stage ?? null)
        <div class="ms-header-meta">{{ $stage->name }}</div>
        @endif
        @if($match->scheduled_at)
        <div class="ms-header-meta ms-header-meta--right">{{ $match->scheduled_at->setTimezone($tz)->format('d.m.Y H:i') }}</div>
        @endif

        <div class="ms-header-row">
            <div class="ms-header-team">
                @if($homeTeam?->captain && $hasRealAvatar($homeTeam->captain))
                <img src="{{ $homeTeam->captain->profile_photo_url }}" class="ms-captain-avatar" alt="" loading="lazy">
                @else
                <div class="ms-captain-avatar ms-captain-avatar--fallback ms-captain-avatar--home">{{ $teamInitials($homeTeam->name ?? '?') }}</div>
                @endif
                <span class="ms-header-team-name">{{ $homeTeam->name ?? '?' }}</span>
            </div>

            <div class="ms-header-score">
                <div class="ms-header-score-main">{{ $match->setsScore() }}</div>
                @if(!empty($setsRendered))
                <div class="ms-header-score-sets">
                    @foreach($setsRendered as $i => $s)
                    @if($i > 0)<span class="ms-set-sep">·</span>@endif
                    <span class="{{ $s['home_win'] ? '' : 'ms-set-dim' }}">{{ $s['home'] }}</span>:<span class="{{ !$s['home_win'] ? '' : 'ms-set-dim' }}">{{ $s['away'] }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="ms-header-team ms-header-team--away">
                <span class="ms-header-team-name">{{ $awayTeam->name ?? '?' }}</span>
                @if($awayTeam?->captain && $hasRealAvatar($awayTeam->captain))
                <img src="{{ $awayTeam->captain->profile_photo_url }}" class="ms-captain-avatar" alt="" loading="lazy">
                @else
                <div class="ms-captain-avatar ms-captain-avatar--fallback ms-captain-avatar--away">{{ $teamInitials($awayTeam->name ?? '?') }}</div>
                @endif
            </div>
        </div>
    </div>

    @if(!$homePlayers && !$awayPlayers)
    <div class="f-13" style="opacity:.5;text-align:center;padding:20px 0">{{ __('tournaments.pub_no_finished_matches') }}</div>
    @else

    {{-- Блок 2: герои матча --}}
    <div class="ms-heroes">
        @foreach(['home' => $homeHero, 'away' => $awayHero] as $side => $hero)
        <div class="ms-hero-card ms-hero-card--{{ $side }}">
            @if($hero)
            <div class="ms-hero-avatar-wrap">
                <img src="{{ $hero['user']?->profile_photo_url }}" class="ms-hero-avatar" alt="" loading="lazy">
                @if($hero['badge'])
                <div class="ms-hero-badge ms-hero-badge--{{ $side }}">{{ $hero['badge'] }}</div>
                @endif
            </div>
            <div class="ms-hero-body">
                <div class="ms-hero-name">{{ $hero['user_name'] }}</div>
                <div class="ms-hero-points ms-hero-points--{{ $side }}">{{ $hero['points'] }}</div>
                <div class="ms-hero-breakdown">
                    <span><span class="ms-hero-dot ms-hero-dot--attack"></span>{{ $hero['attack'] }} Атака</span>
                    <span><span class="ms-hero-dot ms-hero-dot--block"></span>{{ $hero['block'] }} Блок</span>
                    <span><span class="ms-hero-dot ms-hero-dot--serve"></span>{{ $hero['serve'] }} Подача</span>
                </div>
            </div>
            @else
            <div class="f-13" style="opacity:.4">—</div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- Блок 3: сравнительные бары --}}
    <div class="ms-bars">
        @foreach($bars as $bar)
        @php $max = max($bar['home'], $bar['away'], 1); @endphp
        <div class="ms-bar-row">
            <div class="ms-bar-value ms-bar-value--home">{{ $bar['home'] }}</div>
            <div class="ms-bar-track ms-bar-track--home">
                <div class="ms-bar-fill {{ $bar['error'] ? 'ms-bar-fill--error' : 'ms-bar-fill--home' }}" style="width:{{ round($bar['home'] / $max * 100) }}%"></div>
            </div>
            <div class="ms-bar-label">{{ $bar['label'] }}</div>
            <div class="ms-bar-track ms-bar-track--away">
                <div class="ms-bar-fill {{ $bar['error'] ? 'ms-bar-fill--error' : 'ms-bar-fill--away' }}" style="width:{{ round($bar['away'] / $max * 100) }}%"></div>
            </div>
            <div class="ms-bar-value ms-bar-value--away">{{ $bar['away'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Блок 4: таблицы игроков --}}
    <div class="ms-tables">
        @foreach(['home' => ['team' => $homeTeam, 'sections' => $homeSections], 'away' => ['team' => $awayTeam, 'sections' => $awaySections]] as $side => $d)
        @php $nonEmpty = count(array_filter($d['sections'], fn($s) => count($s) > 0)); @endphp
        <div class="ms-team-table ms-team-table--{{ $side }}">
            <div class="ms-team-table-title ms-team-table-title--{{ $side }}">{{ $d['team']->name ?? '?' }}</div>
            <table class="ms-ptable">
                <thead>
                    <tr>
                        <th>{{ __('tournaments.stats_col_player') }}</th>
                        <th>Всего</th>
                        <th>Атака</th>
                        <th>Блок</th>
                        <th>Подача</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($d['sections'] as $secKey => $players)
                    @continue(empty($players))
                    @if($nonEmpty > 1 && $sectionLabels[$secKey])
                    <tr><td colspan="5" class="ms-section-label">{{ $sectionLabels[$secKey] }}</td></tr>
                    @endif
                    @foreach($players as $p)
                    <tr class="{{ $p['points'] === 0 && $p['attack'] === 0 && $p['block'] === 0 && $p['serve'] === 0 ? 'ms-row--muted' : '' }}">
                        <td class="ms-td-player">
                            <div class="ms-player-cell">
                                <img src="{{ $p['user']?->profile_photo_url }}" class="ms-player-avatar-mini" alt="" loading="lazy">
                                @if($p['badge'])<span class="ms-badge">{{ $p['badge'] }}</span>@endif
                                <a href="{{ route('users.show', $p['user_id']) }}" class="blink ms-player-name">{{ $p['user_name'] }}</a>
                            </div>
                        </td>
                        <td class="ms-points-cell">{{ $p['points'] }}</td>
                        <td>{{ $p['attack'] }}</td>
                        <td>{{ $p['block'] }}</td>
                        <td>{{ $p['serve'] }}</td>
                    </tr>
                    @endforeach
                    @endforeach
                    @if(empty($d['sections']['main']) && empty($d['sections']['libero']) && empty($d['sections']['reserve']))
                    <tr><td colspan="5" style="text-align:center;opacity:.5">—</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
        @endforeach
    </div>

    @endif
</div>
