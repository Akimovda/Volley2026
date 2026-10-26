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

// Учёт состава: у матчей с учётом в итоговой строке игрока есть played (true/false), у остальных — NULL
$luTracked = false;
foreach (array_merge($statsData['home'] ?? [], $statsData['away'] ?? []) as $r0) {
    if (is_object($r0['totals'] ?? null) && $r0['totals']->played !== null) { $luTracked = true; break; }
}
$luSum = $luTracked ? app(\App\Services\MatchLineupService::class)->participationSummary($match->id) : null;

$enrichSide = function (array $rows, $membersByUser) use ($getVal, $resolvePosition, $luTracked, $luSum, $posAbbr) {
    $out = [];
    foreach ($rows as $row) {
        $member = $membersByUser->get($row['user_id']);
        $user = $member?->user ?: \App\Models\User::find($row['user_id']);
        $tot = $row['totals'] ?? null;
        $played = (is_object($tot) && $luTracked) ? $tot->played : null;
        $badges = [];
        if ($luTracked && is_object($tot) && is_array($tot->positions)) {
            foreach ($tot->positions as $pc) { if (isset($posAbbr[$pc])) { $badges[] = $posAbbr[$pc]; } }
        } elseif ($resolvePosition($member)) {
            $badges[] = $resolvePosition($member);
        }
        $uid = (int) $row['user_id'];
        $out[] = [
            'user_id'       => $row['user_id'],
            'user_name'     => $row['user_name'],
            'user'          => $user,
            'badge'         => $badges[0] ?? null,
            'badges'        => $badges,
            'played'        => $played,
            'starter'       => $luSum ? in_array($uid, $luSum['starters'], true) : false,
            'libero_only'   => $luSum ? (in_array($uid, $luSum['liberos'], true) && !in_array($uid, $luSum['starters'], true)) : false,
            'subbed'        => $luSum ? in_array($uid, $luSum['subbed_in'], true) : false,
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

$splitSections = function (array $players) use ($luTracked): array {
    if ($luTracked) {
        // По факту игры: основа / либеро / запасные, которые выходили; не выходившие — отдельной строкой
        $played = array_filter($players, fn($p) => $p['played']);
        return [
            'main'    => array_values(array_filter($played, fn($p) => $p['starter'] || (!$p['libero_only'] && !$p['subbed']))),
            'libero'  => array_values(array_filter($played, fn($p) => $p['libero_only'])),
            'reserve' => array_values(array_filter($played, fn($p) => !$p['starter'] && !$p['libero_only'] && $p['subbed'])),
            'idle'    => array_values(array_filter($players, fn($p) => $p['played'] === false)),
        ];
    }
    return [
        'main'    => array_values(array_filter($players, fn($p) => !$p['is_reserve'] && !$p['is_libero'])),
        'libero'  => array_values(array_filter($players, fn($p) => $p['is_libero'] && !$p['is_reserve'])),
        'reserve' => array_values(array_filter($players, fn($p) => $p['is_reserve'])),
        'idle'    => [],
    ];
};
$homeSections = $splitSections($homePlayers);
$awaySections = $splitSections($awayPlayers);
$sectionLabels = [
    'main' => null,
    'libero' => 'Либеро',
    'reserve' => $luTracked ? __('tournaments.lineup_stat_subs_played') : 'Запасные',
];

$tz = $event->timezone ?? 'Europe/Moscow';

$setsRendered = [];
if ($match->score_home && $match->score_away) {
    foreach ($match->score_home as $i => $h) {
        $a = $match->score_away[$i] ?? 0;
        $setsRendered[] = ['home' => $h, 'away' => $a, 'home_win' => $h > $a];
    }
}

$homeWonMatch = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_home_id;
$awayWonMatch = $match->winner_team_id && (int) $match->winner_team_id === (int) $match->team_away_id;
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
        @if($match->isCompleted())
        <button type="button" class="ms-share-btn" onclick="msShareMatch({{ $match->id }}, {{ json_encode(($homeTeam->name ?? '?') . ' vs ' . ($awayTeam->name ?? '?')) }})">📤</button>
        @endif
        @if(\App\Models\MatchRallyEvent::where('match_id', $match->id)->exists())
        <a href="{{ route('match.stats', $match) }}" class="ms-stats-link" aria-label="{{ __('match_stats.stats_link_aria') }}" title="{{ __('match_stats.stats_link_aria') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="12" width="4.5" height="9" rx="1.2" fill="#2967BA"/><rect x="9.75" y="3" width="4.5" height="18" rx="1.2" fill="#E7612F"/><rect x="16.5" y="8" width="4.5" height="13" rx="1.2" fill="#2967BA" fill-opacity=".6"/></svg>
        </a>
        @endif

        <div class="ms-header-row">
            <div class="ms-header-team">
                @if($homeTeam?->captain && $hasRealAvatar($homeTeam->captain))
                <img src="{{ $homeTeam->captain->profile_photo_url }}" class="ms-captain-avatar" alt="" loading="lazy">
                @else
                <div class="ms-captain-avatar ms-captain-avatar--fallback ms-captain-avatar--home">{{ $teamInitials($homeTeam->name ?? '?') }}</div>
                @endif
                <span class="ms-header-team-name{{ $homeWonMatch ? ' ms-header-team-name--winner' : '' }}">{{ $homeTeam->name ?? '?' }}</span>
            </div>

            <div class="ms-header-score">
                <div class="ms-header-score-main">{{ $match->setsScore() }}</div>
                @if(!empty($setsRendered))
                <div class="ms-header-score-sets">
                    @foreach($setsRendered as $i => $s)
                    @if($i > 0)<span class="ms-set-sep">·</span>@endif
                    <span class="{{ $s['home_win'] ? 'ms-set-win' : 'ms-set-dim' }}">{{ $s['home'] }}</span>:<span class="{{ !$s['home_win'] ? 'ms-set-win' : 'ms-set-dim' }}">{{ $s['away'] }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div class="ms-header-team ms-header-team--away">
                <span class="ms-header-team-name{{ $awayWonMatch ? ' ms-header-team-name--winner' : '' }}">{{ $awayTeam->name ?? '?' }}</span>
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
        @php $nonEmpty = count(array_filter(array_diff_key($d['sections'], ['idle' => 1]), fn($s) => count($s) > 0)); @endphp
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
                    @continue(empty($players) || $secKey === 'idle')
                    @if($nonEmpty > 1 && $sectionLabels[$secKey])
                    <tr><td colspan="5" class="ms-section-label">{{ $sectionLabels[$secKey] }}</td></tr>
                    @endif
                    @foreach($players as $p)
                    <tr class="{{ $p['points'] === 0 && $p['attack'] === 0 && $p['block'] === 0 && $p['serve'] === 0 ? 'ms-row--muted' : '' }}">
                        <td class="ms-td-player">
                            <div class="ms-player-cell">
                                <img src="{{ $p['user']?->profile_photo_url }}" class="ms-player-avatar-mini" alt="" loading="lazy">
                                @foreach($p['badges'] as $bdg)<span class="ms-badge">{{ $bdg }}</span>@endforeach
                                <a href="{{ route('users.show', $p['user_id']) }}" class="blink ms-player-name">{{ $p['user_name'] }}</a>
                                @if($p['subbed'])<span class="lu-badge-sub" title="{{ __('tournaments.lineup_stat_subbed_in') }}">@include('tournaments._partials.icon_sub', ['kind' => 'sub', 'size' => 14])</span>@endif
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
            @if(!empty($d['sections']['idle']))
            <div class="ms-notplayed">{{ __('tournaments.lineup_stat_not_played', ['names' => implode(', ', array_column($d['sections']['idle'], 'user_name'))]) }}</div>
            @endif
        </div>
        @endforeach
    </div>

    @endif
</div>

@once
<script>
    async function msShareMatch(matchId, title) {
        var url = '/tournament-matches/' + matchId + '/share-card';
        try {
            var resp = await fetch(url);
            var blob = await resp.blob();
            var file = new File([blob], 'match.png', { type: 'image/png' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                await navigator.share({ files: [file], title: title });
                return;
            }
        } catch (e) { console.warn('[share]', e); }
        window.open(url, '_blank');
    }
</script>
@endonce
