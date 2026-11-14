{{-- Счёт матча по сетам табло-плитками (для списков матчей): $m (TournamentMatch). Не сыгранный матч — «vs». --}}
@if($m->isCompleted() && $m->teamHome && $m->teamAway)
@include('tournaments._partials.flip_score', ['home' => (int) $m->sets_home, 'away' => (int) $m->sets_away, 'digits' => 1, 'size' => 'sm'])
@if($m->detailedScore())
<div class="f-11 b-400" style="opacity:.6;margin-top:3px">{{ $m->detailedScore() }}</div>
@endif
@elseif($m->isLive() && $m->teamHome && $m->teamAway)
<span class="pub-live-badge"><x-ico name="dot-red" size="1em" /> {{ __('tournaments.pub_match_live') }}</span>
@include('tournaments._partials.match_score_live', ['m' => $m])
@else
<span style="opacity:.4">vs</span>
@endif
