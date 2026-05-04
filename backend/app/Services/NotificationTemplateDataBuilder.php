<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class NotificationTemplateDataBuilder
{
    public function build(
        ?User $user = null,
        ?Event $event = null,
        ?EventOccurrence $occurrence = null,
        ?EventRegistration $registration = null,
        array $extra = []
    ): array {
        $event ??= $occurrence?->event;
        $occurrence ??= $registration?->occurrence;
        $event ??= $registration?->event;

        if ($event && !$event->relationLoaded('location')) {
            $event->loadMissing(['location.city', 'organizer']);
        }

        if ($occurrence && !$occurrence->relationLoaded('event')) {
            $occurrence->loadMissing(['event.location.city', 'event.organizer']);
        }

        if ($registration && !$registration->relationLoaded('event')) {
            $registration->loadMissing(['event.location.city', 'occurrence']);
        }

        $location = $event?->location ?? $occurrence?->event?->location;
        $organizer = $event?->organizer ?? $occurrence?->event?->organizer;

        $tz = (string) (
            $location?->city?->timezone
            ?: $occurrence?->timezone
            ?: $event?->timezone
            ?: 'UTC'
        );

        $startsUtc = $occurrence?->starts_at
            ? Carbon::parse($occurrence->starts_at, 'UTC')
            : ($event?->starts_at ? Carbon::parse($event->starts_at, 'UTC') : null);

        $startsLocal = $startsUtc?->copy()->setTimezone($tz);

        $durationSec = $occurrence?->duration_sec ?? $event?->duration_sec ?? null;
        $durationMinutes = $durationSec ? (int) floor(((int) $durationSec) / 60) : null;

        $eventUrl = $this->buildEventUrl($event?->id, $occurrence?->id, $event);

        $locationName = trim((string) ($location?->name ?? ''));
        $locationAddress = trim((string) ($location?->address ?? ''));
        $locationCity = trim((string) ($location?->city?->name ?? ''));

        $locationFull = collect([$locationName, $locationAddress, $locationCity])
            ->filter(fn ($v) => trim((string) $v) !== '')
            ->implode(', ');

        $gameSettings = $this->loadGameSettings($event?->id);

        $data = [
            // user
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email,
            'user_phone' => $user?->phone ?? null,
            'user_city' => $this->resolveUserCity($user),
            'user_vk_id' => $user?->vk_id ?? null,
            'user_telegram_id' => $user?->telegram_id ?? null,
            'user_max_chat_id' => $user?->max_chat_id ?? null,

            // event
            'event_id' => $event?->id,
            'event_title' => $event?->title,
            'event_description' => $event?->description ?? null,
            'event_url' => $eventUrl,
            'event_type' => $event?->type ?? null,
            'event_direction' => $event?->direction ?? null,
            'event_timezone' => $event?->timezone ?? $tz,
            'event_is_recurring' => (bool) ($event?->is_recurring ?? false),

            // occurrence
            'occurrence_id' => $occurrence?->id,
            'occurrence_starts_at_utc' => $startsUtc?->format('Y-m-d H:i:s'),
            'occurrence_starts_at_local' => $startsLocal?->format('Y-m-d H:i:s'),
            'occurrence_date' => $startsLocal?->format('d.m.Y'),
            'occurrence_time' => $startsLocal?->format('H:i'),
            'occurrence_datetime' => $startsLocal?->format('d.m.Y H:i'),
            'occurrence_timezone' => $tz,
            'occurrence_duration_minutes' => $durationMinutes,

            // location
            'location_id' => $location?->id,
            'location_name' => $locationName,
            'location_address' => $locationAddress,
            'location_city' => $locationCity,
            'location_full' => $locationFull,

            // organizer
            'organizer_id' => $organizer?->id,
            'organizer_name' => $organizer?->name,
            'organizer_email' => $organizer?->email,

            // registration
            'registration_id' => $registration?->id,
            'registration_status' => $registration?->status ?? null,
            'registration_position' => $registration?->position ?? null,
            'registration_position_name' => $registration?->position ? position_name($registration->position) : null,
            'registration_group_key' => $registration?->group_key ?? null,
            'registration_created_at' => !empty($registration?->created_at)
                ? Carbon::parse($registration->created_at)->format('Y-m-d H:i:s')
                : null,

            // game settings
            'game_subtype' => $gameSettings['subtype'] ?? null,
            'game_min_players' => $gameSettings['min_players'] ?? null,
            'game_max_players' => $gameSettings['max_players'] ?? null,
            'game_gender_policy' => $gameSettings['gender_policy'] ?? null,
            'game_libero_mode' => $gameSettings['libero_mode'] ?? null,

            // common aliases
            'event_date' => $startsLocal?->format('d.m.Y'),
            'event_time' => $startsLocal?->format('H:i'),
            'event_datetime' => $startsLocal?->format('d.m.Y H:i'),
            'starts_at_text' => $startsLocal?->format('d.m.Y H:i') . ($startsLocal ? " ({$tz})" : ''),
            // алиас для шаблонов, использующих {event_address}
            'event_address' => $locationFull,
        ];

        return $this->sanitize(array_merge($data, $extra));
    }

    private function buildEventUrl(?int $eventId, ?int $occurrenceId, ?Event $event = null): ?string
    {
        if (!$eventId) {
            return null;
        }

        // Для приватных событий добавляем public_token, чтобы ссылка открывалась
        // во внутреннем браузере мессенджера без авторизации
        $token = null;
        if ($event && (int) ($event->is_private ?? 0) === 1 && !empty($event->public_token)) {
            $token = (string) $event->public_token;
        }

        $params = array_filter([
            'event'      => $eventId,
            'occurrence' => $occurrenceId ?: null,
            'token'      => $token,
        ]);

        try {
            return route('events.show', $params);
        } catch (\Throwable) {
            $base = rtrim((string) config('app.url'), '/');
            if ($base === '') {
                return null;
            }

            $query = http_build_query(array_filter([
                'occurrence' => $occurrenceId ?: null,
                'token'      => $token,
            ]));

            return $query ? "{$base}/events/{$eventId}?{$query}" : "{$base}/events/{$eventId}";
        }
    }

    private function loadGameSettings(?int $eventId): array
    {
        if (!$eventId || !Schema::hasTable('event_game_settings')) {
            return [];
        }

        $row = DB::table('event_game_settings')
            ->where('event_id', $eventId)
            ->first([
                'subtype',
                'min_players',
                'max_players',
                'gender_policy',
                'libero_mode',
            ]);

        return $row ? (array) $row : [];
    }

    private function resolveUserCity(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        if (property_exists($user, 'city') && !empty($user->city)) {
            return (string) $user->city;
        }

        return null;
    }

    private function sanitize(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (is_bool($value)) {
                $result[$key] = $value ? '1' : '0';
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[$key] = $value === null ? '' : trim((string) $value);
            }
        }

        return $result;
    }
}
