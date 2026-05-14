@php
$sortedStandings = $group->standings->sortBy('rank');
$teamIds         = $sortedStandings->pluck('team_id')->values()->toArray();
$standingByTeam  = $sortedStandings->keyBy('team_id');

// Индекс матчей: [team_id][opponent_id] => match
$matchIdx = [];
foreach ($groupMatches as $match) {
    if ($match->team_home_id && $match->team_away_id) {
        $matchIdx[(int)$match->team_home_id][(int)$match->team_away_id] = $match;
        $matchIdx[(int)$match->team_away_id][(int)$match->team_home_id] = $match;
    }
}

$rankNumerals = [1=>'I', 2=>'II', 3=>'III', 4=>'IV', 5=>'V', 6=>'VI', 7=>'VII', 8=>'VIII'];
@endphp

<div style="overflow-x:auto">
    <table class="table" style="border-collapse:collapse;font-size:13px;min-width:100%">
        <thead>
            <tr style="border-bottom:2px solid rgba(128,128,128,.2)">
                <th class="p-1" style="text-align:center;width:28px">#</th>
                <th class="p-1" style="text-align:left;min-width:120px">{{ __('tournaments.standings_col_team') }}</th>
                @foreach($teamIds as $idx => $tid)
                <th class="p-1" style="text-align:center;width:50px;color:#6b7280">{{ $idx + 1 }}</th>
                @endforeach
                <th class="p-1" style="text-align:center;width:34px" title="{{ __('tournaments.crosstable_matches') }}">{{ __('tournaments.matches_short') }}</th>
                <th class="p-1" style="text-align:center;width:34px" title="{{ __('tournaments.crosstable_wins') }}">{{ __('tournaments.wins_short') }}</th>
                <th class="p-1" style="text-align:center;width:52px">{{ __('tournaments.sets_short') }}</th>
                <th class="p-1" style="text-align:center;width:44px">{{ __('tournaments.points_short') }}</th>
                <th class="p-1" style="text-align:center;width:56px">{{ __('tournaments.balls_ratio') }}</th>
                <th class="p-1" style="text-align:center;width:46px">{{ __('tournaments.place_short') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($teamIds as $rowIdx => $teamId)
            @php
            $standing   = $standingByTeam[$teamId] ?? null;
            $isOutsider = in_array((int)$teamId, $groupOutsiders, true);
            $cleanPs    = $groupClean[$teamId]['points_scored']   ?? $standing?->points_scored   ?? 0;
            $cleanPc    = $groupClean[$teamId]['points_conceded'] ?? $standing?->points_conceded ?? 0;
            $rank       = $standing?->rank;
            $rankLabel  = $rank ? ($rankNumerals[$rank] ?? $rank) : '—';
            $rankStyle  = match((int)$rank) {
                1 => 'background:rgba(212,175,55,.18);color:#a07c10;font-weight:700',
                2 => 'background:rgba(180,180,180,.18);color:#555;font-weight:700',
                3 => 'background:rgba(176,141,87,.18);color:#8b5e1a;font-weight:700',
                default => '',
            };
            @endphp
            <tr style="{{ $isOutsider ? 'opacity:0.65' : '' }}">
                <td style="text-align:center;color:#6b7280;padding:4px">{{ $rowIdx + 1 }}</td>
                <td class="p-1">
                    <div class="b-600 cd">{{ $standing?->team?->name ?? '—' }}@if($isOutsider) <span class="f-12" style="font-weight:400"> · аут.</span>@endif</div>
                    @if($standing?->team?->members?->count())
                    <div class="f-12" style="color:#6b7280">{{ $standing->team->members->map(fn($m) => $m->user->last_name ?? '?')->implode(' / ') }}</div>
                    @endif
                </td>
                @foreach($teamIds as $colIdx => $oppId)
                @php
                if ((int)$teamId === (int)$oppId) {
                    $cellText  = '×';
                    $cellStyle = 'background:rgba(0,0,0,.06)';
                } else {
                    $m = $matchIdx[(int)$teamId][(int)$oppId] ?? null;
                    if ($m && $m->isCompleted()) {
                        $isHome   = (int)$m->team_home_id === (int)$teamId;
                        $sw       = $isHome ? $m->sets_home : $m->sets_away;
                        $sl       = $isHome ? $m->sets_away : $m->sets_home;
                        $cellText  = $sw . ':' . $sl;
                        $cellStyle = $sw > $sl
                            ? 'background:rgba(16,185,129,.12);color:#065f46;font-weight:600'
                            : 'background:rgba(239,68,68,.1);color:#991b1b';
                    } else {
                        $cellText  = '—';
                        $cellStyle = 'background:rgba(0,0,0,.02);color:#9ca3af';
                    }
                }
                @endphp
                <td style="text-align:center;padding:4px 2px;{{ $cellStyle }}">{{ $cellText }}</td>
                @endforeach
                <td style="text-align:center;padding:4px"><span class="b-600 alert-info pt-05 pb-05 p-1">{{ $standing?->played ?? '—' }}</span></td>
                <td style="text-align:center;padding:4px"><span class="b-600 alert-success pt-05 pb-05 p-1">{{ $standing?->wins ?? '—' }}</span></td>
                <td style="text-align:center;padding:4px">{{ $standing ? ($standing->sets_won . ':' . $standing->sets_lost) : '—' }}</td>
                <td class="b-600" style="text-align:center;padding:4px">{{ $standing?->rating_points ?? '—' }}</td>
                <td style="text-align:center;padding:4px;font-size:12px;color:#6b7280">{{ $standing ? ($cleanPs . ':' . $cleanPc) : '—' }}</td>
                <td style="text-align:center;padding:4px 2px;border-radius:4px;{{ $rankStyle }}">{{ $rankLabel }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
