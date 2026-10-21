<?php

namespace App\Http\Controllers;

use App\Models\BroadcastLink;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use App\Models\User;
use App\Services\Broadcast\CourtStateService;
use App\Services\MatchFinalizeService;
use App\Services\MatchRallyService;
use App\Services\RallyInputService;
use App\Services\RallyScreenService;
use App\Support\RallyActor;
use App\Support\RallyUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use InvalidArgumentException;

/**
 * Ведущий матча по токен-ссылке площадки (/s/{token}), без аккаунта (анонимная ссылка)
 * или под приглашённым пользователем. Видит только матчи СВОЕЙ площадки. Экран и действия —
 * тот же код, что у организатора (rally_screen, RallyInputService, MatchFinalizeService).
 */
class ScorerController extends Controller
{
    public function __construct(
        private CourtStateService $courtState,
        private RallyScreenService $screen,
        private RallyInputService $input,
        private MatchRallyService $rally,
        private MatchFinalizeService $finalizer,
    ) {}

    /* ---------------------------------------------------------------- */
    /*  Контекст запроса                                                  */
    /* ---------------------------------------------------------------- */

    private function link(Request $request): BroadcastLink
    {
        return $request->attributes->get('broadcast_link');
    }

    private static function cookieKey(BroadcastLink $link): string
    {
        return 'scorer_name_' . $link->id;
    }

    /** Имя ведущего: приглашённый — из профиля; анонимный — сессия/кука этого устройства, иначе null. */
    public static function nameFor(Request $request, BroadcastLink $link): ?string
    {
        if ($link->assigned_user_id) {
            $u = $request->user();
            return $u ? (trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: ('ID ' . $u->id)) : null;
        }
        $n = $request->session()->get(self::cookieKey($link)) ?: $request->cookie(self::cookieKey($link));

        return is_string($n) && mb_strlen(trim($n)) >= 2 ? trim($n) : null;
    }

    private function actor(Request $request, BroadcastLink $link): ?RallyActor
    {
        $name = self::nameFor($request, $link);
        if (!$name) {
            return null;
        }

        return new RallyActor($link->assigned_user_id ? $request->user() : null, $name, (int) $link->id);
    }

    private function touchActor(BroadcastLink $link, RallyActor $actor): void
    {
        BroadcastLink::whereKey($link->id)->update([
            'last_actor_name'    => mb_substr((string) $actor->name, 0, 80),
            'last_actor_user_id' => $actor->user?->id,
            'last_actor_at'      => now(),
            'last_used_at'       => now(),
        ]);
    }

    private function court(BroadcastLink $link): EventCourt
    {
        return $link->court;
    }

    /** Матч принадлежит площадке ссылки; иначе 404 (чужие площадки/события не раскрываем). */
    private function ownMatch(BroadcastLink $link, TournamentMatch $match): TournamentMatch
    {
        $ok = $this->courtState->matchesOfCourt($this->court($link))->whereKey($match->id)->exists();
        abort_unless($ok, 404);
        $match->loadMissing(['stage', 'teamHome', 'teamAway']);

        return $match;
    }

    /** Почему матч нельзя вводить ведущему (null — можно). */
    private function blockReason(TournamentMatch $match): ?string
    {
        if ($match->stage->isPlayerBasedMatches()) {
            return __('broadcast.scorer_err_format');
        }
        if ($match->isCompleted()) {
            return __('broadcast.scorer_err_completed');
        }
        if (!$match->hasTeams()) {
            return __('broadcast.scorer_err_no_teams');
        }
        if ($match->is_tiebreaker) {
            return __('broadcast.scorer_err_tiebreak');
        }

        return null;
    }

    /** @return array{0:BroadcastLink,1:TournamentMatch,2:RallyActor}|JsonResponse */
    private function actionContext(Request $request, TournamentMatch $match, bool $allowCompleted = false)
    {
        $link = $this->link($request);
        $match = $this->ownMatch($link, $match);

        $actor = $this->actor($request, $link);
        if (!$actor) {
            return response()->json(['error' => __('broadcast.scorer_name_required')], 403);
        }
        $reason = $this->blockReason($match);
        if ($reason && !($allowCompleted && $match->isCompleted())) {
            return response()->json(['error' => $reason], 422);
        }

        return [$link, $match, $actor];
    }

    private function urls(BroadcastLink $link, TournamentMatch $match): array
    {
        return RallyUrls::forScorer($link, $match);
    }

    private function jsonState(BroadcastLink $link, TournamentMatch $match, int $setNumber): JsonResponse
    {
        $urls = $this->urls($link, $match);

        return response()->json([
            'success' => true,
            'board'   => $this->rally->getBoard($match->fresh(), $setNumber),
            'html'    => $this->screen->fragmentHtml($match, $setNumber, $urls),
        ]);
    }

    /* ---------------------------------------------------------------- */
    /*  Страницы                                                          */
    /* ---------------------------------------------------------------- */

