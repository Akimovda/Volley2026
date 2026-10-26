<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\TournamentMatch;
use App\Models\TournamentStage;
use App\Services\EventAccessService;
use App\Services\EventVisibilityService;
use App\Services\MatchAdvancedStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

/**
 * Страница расширенной статистики матча (/matches/{match}/stats) и её PDF.
 * Доступ ровно как у результатов, где выводится match_stats_pretty: турнир — публично,
 * товарищеская игра — как FriendlyGameController::results (collect_stats + приватность).
 */
class MatchStatsPageController extends Controller
{
    public function __construct(
        private readonly MatchAdvancedStatsService $stats,
        private readonly EventAccessService $access,
    ) {
    }

    public function show(Request $request, TournamentMatch $match)
    {
        $event = $this->authorizeView($request, $match);
        $stage = $match->stage;

        $data = $this->stats->build($match);
        $user = $request->user();

        return view('matches.stats', [
            'match'         => $match,
            'event'         => $event,
            'stage'         => $stage,
            'stats'         => $data,
            'isLive'        => $match->status !== TournamentMatch::STATUS_COMPLETED,
            'isCompleted'   => $match->status === TournamentMatch::STATUS_COMPLETED,
            'highlight'     => (int) $request->query('player', 0) ?: null,
            'canSeeRecorder' => $user && $this->access->canManageEvent($user, (int) $event->organizer_id),
            'resultsUrl'    => $this->resultsUrl($match, $event),
        ]);
    }

    public function pdf(Request $request, TournamentMatch $match)
    {
        $event = $this->authorizeView($request, $match);
        abort_unless($match->status === TournamentMatch::STATUS_COMPLETED, 404);

        $dir = storage_path('app/match-stats-pdf');
        $path = $dir . '/match-' . $match->id . '-' . $this->stats->dataVersion($match) . '-' . app()->getLocale() . '.pdf';

        if (!is_file($path)) {
            File::ensureDirectoryExists($dir, 0775);
            foreach (glob($dir . '/match-' . $match->id . '-*.pdf') ?: [] as $old) {
                @unlink($old);
            }

            $html = view('matches.stats_pdf', [
                'match' => $match,
                'event' => $event,
                'stage' => $match->stage,
                'stats' => $this->stats->build($match),
            ])->render();

            $shot = \Spatie\Browsershot\Browsershot::html($html)
                ->format('A4')
                ->margins(10, 10, 12, 10)
                ->showBackground()
                ->noSandbox();

            // Chrome лежит в storage/app/chromium (как у share-card), версия в пути не хардкодится
            $chrome = glob(storage_path('app/chromium/*/chrome-linux64/chrome'));
            if (!empty($chrome)) {
                $shot->setChromePath($chrome[0]);
            }
            $shot->save($path);
        }

        return response()->download($path, 'match_' . $match->id . '_stats.pdf', ['Content-Type' => 'application/pdf']);
    }

    /** Права просмотра + наличие событий розыгрышей. Возвращает мероприятие матча. */
    private function authorizeView(Request $request, TournamentMatch $match): Event
    {
        $match->loadMissing('stage.event');
        $stage = $match->stage;
        $event = $stage?->event;
        abort_unless($stage && $event, 404);

        if ($stage->type === TournamentStage::TYPE_FRIENDLY) {
            abort_unless($event->collect_stats, 404);

            $visibility = app(EventVisibilityService::class);
            if ($visibility->isPrivateEventRow($event)) {
                abort_unless($visibility->canViewPrivateEvent($event, $request->user()), 403);
            }
        }

        abort_unless($this->stats->hasRally((int) $match->id), 404);

        return $event;
    }

    private function resultsUrl(TournamentMatch $match, Event $event): string
    {
        if ($match->stage?->type === TournamentStage::TYPE_FRIENDLY) {
            return route('game.results', [$event] + ($match->stage->occurrence_id ? ['occurrence' => $match->stage->occurrence_id] : []));
        }

        return route('tournament.public.show', [$event] + ($match->stage?->occurrence_id ? ['occurrence_id' => $match->stage->occurrence_id] : []));
    }
}
