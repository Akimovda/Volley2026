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
            $this->logBroadcast($court, $state, true);
        } catch (\Throwable $e) {
            Log::warning('broadcast.court_state_failed', ['court_id' => $court->id, 'error' => $e->getMessage()]);
            $this->logBroadcast($court, $state, false, $e->getMessage());
        }

        return $state;
    }

    /** Строка в канал логов broadcast на каждое вещание: первые 6 символов channel_key, court_id, match_id, seq, mode. Сбой лога вещание не ломает. */
    private function logBroadcast(EventCourt $court, array $state, bool $ok, ?string $error = null): void
    {
        try {
            Log::channel('broadcast')->info('court_state', [
                'ch'       => substr((string) $court->channel_key, 0, 6),
                'court_id' => $court->id,
                'match_id' => $state['match']['id'] ?? null,
                'seq'      => $state['seq'] ?? null,
                'mode'     => $state['mode'] ?? null,
                'ok'       => $ok,
            ] + ($error !== null ? ['error' => mb_substr($error, 0, 200)] : []));
        } catch (\Throwable) {
            // журнал не должен влиять на вещание
        }
    }
}