    public function hub(Request $request)
    {
        $link = $this->link($request);
        $court = $this->court($link);
        $name = self::nameFor($request, $link);
        $askName = !$link->assigned_user_id && (!$name || $request->boolean('change'));

        $queue = $askName ? ['current' => null, 'upcoming' => collect()] : $this->courtState->queueFor($court);

        $done = null;
        if ($doneId = (int) $request->query('done')) {
            $done = $this->courtState->matchesOfCourt($court)->whereKey($doneId)->where('status', TournamentMatch::STATUS_COMPLETED)
                ->with(['teamHome', 'teamAway', 'stage'])->first();
        }

        return response()->view('scorer.hub', [
            'link'    => $link,
            'court'   => $court,
            'event'   => $link->event,
            'name'    => $name,
            'askName' => $askName,
            'current' => $queue['current'],
            'upcoming' => $queue['upcoming'],
            'done'    => $done,
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function setName(Request $request)
    {
        $link = $this->link($request);
        abort_if($link->assigned_user_id, 404);

        $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $request->input('name'))));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 40) {
            return redirect()->route('scorer.hub', [$link->token, 'change' => 1])
                ->with('scorer_error', __('broadcast.scorer_name_invalid'));
        }

        $request->session()->put(self::cookieKey($link), $name);
        Cookie::queue(Cookie::make(self::cookieKey($link), $name, 60 * 24 * 30));

        return redirect()->route('scorer.hub', $link->token);
    }

    public function match(Request $request, string $token, TournamentMatch $match)
    {
        $link = $this->link($request);
        $match = $this->ownMatch($link, $match);

        $name = self::nameFor($request, $link);
        if (!$name) {
            return redirect()->route('scorer.hub', $link->token);
        }
        if ($match->isCompleted()) {
            return redirect()->route('scorer.hub', [$link->token, 'done' => $match->id]);
        }
        if ($reason = $this->blockReason($match)) {
            return redirect()->route('scorer.hub', $link->token)->with('scorer_error', $reason);
        }

        $match->load(['teamHome.members.user', 'teamHome.captain', 'teamAway.members.user', 'teamAway.captain', 'stage']);
        $activeSet = $this->rally->getActiveSetNumber($match);
        $setNumber = (int) $request->query('set', $activeSet);
        if ($setNumber < 1) {
            $setNumber = $activeSet;
        }

        return response()->view('scorer.match', $this->screen->viewData($match, $setNumber, $this->urls($link, $match)) + [
            'link'  => $link,
            'court' => $this->court($link),
            'name'  => $name,
        ])->header('Cache-Control', 'no-store')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /* ---------------------------------------------------------------- */
    /*  Действия (JSON)                                                   */
    /* ---------------------------------------------------------------- */

    public function fragment(Request $request, string $token, TournamentMatch $match)
    {
        $ctx = $this->actionContext($request, $match);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$link, $match] = $ctx;

        $set = max(1, (int) $request->query('set', $this->rally->getActiveSetNumber($match)));

        return response()->json(['success' => true, 'html' => $this->screen->fragmentHtml($match, $set, $this->urls($link, $match))]);
    }

    /** Общий каркас действия: контекст → валидация → вызов сервиса → отметка «кто вводил» → JSON с фрагментом. */
    private function act(Request $request, TournamentMatch $match, array $rules, \Closure $do)
    {
        $ctx = $this->actionContext($request, $match);
        if ($ctx instanceof JsonResponse) {
            return $ctx;
        }
        [$link, $match, $actor] = $ctx;
        $v = $request->validate($rules);

        try {
            $do($match, $v, $actor);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $this->touchActor($link, $actor);

        return $this->jsonState($link, $match, (int) $v['set_number']);
    }

    public function point(Request $request, string $token, TournamentMatch $match)
    {
        return $this->act($request, $match, RallyInputService::pointRules(), fn ($m, $v, $a) => $this->input->point($m, $v, $a));
    }

    public function undo(Request $request, string $token, TournamentMatch $match)
    {
        return $this->act($request, $match, RallyInputService::undoRules(), fn ($m, $v, $a) => $this->input->undo($m, $v, $a));
    }

    public function lineupStart(Request $request, string $token, TournamentMatch $match)
    {
        return $this->act($request, $match, RallyInputService::startRules(), fn ($m, $v, $a) => $this->input->setStart($m, $v, $a));
    }

    public function lineupEvent(Request $request, string $token, TournamentMatch $match)
    {
        return $this->act($request, $match, RallyInputService::eventRules(), fn ($m, $v, $a) => $this->input->lineupEvent($m, $v, $a));
    }

    /** Запись итогового счёта из лога очков; ответ — адрес экрана площадки с итогом. Переоткрыть матч ведущий не может. */
    public function finalize(Request $request, string $token, TournamentMatch $match)
    {
        $link = $this->link($request);
        $match = $this->ownMatch($link, $match);
        $actor = $this->actor($request, $link);
        if (!$actor) {
            return response()->json(['error' => __('broadcast.scorer_name_required')], 403);
        }

        $done = fn () => response()->json(['success' => true, 'redirect' => route('scorer.hub', [$link->token, 'done' => $match->id])]);

        // Повторный тап: матч уже завершён тем же счётом из лога — это успех
        $fresh = $match->fresh();
        if ($fresh->isCompleted()) {
            $sets = $this->rally->isMatchReadyToFinalize($fresh) ? $this->rally->buildFinalSets($fresh) : [];
            if ($sets && $this->finalizer->sameSetScores($fresh, $sets)) {
                return $done();
            }
            return response()->json(['error' => __('broadcast.scorer_err_completed')], 422);
        }
        if ($reason = $this->blockReason($match)) {
            return response()->json(['error' => $reason], 422);
        }
        if (!$this->rally->isMatchReadyToFinalize($match)) {
            return response()->json(['error' => __('broadcast.scorer_err_not_ready')], 422);
        }

        try {
            $this->finalizer->finalize($match, $this->rally->buildFinalSets($match), null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
        $this->touchActor($link, $actor);

        return $done();
    }
}
