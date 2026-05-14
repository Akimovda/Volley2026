<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserMergeService
{
    /**
     * @return array{transferred: int, cancelled_conflicts: array<int, array{event_id:int, title:string, starts_at:string}>}
     */
    public function merge(User $primary, User $secondary): array
    {
        if ($primary->id === $secondary->id) {
            throw new \InvalidArgumentException('Нельзя объединить аккаунт с самим собой.');
        }

        $result = ['transferred' => 0, 'cancelled_conflicts' => []];

        DB::transaction(function () use ($primary, $secondary, &$result) {

            // 1. Провайдеры — уникальные поля: сначала обнуляем у secondary, потом ставим на primary
            $uniqueProviderFields = ['telegram_id', 'vk_id', 'yandex_id', 'apple_id', 'google_id'];
            $toTransfer = [];
            foreach ($uniqueProviderFields as $f) {
                if (empty($primary->$f) && !empty($secondary->$f)) {
                    $toTransfer[$f] = $secondary->$f; // сохраняем значение
                }
            }
            if (!empty($toTransfer)) {
                // Снимаем unique-значения у secondary до того как primary их получит
                DB::table('users')->where('id', $secondary->id)
                    ->update(array_fill_keys(array_keys($toTransfer), null));
                foreach ($toTransfer as $f => $val) {
                    $primary->$f = $val;
                    $secondary->$f = null;
                }
            }
            // Не-уникальные поля провайдеров
            foreach (['telegram_username', 'yandex_phone', 'telegram_phone', 'vk_phone'] as $f) {
                if (empty($primary->$f) && !empty($secondary->$f)) {
                    $primary->$f = $secondary->$f;
                }
            }

            // 2. Профиль — заполняем пустые поля из вторичного
            foreach (['phone', 'first_name', 'last_name', 'patronymic', 'birth_date', 'city_id', 'gender', 'height_cm', 'classic_level', 'beach_level'] as $f) {
                if (empty($primary->$f) && !empty($secondary->$f)) {
                    $primary->$f = $secondary->$f;
                }
            }
            $primary->save();

            // 3. Регистрации — без дублей по occurrence
            $primaryOccurrences = DB::table('event_registrations')
                ->where('user_id', $primary->id)
                ->pluck('occurrence_id')
                ->toArray();

            // Фиксируем конфликтные будущие записи ПЕРЕД отменой
            $nowUtc = now('UTC');
            $conflictRows = DB::table('event_registrations as er')
                ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
                ->join('events as e', 'e.id', '=', 'er.event_id')
                ->where('er.user_id', $secondary->id)
                ->whereIn('er.occurrence_id', $primaryOccurrences)
                ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
                ->where('eo.starts_at', '>', $nowUtc)
                ->select('er.occurrence_id', 'er.event_id', 'e.title', 'eo.starts_at', 'eo.timezone')
                ->get();

            foreach ($conflictRows as $row) {
                $tz = $row->timezone ?: 'UTC';
                $result['cancelled_conflicts'][] = [
                    'event_id'   => $row->event_id,
                    'title'      => $row->title ?? ('Мероприятие #' . $row->event_id),
                    'starts_at'  => \Carbon\Carbon::parse($row->starts_at, 'UTC')->setTimezone($tz)->format('d.m.Y H:i'),
                ];
            }

            $result['transferred'] = DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id', $primaryOccurrences)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->count();

            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id', $primaryOccurrences)
                ->update(['user_id' => $primary->id]);

            // Отменяем оставшиеся конфликтные записи secondary
            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
                ->update([
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'status'       => 'cancelled',
                    'updated_at'   => now(),
                ]);

            // 4. Лог регистраций
            DB::table('event_registration_logs')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id]);
            DB::table('event_registration_logs')
                ->where('actor_id', $secondary->id)
                ->update(['actor_id' => $primary->id]);

            // 5. Платежи
            DB::table('payments')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id]);

            // 6. Виртуальный кошелёк — мержим баланс
            $secondaryWallets = DB::table('virtual_wallets')
                ->where('user_id', $secondary->id)
                ->get();

            foreach ($secondaryWallets as $sw) {
                $primaryWallet = DB::table('virtual_wallets')
                    ->where('user_id', $primary->id)
                    ->where('organizer_id', $sw->organizer_id)
                    ->first();

                if ($primaryWallet) {
                    // Переносим транзакции на кошелёк основного
                    DB::table('wallet_transactions')
                        ->where('wallet_id', $sw->id)
                        ->update(['wallet_id' => $primaryWallet->id]);

                    // Суммируем баланс
                    DB::table('virtual_wallets')
                        ->where('id', $primaryWallet->id)
                        ->increment('balance_minor', $sw->balance_minor);

                    DB::table('virtual_wallets')->where('id', $sw->id)->delete();
                } else {
                    // Переназначаем кошелёк основному
                    DB::table('virtual_wallets')
                        ->where('id', $sw->id)
                        ->update(['user_id' => $primary->id]);
                }
            }

            // 7. Абонементы и купоны
            DB::table('subscriptions')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);
            DB::table('coupons')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);

            // 8. Премиум-подписка
            $hasPremium = DB::table('premium_subscriptions')
                ->where('user_id', $primary->id)
                ->where('expires_at', '>', now())
                ->exists();
            if (!$hasPremium) {
                DB::table('premium_subscriptions')
                    ->where('user_id', $secondary->id)
                    ->update(['user_id' => $primary->id]);
            }

            // 9. Каналы уведомлений — без дублей по platform+chat_id
            $existingChannels = DB::table('user_notification_channels')
                ->where('user_id', $primary->id)
                ->pluck('chat_id')
                ->toArray();

            DB::table('user_notification_channels')
                ->where('user_id', $secondary->id)
                ->whereNotIn('chat_id', $existingChannels)
                ->update(['user_id' => $primary->id]);

            DB::table('user_notification_channels')
                ->where('user_id', $secondary->id)
                ->delete();

            // 10. Уведомления
            DB::table('user_notifications')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);

            // 11. Лист ожидания — без дублей по occurrence
            $primaryWaitlist = DB::table('occurrence_waitlist')
                ->where('user_id', $primary->id)
                ->pluck('occurrence_id')
                ->toArray();

            DB::table('occurrence_waitlist')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id', $primaryWaitlist)
                ->update(['user_id' => $primary->id]);

            DB::table('occurrence_waitlist')->where('user_id', $secondary->id)->delete();

            // 12. Команды — без дублей по event_team_id
            $primaryTeams = DB::table('event_team_members')
                ->where('user_id', $primary->id)
                ->pluck('event_team_id')
                ->toArray();

            DB::table('event_team_members')
                ->where('user_id', $secondary->id)
                ->whereNotIn('event_team_id', $primaryTeams)
                ->update(['user_id' => $primary->id]);

            DB::table('event_team_members')->where('user_id', $secondary->id)->delete();

            DB::table('event_team_invites')
                ->where('invited_user_id', $secondary->id)
                ->update(['invited_user_id' => $primary->id]);

            DB::table('event_team_applications')
                ->where('submitted_by_user_id', $secondary->id)
                ->update(['submitted_by_user_id' => $primary->id]);

            // 13. Дружба — без дублей
            $primaryFriends = DB::table('friendships')
                ->where('user_id', $primary->id)
                ->pluck('friend_id')
                ->toArray();

            DB::table('friendships')
                ->where('user_id', $secondary->id)
                ->whereNotIn('friend_id', $primaryFriends)
                ->update(['user_id' => $primary->id]);

            DB::table('friendships')->where('user_id', $secondary->id)->delete();

            // 14. Статистика
            DB::table('player_career_stats')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);
            DB::table('player_tournament_stats')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);
            DB::table('match_player_stats')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);
            DB::table('tournament_season_stats')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);

            // 15. Device tokens
            DB::table('device_tokens')->where('user_id', $secondary->id)->update(['user_id' => $primary->id]);

            // 16. Помечаем вторичный аккаунт
            $secondary->merged_into_user_id = $primary->id;
            $secondary->save();

            DB::table('users')->where('id', $secondary->id)->update(['deleted_at' => now()]);

            Log::info("UserMerge: #{$secondary->id} → #{$primary->id}", [
                'transferred'         => $result['transferred'],
                'cancelled_conflicts' => count($result['cancelled_conflicts']),
            ]);
        });

        return $result;
    }

    public function findDuplicates(): array
    {
        $result  = [];
        $seenIds = [];

        // 1. По совпадению телефона
        $phoneGroups = DB::select("
            SELECT phone, array_agg(id ORDER BY id) as user_ids
            FROM users
            WHERE phone IS NOT NULL AND phone != ''
              AND is_bot = false
              AND deleted_at IS NULL
              AND merged_into_user_id IS NULL
            GROUP BY phone
            HAVING COUNT(*) > 1
            ORDER BY COUNT(*) DESC, phone
        ");

        foreach ($phoneGroups as $g) {
            $ids   = array_map('intval', explode(',', trim($g->user_ids, '{}')));
            $users = User::whereIn('id', $ids)->orderBy('id')->get();

            foreach ($ids as $id) {
                $seenIds[$id] = true;
            }

            [$statsMap, $recommendedPrimaryId] = $this->buildGroupStats($users);

            $lastNames = $users->pluck('last_name')->filter()->unique();
            $level     = ($lastNames->count() === 1) ? 'red' : 'yellow';
            $label     = ($level === 'red') ? 'Фамилия + телефон' : 'Только телефон';

            $conflictsMap = [];
            foreach ($users as $u) {
                if ($u->id !== $recommendedPrimaryId) {
                    $conflictsMap[$u->id] = $this->upcomingConflicts($recommendedPrimaryId, $u->id);
                }
            }

            $result[] = [
                'level'               => $level,
                'label'               => $label,
                'phone'               => $g->phone,
                'users'               => $users,
                'stats'               => $statsMap,
                'recommended_primary' => $recommendedPrimaryId,
                'conflicts'           => $conflictsMap,
            ];
        }

        // 2. По совпадению имя + фамилия (исключая уже найденных по телефону)
        $excludeIds   = empty($seenIds) ? [0] : array_keys($seenIds);
        $placeholders = implode(',', $excludeIds);

        // Вычисляемое имя: first+last если заполнены, иначе поле name (как в User::getNameAttribute)
        $nameGroups = DB::select("
            SELECT name_key,
                   array_agg(id ORDER BY id) AS user_ids
            FROM (
                SELECT id,
                    LOWER(TRIM(
                        CASE
                            WHEN TRIM(COALESCE(first_name,'')) != '' OR TRIM(COALESCE(last_name,'')) != ''
                            THEN COALESCE(first_name,'') || ' ' || COALESCE(last_name,'')
                            ELSE COALESCE(name,'')
                        END
                    )) AS name_key
                FROM users
                WHERE is_bot = false
                  AND deleted_at IS NULL
                  AND merged_into_user_id IS NULL
                  AND id NOT IN ({$placeholders})
            ) sub
            WHERE name_key != '' AND name_key != 'пользователь'
            GROUP BY name_key
            HAVING COUNT(*) > 1
            ORDER BY COUNT(*) DESC, name_key
        ");

        foreach ($nameGroups as $g) {
            $ids   = array_map('intval', explode(',', trim($g->user_ids, '{}')));
            $users = User::whereIn('id', $ids)->orderBy('id')->get();

            foreach ($ids as $id) {
                $seenIds[$id] = true;
            }

            [$statsMap, $recommendedPrimaryId] = $this->buildGroupStats($users);

            $conflictsMap = [];
            foreach ($users as $u) {
                if ($u->id !== $recommendedPrimaryId) {
                    $conflictsMap[$u->id] = $this->upcomingConflicts($recommendedPrimaryId, $u->id);
                }
            }

            $result[] = [
                'level'               => 'yellow',
                'label'               => 'Имя + Фамилия',
                'phone'               => null,
                'users'               => $users,
                'stats'               => $statsMap,
                'recommended_primary' => $recommendedPrimaryId,
                'conflicts'           => $conflictsMap,
            ];
        }

        // 3. Перепутаны местами имя и фамилия (first_name одного = last_name другого и наоборот)
        $excludeIds3  = empty($seenIds) ? [0] : array_keys($seenIds);
        $placeholders3 = implode(',', $excludeIds3);

        $swappedPairs = DB::select("
            SELECT LEAST(a.id, b.id) AS id1, GREATEST(a.id, b.id) AS id2
            FROM users a
            JOIN users b
                ON  LOWER(TRIM(a.first_name)) = LOWER(TRIM(b.last_name))
                AND LOWER(TRIM(a.last_name))  = LOWER(TRIM(b.first_name))
                AND a.id < b.id
                AND TRIM(COALESCE(a.first_name,'')) != ''
                AND TRIM(COALESCE(a.last_name,''))  != ''
                AND TRIM(COALESCE(b.first_name,'')) != ''
                AND TRIM(COALESCE(b.last_name,''))  != ''
            WHERE a.is_bot = false AND a.deleted_at IS NULL AND a.merged_into_user_id IS NULL
              AND b.is_bot = false AND b.deleted_at IS NULL AND b.merged_into_user_id IS NULL
              AND a.id NOT IN ({$placeholders3})
              AND b.id NOT IN ({$placeholders3})
        ");

        // Объединяем пары в группы (один человек может совпасть с несколькими)
        $swapGroups = [];
        foreach ($swappedPairs as $pair) {
            $id1 = (int) $pair->id1;
            $id2 = (int) $pair->id2;
            $placed = false;
            foreach ($swapGroups as &$grp) {
                if (in_array($id1, $grp, true) || in_array($id2, $grp, true)) {
                    $grp = array_unique(array_merge($grp, [$id1, $id2]));
                    $placed = true;
                    break;
                }
            }
            unset($grp);
            if (!$placed) {
                $swapGroups[] = [$id1, $id2];
            }
        }

        foreach ($swapGroups as $ids) {
            // Пропускаем если кто-то из пары уже попал в seenIds
            if (array_intersect($ids, array_keys($seenIds))) continue;

            $users = User::whereIn('id', $ids)->orderBy('id')->get();

            foreach ($ids as $id) {
                $seenIds[$id] = true;
            }

            [$statsMap, $recommendedPrimaryId] = $this->buildGroupStats($users);

            $conflictsMap = [];
            foreach ($users as $u) {
                if ($u->id !== $recommendedPrimaryId) {
                    $conflictsMap[$u->id] = $this->upcomingConflicts($recommendedPrimaryId, $u->id);
                }
            }

            $result[] = [
                'level'               => 'yellow',
                'label'               => 'Имя ↔ Фамилия переставлены',
                'phone'               => null,
                'users'               => $users,
                'stats'               => $statsMap,
                'recommended_primary' => $recommendedPrimaryId,
                'conflicts'           => $conflictsMap,
            ];
        }

        return $result;
    }

    private function buildGroupStats($users): array
    {
        $statsMap = [];
        $scores   = [];
        foreach ($users as $u) {
            $stats            = $this->userStats($u->id);
            $statsMap[$u->id] = $stats;
            $scores[$u->id]   = $stats['registrations'] * 2
                              + $stats['payments'] * 3
                              + ($stats['profile_complete'] ? 5 : 0);
        }
        arsort($scores);
        return [$statsMap, array_key_first($scores)];
    }

    private function userStats(int $userId): array
    {
        $registrations = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->count();

        $cancelledRegistrations = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->whereRaw('(is_cancelled = true OR status = \'cancelled\')')
            ->count();

        $upcomingRows = DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->join('events as e', 'e.id', '=', 'er.event_id')
            ->where('er.user_id', $userId)
            ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
            ->where('eo.starts_at', '>', now('UTC'))
            ->orderBy('eo.starts_at')
            ->select('e.id as event_id', 'eo.id as occurrence_id', 'e.title', 'eo.starts_at', 'er.position')
            ->get();

        $lastRegAt = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->value('created_at');

        $payments = DB::table('payments')
            ->where('user_id', $userId)
            ->count();

        $cancelledPayments = DB::table('payments')
            ->where('user_id', $userId)
            ->where('status', 'cancelled')
            ->count();

        $walletBalance = DB::table('virtual_wallets')
            ->where('user_id', $userId)
            ->sum('balance_minor');

        $user = User::find($userId);
        $profileComplete = !is_null($user?->profile_completed_at);

        return [
            'registrations'          => $registrations,
            'cancelled_registrations' => $cancelledRegistrations,
            'upcoming'               => $upcomingRows->count(),
            'upcoming_rows'          => $upcomingRows,
            'last_reg_at'            => $lastRegAt,
            'payments'               => $payments,
            'cancelled_payments'     => $cancelledPayments,
            'wallet_balance'         => (int) $walletBalance,
            'profile_complete'       => $profileComplete,
        ];
    }

    /** Возвращает кол-во конфликтующих будущих записей между двумя пользователями */
    public function upcomingConflicts(int $primaryId, int $secondaryId): int
    {
        $primaryOccurrences = DB::table('event_registrations')
            ->where('user_id', $primaryId)
            ->whereRaw('(is_cancelled IS NULL OR is_cancelled = false)')
            ->pluck('occurrence_id')
            ->toArray();

        if (empty($primaryOccurrences)) {
            return 0;
        }

        return DB::table('event_registrations as er')
            ->join('event_occurrences as eo', 'eo.id', '=', 'er.occurrence_id')
            ->where('er.user_id', $secondaryId)
            ->whereIn('er.occurrence_id', $primaryOccurrences)
            ->whereRaw('(er.is_cancelled IS NULL OR er.is_cancelled = false)')
            ->where('eo.starts_at', '>', now('UTC'))
            ->count();
    }
}
