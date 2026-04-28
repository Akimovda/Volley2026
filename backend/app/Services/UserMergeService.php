<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserMergeService
{
    public function merge(User $primary, User $secondary): void
    {
        if ($primary->id === $secondary->id) {
            throw new \InvalidArgumentException('Нельзя объединить аккаунт с самим собой.');
        }

        DB::transaction(function () use ($primary, $secondary) {

            // 1. Провайдеры
            foreach (['telegram_id', 'telegram_username', 'vk_id', 'yandex_id', 'yandex_phone', 'telegram_phone', 'vk_phone'] as $f) {
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

            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id', $primaryOccurrences)
                ->update(['user_id' => $primary->id]);

            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->update(['is_cancelled' => true]);

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
                ->where('ends_at', '>', now())
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

            // 12. Команды — без дублей по team_id
            $primaryTeams = DB::table('event_team_members')
                ->where('user_id', $primary->id)
                ->pluck('team_id')
                ->toArray();

            DB::table('event_team_members')
                ->where('user_id', $secondary->id)
                ->whereNotIn('team_id', $primaryTeams)
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

            Log::info("UserMerge: #{$secondary->id} → #{$primary->id}");
        });
    }

    public function findDuplicates(): array
    {
        $result = [];

        // 🔴 Точные — Фамилия + телефон
        $exactDupes = DB::select("
            SELECT u1.id as id1, u2.id as id2
            FROM users u1
            JOIN users u2 ON u1.id < u2.id
                AND u1.last_name = u2.last_name
                AND u1.last_name IS NOT NULL AND u1.last_name != ''
                AND u1.phone = u2.phone
                AND u1.phone IS NOT NULL AND u1.phone != ''
            WHERE u1.is_bot = false AND u2.is_bot = false
                AND u1.deleted_at IS NULL AND u2.deleted_at IS NULL
                AND u1.merged_into_user_id IS NULL AND u2.merged_into_user_id IS NULL
        ");

        foreach ($exactDupes as $d) {
            $result[] = $this->buildDupEntry('red', 'Фамилия + телефон', $d->id1, $d->id2);
        }

        // 🟡 Вероятные — только телефон
        $phoneDupes = DB::select("
            SELECT u1.id as id1, u2.id as id2
            FROM users u1
            JOIN users u2 ON u1.id < u2.id
                AND u1.phone = u2.phone
                AND u1.phone IS NOT NULL AND u1.phone != ''
                AND (u1.last_name IS NULL OR u2.last_name IS NULL OR u1.last_name != u2.last_name)
            WHERE u1.is_bot = false AND u2.is_bot = false
                AND u1.deleted_at IS NULL AND u2.deleted_at IS NULL
                AND u1.merged_into_user_id IS NULL AND u2.merged_into_user_id IS NULL
        ");

        $addedPairs = collect($result)->map(fn($r) => $r['user1']->id . '-' . $r['user2']->id)->toArray();

        foreach ($phoneDupes as $d) {
            if (!in_array($d->id1 . '-' . $d->id2, $addedPairs)) {
                $result[] = $this->buildDupEntry('yellow', 'Только телефон', $d->id1, $d->id2);
            }
        }

        return $result;
    }

    private function buildDupEntry(string $level, string $label, int $id1, int $id2): array
    {
        $u1 = User::find($id1);
        $u2 = User::find($id2);

        $stats1 = $this->userStats($id1);
        $stats2 = $this->userStats($id2);

        // Рекомендуем основным того, у кого больше данных
        $score1 = $stats1['registrations'] * 2 + $stats1['payments'] * 3 + ($stats1['profile_complete'] ? 5 : 0);
        $score2 = $stats2['registrations'] * 2 + $stats2['payments'] * 3 + ($stats2['profile_complete'] ? 5 : 0);

        $recommended_primary = $score1 >= $score2 ? $id1 : $id2;

        return [
            'level'               => $level,
            'label'               => $label,
            'user1'               => $u1,
            'user2'               => $u2,
            'stats1'              => $stats1,
            'stats2'              => $stats2,
            'recommended_primary' => $recommended_primary,
        ];
    }

    private function userStats(int $userId): array
    {
        $registrations = DB::table('event_registrations')
            ->where('user_id', $userId)
            ->whereRaw('is_cancelled IS NULL OR is_cancelled = false')
            ->count();

        $payments = DB::table('payments')
            ->where('user_id', $userId)
            ->count();

        $walletBalance = DB::table('virtual_wallets')
            ->where('user_id', $userId)
            ->sum('balance_minor');

        $user = User::find($userId);
        $profileComplete = !is_null($user?->profile_completed_at);

        return [
            'registrations'  => $registrations,
            'payments'       => $payments,
            'wallet_balance' => (int) $walletBalance,
            'profile_complete' => $profileComplete,
        ];
    }
}
