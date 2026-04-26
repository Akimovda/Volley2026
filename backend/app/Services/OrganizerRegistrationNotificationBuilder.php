<?php

namespace App\Services;

use App\Data\ChannelMessageData;
use App\Models\EventOccurrence;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrganizerRegistrationNotificationBuilder
{
    private const POS_LABELS = [
        'setter'   => 'Связующий',
        'outside'  => 'Доигровщик',
        'opposite' => 'Диагональный',
        'middle'   => 'Центральный',
        'libero'   => 'Либеро',
        'defender' => 'Защитник',
    ];

    /**
     * @param  string $type  'registered' | 'cancelled'
     */
    public function build(
        EventOccurrence $occurrence,
        User $player,
        string $type,
        string $platform = 'telegram'
    ): ChannelMessageData {
        $event    = $occurrence->event;
        $tz       = $occurrence->timezone ?: ($event->timezone ?: 'UTC');
        $starts   = Carbon::parse($occurrence->starts_at, 'UTC')->setTimezone($tz);
        $durationSec = $occurrence->duration_sec ?? $event->duration_sec ?? null;
        $ends     = $durationSec ? $starts->copy()->addSeconds((int) $durationSec) : null;

        $text = $this->buildText($occurrence, $player, $type, $starts, $ends, $platform);

        $occUrl = route('events.show', [
            'event'      => (int) $event->id,
            'occurrence' => (int) $occurrence->id,
        ]);

        $messageThreadId = null;

        return new ChannelMessageData(
            title: $type === 'registered' ? '✅ Регистрация подтверждена' : '⛔️ Бронь отменена',
            text: $text,
            buttonUrl: $occUrl,
            buttonText: 'Открыть мероприятие',
            imageUrl: null,
            silent: false,
            messageThreadId: $messageThreadId,
        );
    }

    private function buildText(
        EventOccurrence $occurrence,
        User $player,
        string $type,
        Carbon $starts,
        ?Carbon $ends,
        string $platform
    ): string {
        $event    = $occurrence->event;
        $location = $event->location ?? null;

        $header = $type === 'registered' ? '✅ Регистрация подтверждена' : '⛔️ Бронь отменена';

        $lines = [];
        $lines[] = $header;
        $lines[] = '';

        // Название
        $title = (string) ($event->title ?? '—');
        $lines[] = '🏐 ' . ($platform === 'telegram' ? "<b>{$title}</b>" : $title);
        $lines[] = '';
        $lines[] = 'Информация:';
        $lines[] = '';

        // Дата
        Carbon::setLocale('ru');
        $dayName  = mb_ucfirst($starts->translatedFormat('l'));
        $dateStr  = $starts->translatedFormat('j F');
        $lines[] = "📆: {$dayName}, {$dateStr}";

        // Время
        $timeStr = $starts->format('H:i');
        if ($ends) {
            $timeStr .= ' - ' . $ends->format('H:i');
        }
        $lines[] = "🕘: {$timeStr}";

        // Место
        if ($location) {
            $addrParts = array_filter([
                $location->metro ?? null,
                $location->city?->name ?? null,
                $location->address ?? null,
            ]);
            $addr = $addrParts ? implode(', ', $addrParts) : ($location->name ?? null);
            if ($addr) {
                $lines[] = "📍: {$addr}";
            }
        }

        $lines[] = '';

        // Статистика мест
        $registered = $this->countRegistered((int) $occurrence->id);
        $maxPlayers = $this->getMaxPlayers($occurrence);
        if ($maxPlayers > 0) {
            $available = max(0, $maxPlayers - $registered);
            $lines[] = "Сейчас {$registered} мест(о) забронировано, а {$available} доступно.";
        }

        $lines[] = '';
        $lines[] = 'Детали записи:';
        $lines[] = '';

        // Игрок
        $playerName = trim(implode(' ', array_filter([
            $player->last_name,
            $player->first_name,
            $player->patronymic,
        ])));
        if ($playerName === '') {
            $playerName = (string) ($player->name ?? '—');
        }
        $lines[] = "👤 : {$playerName}";

        $phone = (string) ($player->phone ?? '');
        if ($phone !== '') {
            $lines[] = "☎️ : {$phone}";
        }

        // Позиция
        $position = $this->getPlayerPosition($player->id, $occurrence->id);
        if ($position !== null) {
            $posLabel = self::POS_LABELS[$position] ?? $position;
            $lines[] = "✅ {$posLabel}";
        }

        return implode("\n", $lines);
    }

    private function countRegistered(int $occurrenceId): int
    {
        return (int) DB::table('event_registrations')
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', 'confirmed');
            })
            ->count();
    }

    private function getMaxPlayers(EventOccurrence $occurrence): int
    {
        $max = $occurrence->max_players
            ?? $occurrence->event?->gameSettings?->max_players
            ?? 0;
        return (int) $max;
    }

    private function getPlayerPosition(int $userId, int $occurrenceId): ?string
    {
        $row = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->where('occurrence_id', $occurrenceId)
            ->whereNull('cancelled_at')
            ->value('position');

        return $row !== null ? (string) $row : null;
    }
}
