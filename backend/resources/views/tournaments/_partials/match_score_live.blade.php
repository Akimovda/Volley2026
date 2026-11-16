{{-- Текущие очки идущего матча табло-плитками + «Сет N · по сетам a:b». $m (TournamentMatch, live). Обновляется скриптом по tournament.tv.live (id pub-pts-{id}). --}}
@php
	$__st = null;
	try { $__st = app(\App\Services\MatchRallyService::class)->computeMatchState($m); } catch (\Throwable $e) { $__st = null; }
	$__no  = $__st && !empty($__st['sets']) ? (int) array_key_last($__st['sets']) : 1;
	$__inf = $__st['sets'][$__no] ?? null;
	$__sh  = (int) ($__st['homeWins'] ?? $m->sets_home ?? 0);
	$__sa  = (int) ($__st['awayWins'] ?? $m->sets_away ?? 0);
@endphp
@include('tournaments._partials.flip_score', ['home' => (int) ($__inf['home'] ?? 0), 'away' => (int) ($__inf['away'] ?? 0), 'size' => 'sm', 'id' => 'pub-pts-' . $m->id])
<div class="f-11 b-400" style="opacity:.7;margin-top:3px"><span id="pub-setlbl-{{ $m->id }}">{{ __('tournaments.rally_set_n', ['n' => $__no]) }}</span> · {{ __('tournaments.score_by_sets') }} {{ $__sh }}:{{ $__sa }}</div>
