<?php

namespace App\Services;

use App\Models\Event;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class EventTrainerService
{

    /*
    |--------------------------------------------------------------------------
    | NORMALIZE TRAINER IDS
    |--------------------------------------------------------------------------
    */

    public function normalizeTrainerIds(array $data, bool $needTrainers): array
    {
        $trainerIds = $data['trainer_user_ids'] ?? [];

        // legacy single -> add to array
        $legacyTrainerId = isset($data['trainer_user_id'])
            ? (int)$data['trainer_user_id']
            : 0;

        if ($legacyTrainerId > 0) {
            if (is_array($trainerIds)) {
                $trainerIds[] = $legacyTrainerId;
            } else {
                $trainerIds = [$legacyTrainerId];
            }
        }

        if (is_string($trainerIds)) {
            $trainerIds = [$trainerIds];
        }

        $trainerIds = is_array($trainerIds)
            ? array_values(array_unique(array_map('intval', $trainerIds)))
            : [];

        $trainerIds = array_values(array_filter(
            $trainerIds,
            fn($id) => $id > 0
        ));

        if ($needTrainers && count($trainerIds) === 0) {
            return [
                'trainerIds' => [],
                'errors' => [
                    'trainer_user_ids' => [
                        'Выберите минимум одного тренера.'
                    ]
                ],
            ];
        }

        if (count($trainerIds) > 0) {

            $cnt = User::query()
                ->whereIn('id', $trainerIds)
                ->count();

            if ($cnt !== count($trainerIds)) {

                return [
                    'trainerIds' => [],
                    'errors' => [
                        'trainer_user_ids' => [
                            'Некоторые тренеры не найдены.'
                        ]
                    ],
                ];
            }
        }

        return [
            'trainerIds' => $trainerIds,
            'errors' => [],
        ];
    }


    /**
     * Организатор отметил «Тренер — я», а анкеты тренера нет — создаём её автоматически (публичную, без текста, возвращает true при создании:
     * специализацию, опыт и «О себе» можно дополнить в профиле). Без анкеты человек не смог бы быть тренером события.
     */
    public function ensureSelfTrainerProfile(array $trainerIds, ?User $user): bool
    {
        if (!$user || !in_array((int) $user->id, array_map('intval', $trainerIds), true)) {
            return false;
        }
        if (!Schema::hasTable('trainer_profiles')) {
            return false;
        }
        $profile = TrainerProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['is_public' => true]
        );
        if ($profile->wasRecentlyCreated) {
            // подсказка «дополните анкету» на следующей странице (events.show / управление событием)
            session()->flash('trainer_profile_created', true);
            return true;
        }
        return false;
    }


    /*
    |--------------------------------------------------------------------------
    | SYNC TRAINERS
    |--------------------------------------------------------------------------
    */

    public function sync(Event $event, bool $needTrainers, array $trainerIds): void
    {
        if (
            method_exists($event, 'trainers') &&
            Schema::hasTable('event_trainers')
        ) {
            $event->trainers()->sync(
                $needTrainers ? $trainerIds : []
            );
        }
    }
}