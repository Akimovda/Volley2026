<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Единая проверка «запрещена ли пользователю запись на мероприятие» по user_restrictions
 * (scope events — точечно или глобально, scope organizer — бан у организатора за неоплату наличными).
 *
 * Используется middleware EnsureUserNotRestricted (ручная запись) и всеми АВТОМАТИЧЕСКИМИ путями
 * (автозапись из листа ожидания, по абонементу, по Premium) — раньше они бан не проверяли.
 * Ручное добавление организатором (events.registrations.add) намеренно не блокируется.
 */
class UserRestrictionService
{
    public const ALL = 'all';
    public const EVENT = 'event';
    public const ORGANIZER = 'organizer';

    /** Причина запрета для одного пользователя или null, если можно записываться. */
    public function blockKind(int $userId, int $eventId): ?string
    {
        return $this->blockKinds([$userId], $eventId)[$userId] ?? null;
    }

    public function isBlocked(int $userId, int $eventId): bool
    {
        return $this->blockKind($userId, $eventId) !== null;
    }

    /**
     * Пакетная проверка: user_id => причина (только для заблокированных).
     *
     * @param  int[]  $userIds
     * @return array<int, string>
     */
    public function blockKinds(array $userIds, int $eventId): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));
        if (!$userIds) {
            return [];
        }

        $rows = DB::table('user_restrictions')
            ->select(['user_id', 'scope', 'event_ids', 'organizer_id'])
            ->whereIn('user_id', $userIds)
            ->whereIn('scope', ['events', 'organizer'])
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $eventOrganizerId = (int) DB::table('events')->where('id', $eventId)->value('organizer_id');
        $admins = User::whereIn('id', $rows->pluck('user_id')->unique())->get()->keyBy('id');

        $out = [];
        foreach ($rows as $r) {
            $uid = (int) $r->user_id;
            if (isset($out[$uid]) && $out[$uid] === self::ALL) {
                continue;
            }

            if ($r->scope === 'organizer') {
                // Админов бан по организатору не касается (защита от самоблокировки)
                if (is_numeric($r->organizer_id) && $eventOrganizerId
                    && (int) $r->organizer_id === $eventOrganizerId
                    && !($admins->get($uid)?->isAdmin())) {
                    $out[$uid] = $out[$uid] ?? self::ORGANIZER;
                }
                continue;
            }

            // scope=events: event_ids пусто/NULL → бан на ВСЕ мероприятия
            $ids = is_string($r->event_ids) ? json_decode($r->event_ids, true) : $r->event_ids;
            if (empty($ids)) {
                $out[$uid] = self::ALL;
            } elseif (is_array($ids) && in_array($eventId, array_map('intval', $ids), true)) {
                $out[$uid] = $out[$uid] ?? self::EVENT;
            }
        }

        return $out;
    }
}
