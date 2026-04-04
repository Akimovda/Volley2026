<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\EventOccurrence;
use Illuminate\Support\Carbon;

class OccurrenceAnnouncementMessageBuilder
{
    public function build(EventOccurrence $occurrence, array $options = []): ChannelMessageData
    {
        $event = $occurrence->event;
        $tz = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
        $starts = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);
        $platform = (string) ($options['platform'] ?? '');

        $link = !empty($options['private_link'])
            ? (string) $options['private_link']
            : route('events.show', [
                'event' => $event->id,
                'occurrence' => $occurrence->id,
            ]);

        $imageUrl = null;
        if ((bool) ($options['include_image'] ?? true) && $event->media?->first()) {
            $imageUrl = $event->media->first()->getUrl();
        }

        if ($platform === 'vk') {
            $location = $event->location?->name ?: 'Место уточняется';

            $text =
                "🏐 {$event->title}\n\n" .
                "📍 {$location}\n" .
                "🕒 {$starts->format('d.m H:i')}\n\n" .
                "Жми кнопку, чтобы записаться 👇";
        } else {
            $registered = $this->registeredPlayersText(
                $occurrence,
                (bool) ($options['include_registered_list'] ?? true)
            );

            $parts = array_filter([
                "🏐 {$event->title}",
                "📅 " . $starts->format('d.m.Y H:i') . " ({$tz})",
                $event->location?->name ? "📍 {$event->location->name}" : null,
                null,
                'Открыта регистрация на мероприятие.',
                $registered,
            ], static fn ($v) => !is_null($v) && $v !== '',
            );

            $text = implode("\n", $parts);
        }

        return new ChannelMessageData(
            title: (string) $event->title,
            text: $text,
            buttonUrl: $link,
            buttonText: $platform === 'vk' ? 'Записаться' : 'Открыть мероприятие',
            imageUrl: $imageUrl,
            silent: (bool) ($options['silent'] ?? false),
        );
    }

    private function registeredPlayersText(EventOccurrence $occurrence, bool $include): ?string
    {
        if (!$include) {
            return null;
        }

        $rows = $occurrence->registrations()
            ->with('user:id,name')
            ->whereNull('cancelled_at')
            ->limit(20)
            ->get();

        if ($rows->isEmpty()) {
            return 'Пока никто не записался.';
        }

        $names = $rows->map(static function ($r) {
            $name = trim((string) ($r->user?->name ?? ('Игрок #' . $r->user_id)));
            return '• ' . $name;
        })->implode("\n");

        return "Уже записались:\n{$names}";
    }
}