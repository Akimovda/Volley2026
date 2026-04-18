@php
/**
 * Bracket-сетка (single elimination) — HTML/CSS рендеринг.
 * 
 * @var TournamentStage $stage
 * @var Collection $matches
 */

$totalRounds = $matches->max('round') ?? 0;
$matchesByRound = [];
for ($r = 1; $r <= $totalRounds; $r++) {
    $matchesByRound[$r] = $matches->where('round', $r)->sortBy('match_number')->values();
}

// Размеры
$matchW = 200;
$matchH = 56;
$gapV = 16;
$gapH = 60;
$roundW = $matchW + $gapH;
@endphp

<div style="overflow-x:auto;padding:10px 0">
    <div style="display:flex;gap:{{ $gapH }}px;align-items:flex-start;min-width:{{ $totalRounds * $roundW }}px">
        @for($r = 1; $r <= $totalRounds; $r++)
            @php
                $roundMatches = $matchesByRound[$r] ?? collect();
                $roundLabel = $r === $totalRounds ? 'Финал' : ($r === $totalRounds - 1 ? 'Полуфинал' : 'Раунд ' . $r);
                // Вертикальное смещение для визуального выравнивания bracket
                $verticalGap = $gapV * pow(2, $r - 1);
                $topPad = ($verticalGap - $gapV) / 2;
            @endphp
            <div style="flex-shrink:0;width:{{ $matchW }}px">
                <div class="f-12 b-600 mb-2" style="opacity:.5;text-align:center">{{ $roundLabel }}</div>
                <div style="display:flex;flex-direction:column;gap:{{ $verticalGap }}px;padding-top:{{ $topPad }}px">
                    @foreach($roundMatches as $m)
                        @php
                            $isCompleted = $m->status === 'completed';
                            $homeWin = $m->winner_team_id === $m->team_home_id;
                            $awayWin = $m->winner_team_id === $m->team_away_id;
                            $isBye = $isCompleted && empty($m->score_home);
                        @endphp
                        <div style="border:1px solid rgba(128,128,128,.2);border-radius:8px;overflow:hidden;{{ $isCompleted ? 'border-color:rgba(16,185,129,.3)' : '' }}">
                            {{-- Home --}}
                            <div class="d-flex" style="padding:6px 8px;{{ $homeWin ? 'background:rgba(16,185,129,.08)' : '' }};border-bottom:1px solid rgba(128,128,128,.15)">
                                <span class="f-13 {{ $homeWin ? 'b-700' : '' }}" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    {{ $m->teamHome->name ?? 'TBD' }}
                                </span>
                                @if($isCompleted && !$isBye)
                                    <span class="f-13 b-700" style="margin-left:8px">{{ $m->sets_home }}</span>
                                @endif
                            </div>
                            {{-- Away --}}
                            <div class="d-flex" style="padding:6px 8px;{{ $awayWin ? 'background:rgba(16,185,129,.08)' : '' }}">
                                <span class="f-13 {{ $awayWin ? 'b-700' : '' }}" style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                    {{ $m->teamAway->name ?? 'TBD' }}
                                </span>
                                @if($isCompleted && !$isBye)
                                    <span class="f-13 b-700" style="margin-left:8px">{{ $m->sets_away }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endfor
    </div>
</div>
