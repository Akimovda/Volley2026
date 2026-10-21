<?php

namespace App\Services\Broadcast;

use App\Events\CourtStateUpdated;
use App\Models\EventCourt;
use App\Models\TournamentMatch;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/** Рассылка состояния площадки. Сбой трансляции никогда не должен ломать ввод счёта. */
class CourtStateBroadcaster
{
    public const CACHE_PREFIX = 'broadcast:court-state:';

    public function __construct(
        protected CourtStateService $states,
        protected CourtRegistry $courts,
    ) {}

    /** Состояние с кешем на 1 с (для публичного GET). */
    public function cached(EventCourt $court): array
    {
        $key = self::CACHE_PREFIX . $court->id;
        $hit = Cache::get($key);
        // Кеш не должен пережить запланированную смену режима (иначе клиент после mode_until получит старый режим)
        if (is_array($hit) && (empty($hit['mode_until']) || now()->lt(\Carbon\Carbon::parse($hit['mode_until'])))) {
            return $hit;
        }
        $state = $this->states->forCourt($court);
        Cache::put($key, $state, 1);

        return $state;
    }

    public function forMatch(int|TournamentMatch|null $match): void
    {
        try {
            $match = is_int($match) ? TournamentMatch::find($match) : $match;
            if (!$match) {
                return;
            }
            $court = $this->courts->courtOfMatch($match);
            if ($court) {
                $this->forCourt($court);
            }
        } catch (\Throwable $e) {
            Log::warning('broadcast.court_state_failed', ['match_id' => is_object($match) ? $match->id : $match, 'error' => $e->getMessage()]);
        }
    }

    /** Матч + матчи, в которые перешёл победитель/проигравший (у них могли появиться команды). */
    public function forMatchAndFollowers(TournamentMatch $match): void
    {
        $ids = array_unique(array_filter([$match->id, $match->next_match_id, $match->loser_next_match_id]));
        foreach ($ids as $id) {
            $this->forMatch((int) $id);
        }
    }

    public function forCourt(EventCourt $court): array
    {
        Cache::forget(self::CACHE_PREFIX . $court->id);
        $court = $court->fresh() ?? $court;
        // Площадка скрыта: оверлей уходит в прозрачный режим (клиент затем опрашивает раз в 30 с)
        $state = $court->is_active ? $this->states->forCourt($court) : ['mode' => 'off', 'hidden' => true, 'seq' => 0, 'now_ms' => (int) (microtime(true) * 1000)];

        try {
            broadcast(new CourtStateUpdated($court->channel_key, $state));
        } catch (\Throwable $e) {
            Log::warning('broadcast.court_state_failed', ['court_id' => $court->id, 'error' => $e->getMessage()]);
        }

        return $state;
    }
}
