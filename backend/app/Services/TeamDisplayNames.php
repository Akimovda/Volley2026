<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTeam;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Как показывать команду: в пляжке (direction = beach) пара из ровно двух подтверждённых игроков — это «Фамилия Имя» обоих
 * игроков (вместо названия команды), в остальных случаях — название команды и (в итогах/шапках классики) «Капитан: Фамилия Имя».
 * Один источник правды для оверлея (/state), пульта, страницы статистики, PDF и share-card.
 */
class TeamDisplayNames
{
    /** Подтверждённые игроки команды (по порядку добавления). */
    public function confirmedMembers(EventTeam $t): Collection
    {
        return DB::table('event_team_members')
            ->join('users', 'users.id', '=', 'event_team_members.user_id')
            ->where('event_team_members.event_team_id', $t->id)
            ->where('event_team_members.confirmation_status', 'confirmed')
            ->orderBy('event_team_members.id')
            ->get(['users.last_name', 'users.first_name', 'users.name']);
    }

    /** Фамилии (до двух). */
    public function surnames(EventTeam $t): array
    {
        return $this->confirmedMembers($t)->take(2)
            ->map(fn ($u) => trim((string) ($u->last_name ?: $u->first_name)))
            ->filter()->values()->all();
    }

    /** «Фамилия Имя» (до двух). */
    public function fullNames(EventTeam $t): array
    {
        return $this->confirmedMembers($t)->take(2)
            ->map(fn ($u) => $this->full($u))
            ->filter()->values()->all();
    }

    /**
     * Подпись под командой на экране камеры (/broadcast/camera): пляжка — весь подтверждённый состав «Фамилия Имя»
     * (по строке на игрока); классика — «Капитан: Фамилия Имя», если капитан назначен (event_teams.captain_user_id); иначе пусто.
     *
     * @return string[]
     */
    public function cameraLines(?EventTeam $t, ?Event $event = null): array
    {
        if (!$t) {
            return [];
        }
        $event ??= $t->event;
        if (($event?->direction ?? null) === 'beach') {
            return $this->confirmedMembers($t)->map(fn ($u) => $this->full($u))->filter()->values()->all();
        }
        $cap = $this->captainName($t);

        return $cap ? [__('broadcast.captain') . ': ' . $cap] : [];
    }

    /** «Фамилия Имя» капитана (event_teams.captain_user_id) или null. */
    public function captainName(EventTeam $t): ?string
    {
        if (!$t->captain_user_id) {
            return null;
        }
        $u = DB::table('users')->where('id', $t->captain_user_id)->first(['last_name', 'first_name', 'name']);

        return $u ? ($this->full($u) ?: null) : null;
    }

    /**
     * Пара в пляжке: [['full' => 'Фамилия Имя', 'sur' => 'Фамилия'], …] (ровно два игрока) или null, если это не пара.
     */
    public function pair(?EventTeam $t, ?Event $event = null): ?array
    {
        if (!$t) {
            return null;
        }
        $event ??= $t->event;
        if (($event?->direction ?? null) !== 'beach') {
            return null;
        }
        $members = $this->confirmedMembers($t);
        if ($members->count() !== 2) {
            return null;
        }

        return $members->map(fn ($u) => [
            'full' => $this->full($u),
            'sur'  => trim((string) ($u->last_name ?: $u->first_name)),
        ])->values()->all();
    }

    private function full(object $u): string
    {
        $full = trim(trim((string) ($u->last_name ?? '')) . ' ' . trim((string) ($u->first_name ?? '')));

        return $full !== '' ? $full : trim((string) ($u->name ?? ''));
    }
}
