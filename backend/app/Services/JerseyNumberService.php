<?php

namespace App\Services;

use App\Models\EventJerseyNumber;
use App\Models\TournamentMatch;
use App\Models\User;
use InvalidArgumentException;

/** Номера на футболках (событие + игрок). Дубли допустимы; пусто = номера нет. */
class JerseyNumberService
{
    public const MAX = 99;

    /** @return array<int,int> user_id => номер (только заполненные) */
    public function forEvent(int $eventId, array $userIds = []): array
    {
        return EventJerseyNumber::where('event_id', $eventId)
            ->whereNotNull('number')
            ->when($userIds, fn ($q) => $q->whereIn('user_id', $userIds))
            ->pluck('number', 'user_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /**
     * Сохранить номер игрока. $number = null или '' — убрать номер.
     * Игрок должен входить в подтверждённый состав одной из команд матча.
     */
    public function setForMatch(TournamentMatch $match, int $userId, $number, ?User $by = null, ?int $byLinkId = null): ?int
    {
        $event = ($match->relationLoaded('stage') ? $match->stage : $match->stage()->first())?->event;
        if (!$event) {
            throw new InvalidArgumentException('Событие матча не найдено.');
        }

        $roster = app(MatchLineupService::class)->roster($match);
        $inMatch = false;
        foreach ($roster as $players) {
            if (isset($players[$userId])) {
                $inMatch = true;
                break;
            }
        }
        if (!$inMatch) {
            throw new InvalidArgumentException('Игрок не входит в состав команд этого матча.');
        }

        $value = null;
        if ($number !== null && $number !== '') {
            if (!is_numeric($number) || (int) $number != $number || (int) $number < 0 || (int) $number > self::MAX) {
                throw new InvalidArgumentException('Номер — целое число от 0 до ' . self::MAX . '.');
            }
            $value = (int) $number;
        }

        EventJerseyNumber::updateOrCreate(
            ['event_id' => (int) $event->id, 'user_id' => $userId],
            ['number' => $value, 'updated_by_user_id' => $by?->id, 'updated_by_link_id' => $byLinkId]
        );

        return $value;
    }
}
