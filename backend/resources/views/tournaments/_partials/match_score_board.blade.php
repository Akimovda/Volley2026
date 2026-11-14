{{-- Счёт матча по сетам табло-плитками (для списков матчей): $m (TournamentMatch). Не сыгранный матч — «vs». --}}
@if($m->isCompleted() && $m->teamHome && $m->teamAway)
@include('tournaments._partials.flip_score', ['home' => (int) $m->sets_home, 'away' => (int) $m->sets_away, 'digits' => 1, 'size' => 'sm'])
@if($m->detailedScore())
<div class="f-11 b-400" style="opacity:.6;margin-top:3px">{{ $m->detailedScore() }}</div>
@endif
@else
<span style="opacity:.4">vs</span>
@endif
