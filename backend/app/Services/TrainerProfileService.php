<?php

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\User;

class TrainerProfileService
{
    public function upsert(User $user, array $data): TrainerProfile
    {
        return TrainerProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'specialization'   => $data['specialization'] ?? null,
                'bio'              => $data['bio'] ?? null,
                'experience_years' => $data['experience_years'] ?? null,
                'is_public'        => $data['is_public'] ?? false,
            ]
        );
    }
}
