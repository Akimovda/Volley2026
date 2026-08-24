{{--
	Обёртка над match_progress.blade.php — общая для первичной отрисовки страницы
	(tournaments/public/show.blade.php) и live-фрагмента (TournamentPublicController::
	matchProgressFragment(), запрашивается JS по WebSocket-сигналу rally.updated).
	Один источник разметки — исключает расхождение между live-обновлением и обычной
	загрузкой страницы.

	@var array $matchProgress ['has_progress' => bool, 'sets' => [...]]
	@var \App\Models\TournamentMatch $match
	@var \App\Models\Event $event
--}}
@if(!empty($matchProgress['has_progress']))
@include('tournaments._partials.match_progress', ['progress' => $matchProgress, 'match' => $match, 'event' => $event])
@else
<div class="rp-empty">{{ __('tournaments.pub_match_progress_not_tracked') }}</div>
@endif
