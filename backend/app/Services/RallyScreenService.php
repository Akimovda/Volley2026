<?php

namespace App\Services;

use App\Models\MatchRallyEvent;
use App\Models\MatchServeMark;
use App\Models\TournamentMatch;

/** Данные и HTML-фрагмент экрана поочкового ввода — общие для организатора и ведущего по токен-ссылке. */
class RallyScreenService
{
    public function __construct(private MatchRallyService $rally) {}

    /** Данные экрана — общие для полной страницы и для HTML-фрагмента без перезагрузки. */
    public function viewData(TournamentMatch $match, int $setNumber, array $urls): array
    {
        $stage = $match->stage;
        $event = $stage->event;
        $board = $this->rally->getBoard($match, $setNumber);
        $matchReady = $this->rally->isMatchReadyToFinalize($match);
        $lineup = app(MatchLineupView::class)->forRally($match, $setNumber, $board['decided']);
        $journal = app(MatchProgressService::class)->build($match->id, true)['sets'][$setNumber]['rallies'] ?? [];

        // Очки без игрока (только счёт) — для предупреждения перед записью итога
        $unattributed = (int) MatchRallyEvent::where('match_id', $match->id)->where('action_type', MatchRallyEvent::ACTION_UNATTRIBUTED)->count();

        $finalScore = $matchReady
            ? implode(', ', array_map(fn ($s) => $s[0] . ':' . $s[1], $this->rally->buildFinalSets($match)))
            : '';

        // «▶ Матч начался» — пока в матче нет очков; при учёте состава эту роль играет «Начать партию»
        $hasRallies = MatchRallyEvent::where('match_id', $match->id)->exists();
        $canMatchStart = empty($lineup['enabled']) && $match->status === TournamentMatch::STATUS_SCHEDULED && !$hasRallies;
        $canMatchUnstart = $match->status === TournamentMatch::STATUS_LIVE && !RallyInputService::hasPlay($match);

        // «Подаёт: [A] [B]» — только в текущей партии, пока в ней нет очков; при учёте состава подачу задаёт расстановка
        $setHasPoints = MatchRallyEvent::where('match_id', $match->id)->where('set_number', $setNumber)->exists();
        // Пляжка 2×2: порядок подачи (карточки игроков, «Подаёт первым»); тогда старая отметка «Подаёт: [A][B]» не нужна
        $serve = app(BeachServeOrderService::class)->forRally($match, $setNumber, (bool) $board['decided']);
        $canServeMark = empty($serve['enabled']) && !empty($urls['serve_mark']) && !$match->isCompleted() && $match->hasTeams()
            && !$setHasPoints && !$board['decided'] && empty($lineup['enabled'])
            && $setNumber === $this->rally->getActiveSetNumber($match);
        $serveMarkTeamId = $canServeMark
            ? MatchServeMark::where('match_id', $match->id)->where('set_number', $setNumber)->value('team_id')
            : null;

        return compact('event', 'match', 'stage', 'board', 'setNumber', 'matchReady', 'lineup', 'journal', 'urls', 'unattributed', 'finalScore', 'canMatchStart', 'canMatchUnstart', 'canServeMark', 'serveMarkTeamId', 'serve');
    }

    public function fragmentHtml(TournamentMatch $match, int $setNumber, array $urls): string
    {
        $match = $match->fresh(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'stage']);

        return view('tournaments._partials.rally_live', $this->viewData($match, $setNumber, $urls))->render();
    }
}
