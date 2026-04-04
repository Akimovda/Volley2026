<?php

namespace App\Services;

use App\Models\Event;
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