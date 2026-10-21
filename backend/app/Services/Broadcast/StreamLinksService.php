<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastCourtOverride;
use App\Models\EventCourt;

/**
 * Публичные ссылки на эфир (VK Видео, RuTube, YouTube, другое) по площадкам.
 * Токен оверлея сюда не попадает никогда — только stream_urls.
 */
class StreamLinksService
{
    public const PLATFORMS = ['vk', 'rutube', 'youtube', 'other'];

    /** Только https, без пробелов и учётных данных в URL; иначе null. */
    public static function cleanUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || mb_strlen($url) > 300 || preg_match('/\s/u', $url)) {
            return null;
        }
        $p = parse_url($url);
        if (!$p || strtolower($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
            return null;
        }
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * @return array<int, array{id:int,name:string,links:array<int,array{platform:string,url:string}>}> по court_id
     */
    public function forEvent(int $eventId, ?int $occurrenceId): array
    {
        $courts = EventCourt::where('event_id', $eventId)->where('is_active', true)
            ->when($occurrenceId, fn ($q) => $q->where(fn ($w) => $w->where('occurrence_id', $occurrenceId)->orWhereNull('occurrence_id')))
            ->orderBy('sort_order')->orderBy('id')->get();
        if ($courts->isEmpty()) {
            return [];
        }
        $overrides = BroadcastCourtOverride::whereIn('court_id', $courts->pluck('id'))->get()->keyBy('court_id');

        $out = [];
        foreach ($courts as $c) {
            $links = [];
            foreach ((array) ($overrides[$c->id]->stream_urls ?? []) as $platform => $url) {
                if (in_array($platform, self::PLATFORMS, true) && ($u = self::cleanUrl(is_string($url) ? $url : null))) {
                    $links[] = ['platform' => $platform, 'url' => $u];
                }
            }
            if ($links) {
                $out[(int) $c->id] = ['id' => (int) $c->id, 'name' => $c->name, 'links' => $links];
            }
        }
        return $out;
    }
}
