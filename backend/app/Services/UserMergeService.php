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

            // 1. Провайдеры — уникальные поля: сначала обнуляем у secondary, потом ставим на primary
            $uniqueProviderFields = ['telegram_id', 'vk_id', 'yandex_id'];
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

            Log::info("UserMerge: #{$secondary->id} → #{$primary->id}");
        });
    }

    public function findDuplicates(): array
    {
        // Группируем по телефону — находим все аккаунты с одинаковым номером
        $groups = DB::select("
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

        $result = [];

        foreach ($groups as $g) {
            // PostgreSQL возвращает array_agg как строку вида "{1,2,3}"
            $ids = array_map('intval', explode(',', trim($g->user_ids, '{}')));
            $users = User::whereIn('id', $ids)->orderBy('id')->get();

            // Считаем статистику и определяем рекомендованного primary
            $statsMap = [];
            $scores   = [];
            foreach ($users as $u) {
                $stats = $this->userStats($u->id);
                $statsMap[$u->id] = $stats;
                $scores[$u->id]   = $stats['registrations'] * 2
                                  + $stats['payments'] * 3
                                  + ($stats['profile_complete'] ? 5 : 0);
            }
            arsort($scores);
            $recommendedPrimaryId = array_key_first($scores);

            // Определяем уровень: если у всех одинаковая фамилия — красный
            $lastNames = $users->pluck('last_name')->filter()->unique();
            $level = ($lastNames->count() === 1) ? 'red' : 'yellow';
            $label = ($level === 'red') ? 'Фамилия + телефон' : 'Только телефон';

            $result[] = [
                'level'               => $level,
                'label'               => $label,
                'phone'               => $g->phone,
                'users'               => $users,
                'stats'               => $statsMap,
                'recommended_primary' => $recommendedPrimaryId,
            ];
        }

        return $result;
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
