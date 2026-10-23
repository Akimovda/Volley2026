<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastChannel;
use App\Models\Event;
use App\Models\EventCourt;

/**
 * Куда площадка ведёт эфир: назначение площадки (канал или разовый ключ) → иначе канал организатора по умолчанию.
 * Возвращает и платформу/подпись для показа, и адрес/ключ для /start (их наружу отдаёт только start()).
 */
class BroadcastTargetResolver
{
    public const NONE = 'none';             // ничего не настроено
    public const NEED_CHANNEL = 'need_channel'; // был канал, его удалили, замены нет

    /** @return array{ok:bool, error:?string, platform:?string, label:?string, server_url:?string, stream_key:?string, channel_id:?int, via:?string, destination_id:?int} */
    public function resolve(EventCourt $court, Event $event): array
    {
        $dests = $court->destinations()->with('channel')->orderBy('id')->get();
        $valid = $dests->filter(fn ($d) => $d->channel ? true : !$d->isOrphan());
        $dest = $valid->firstWhere('is_default', true) ?: $valid->first();

        if ($dest) {
            $ch = $dest->channel;
            return $this->ok($ch ?: $dest, $ch ? (int) $ch->id : null, $ch ? 'channel' : 'own', (int) $dest->id);
        }

        $default = BroadcastChannel::where('organizer_id', (int) $event->organizer_id)->where('is_default', true)->first();
        if ($default) {
            return $this->ok($default, (int) $default->id, 'default_channel');
        }

        return [
            'ok' => false, 'platform' => null, 'label' => null, 'server_url' => null, 'stream_key' => null, 'channel_id' => null, 'via' => null,
            'error' => $dests->contains(fn ($d) => $d->isOrphan()) ? self::NEED_CHANNEL : self::NONE,
        ];
    }

    /** @param \App\Models\BroadcastChannel|\App\Models\BroadcastDestination $src */
    private function ok($src, ?int $channelId, string $via, ?int $destId = null): array
    {
        return [
            'ok' => true, 'error' => null, 'platform' => $src->platform, 'label' => $src->label,
            'server_url' => $src->server_url, 'stream_key' => $src->stream_key, 'channel_id' => $channelId, 'via' => $via, 'destination_id' => $destId,
        ];
    }
}
