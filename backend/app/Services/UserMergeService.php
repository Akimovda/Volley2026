<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserMergeService
{
    /**
     * Слияние вторичного аккаунта в основной.
     * Всё переносится на $primary, $secondary помечается merged_into_user_id.
     */
    public function merge(User $primary, User $secondary): void
    {
        if ($primary->id === $secondary->id) {
            throw new \InvalidArgumentException('Нельзя объединить аккаунт с самим собой.');
        }

        DB::transaction(function () use ($primary, $secondary) {

            // 1. Провайдеры — привязываем если у основного нет
            if (empty($primary->telegram_id) && !empty($secondary->telegram_id)) {
                $primary->telegram_id = $secondary->telegram_id;
                $primary->telegram_username = $primary->telegram_username ?? $secondary->telegram_username;
            }
            if (empty($primary->vk_id) && !empty($secondary->vk_id)) {
                $primary->vk_id = $secondary->vk_id;
            }
            if (empty($primary->yandex_id) && !empty($secondary->yandex_id)) {
                $primary->yandex_id = $secondary->yandex_id;
            }

            // 2. Телефон — если у основного нет
            if (empty($primary->phone) && !empty($secondary->phone)) {
                $primary->phone = $secondary->phone;
            }

            $primary->save();

            // 3. Регистрации на мероприятия
            // Если у основного уже есть запись на то же occurrence — пропускаем
            $primaryOccurrences = DB::table('event_registrations')
                ->where('user_id', $primary->id)
                ->pluck('occurrence_id')
                ->toArray();

            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id', $primaryOccurrences)
                ->update(['user_id' => $primary->id]);

            // Остальные регистрации вторичного — отменяем
            DB::table('event_registrations')
                ->where('user_id', $secondary->id)
                ->update(['is_cancelled' => true]);

            // 4. Абонементы
            DB::table('subscriptions')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id]);

            // 5. Купоны
            DB::table('coupons')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id]);

            // 6. Уведомления
            DB::table('user_notifications')
                ->where('user_id', $secondary->id)
                ->update(['user_id' => $primary->id]);

            // 7. Waitlist
            DB::table('occurrence_waitlist')
                ->where('user_id', $secondary->id)
                ->whereNotIn('occurrence_id',
                    DB::table('occurrence_waitlist')->where('user_id', $primary->id)->pluck('occurrence_id')
                )
                ->update(['user_id' => $primary->id]);

            DB::table('occurrence_waitlist')
                ->where('user_id', $secondary->id)
                ->delete();

            // 8. Помечаем вторичный аккаунт
            $secondary->merged_into_user_id = $primary->id;
            $secondary->save();

            // Soft delete
            DB::table('users')->where('id', $secondary->id)
                ->update(['deleted_at' => now()]);

            Log::info("UserMerge: #{$secondary->id} → #{$primary->id}");
        });
    }

    /**
     * Поиск дублей по телефону и Фамилия+телефон
     */
    public function findDuplicates(): array
    {
        $result = [];

        // 🔴 Точные — совпадает Фамилия + телефон
        $exactDupes = DB::select("
            SELECT u1.id as id1, u2.id as id2
            FROM users u1
            JOIN users u2 ON u1.id < u2.id
                AND u1.last_name = u2.last_name
                AND u1.last_name IS NOT NULL
                AND u1.last_name != ''
                AND u1.phone = u2.phone
                AND u1.phone IS NOT NULL
                AND u1.phone != ''
            WHERE u1.is_bot = false AND u2.is_bot = false
                AND u1.deleted_at IS NULL AND u2.deleted_at IS NULL
                AND u1.merged_into_user_id IS NULL AND u2.merged_into_user_id IS NULL
        ");

        foreach ($exactDupes as $d) {
            $result[] = [
                'level'   => 'red',
                'label'   => 'Фамилия + телефон',
                'user1'   => User::find($d->id1),
                'user2'   => User::find($d->id2),
            ];
        }

        // 🟡 Вероятные — только телефон
        $phoneDupes = DB::select("
            SELECT u1.id as id1, u2.id as id2
            FROM users u1
            JOIN users u2 ON u1.id < u2.id
                AND u1.phone = u2.phone
                AND u1.phone IS NOT NULL
                AND u1.phone != ''
                AND (u1.last_name IS NULL OR u2.last_name IS NULL OR u1.last_name != u2.last_name)
            WHERE u1.is_bot = false AND u2.is_bot = false
                AND u1.deleted_at IS NULL AND u2.deleted_at IS NULL
                AND u1.merged_into_user_id IS NULL AND u2.merged_into_user_id IS NULL
        ");

        foreach ($phoneDupes as $d) {
            // Не добавляем если уже есть в exact
            $alreadyAdded = collect($result)->contains(fn($r) =>
                ($r['user1']->id === $d->id1 && $r['user2']->id === $d->id2)
            );
            if (!$alreadyAdded) {
                $result[] = [
                    'level' => 'yellow',
                    'label' => 'Только телефон',
                    'user1' => User::find($d->id1),
                    'user2' => User::find($d->id2),
                ];
            }
        }

        return $result;
    }
}
