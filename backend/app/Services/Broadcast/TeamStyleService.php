<?php

namespace App\Services\Broadcast;

use App\Models\BroadcastTeamStyle;
use App\Models\Event;
use App\Models\EventTeam;
use App\Models\User;
use Illuminate\Database\QueryException;

/** Цвет и логотип команды на трансляции. */
class TeamStyleService
{
    /** Цвета friendly-команд (meta.color — ключ) → hex. */
    public const FRIENDLY_HEX = [
        'red' => '#E53935', 'blue' => '#1E88E5', 'green' => '#43A047',
        'yellow' => '#FDD835', 'white' => '#FFFFFF', 'black' => '#212121',
    ];

    /**
     * Цвет: стиль команды → friendly meta.color → автоназначение из палитры
     * (сохраняется, чтобы цвет не менялся в течение турнира; без повторов внутри события, пока хватает палитры).
     */
    public function colorFor(EventTeam $team): string
    {
        $style = BroadcastTeamStyle::where('event_team_id', $team->id)->first();
        if ($style && $style->color) {
            return $style->color;
        }

        $meta = $team->meta['color'] ?? null;
        if (is_string($meta) && isset(self::FRIENDLY_HEX[$meta])) {
            return self::FRIENDLY_HEX[$meta];
        }
        if (is_string($meta) && preg_match('/^#[0-9a-fA-F]{6}$/', $meta)) {
            return $meta;
        }

        return $this->assignFromPalette($team, $style);
    }

    private function assignFromPalette(EventTeam $team, ?BroadcastTeamStyle $style): string
    {
        $teamIds = EventTeam::where('event_id', $team->event_id)->pluck('id');
        $used = BroadcastTeamStyle::whereIn('event_team_id', $teamIds)
            ->whereNotNull('color')->pluck('color')
            ->map(fn ($c) => strtoupper($c))->all();

        $palette = BroadcastTeamStyle::PALETTE;
        $color = null;
        foreach ($palette as $c) {
            if (!in_array($c, $used, true)) {
                $color = $c;
                break;
            }
        }
        $color ??= $palette[count($used) % count($palette)];

        try {
            BroadcastTeamStyle::updateOrCreate(['event_team_id' => $team->id], ['color' => $color]);
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23505') {
                throw $e;
            }
            return BroadcastTeamStyle::where('event_team_id', $team->id)->value('color') ?: $color;
        }

        return $color;
    }

    /** Логотип: загруженный команде → аватар организатора (оригинал) → null. */
    public function logoUrlFor(EventTeam $team, ?Event $event = null): ?string
    {
        $style = BroadcastTeamStyle::where('event_team_id', $team->id)->first();
        $media = $style?->getFirstMedia('team_logo');
        if ($media) {
            return $this->abs($media->hasGeneratedConversion('logo') ? $media->getUrl('logo') : $media->getUrl());
        }

        $event ??= Event::with('organizer')->find($team->event_id);
        return $this->organizerAvatarUrl($event?->organizer);
    }

    public function organizerAvatarUrl(?User $user): ?string
    {
        if (!$user || !$user->avatar_media_id) {
            return null;
        }
        $media = $user->getMedia('photos')->firstWhere('id', $user->avatar_media_id);
        return $media ? $this->abs($media->getUrl()) : null;
    }

    private function abs(string $url): string
    {
        return str_starts_with($url, '/') ? url($url) : $url;
    }
}
