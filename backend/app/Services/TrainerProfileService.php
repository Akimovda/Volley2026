<?php

namespace App\Services;

use App\Models\TrainerProfile;
use App\Models\User;

class TrainerProfileService
{
    /**
     * Сохранение блока «Тренер» из формы профиля.
     * $enabled=false не удаляет профиль, а только скрывает его (is_public=false) —
     * данные и рейтинг сохраняются, включение обратно возвращает всё как было.
     */
    public function applyFromProfile(User $user, bool $enabled, array $data): ?TrainerProfile
    {
        if (!$enabled) {
            TrainerProfile::where('user_id', $user->id)->update(['is_public' => false]);
            return null;
        }

        return $this->upsert($user, [
            'specialization'   => $data['specialization'] ?? null,
            'bio'              => $this->cleanBio($data['bio'] ?? null),
            'experience_years' => $data['experience_years'] ?? null,
            'is_public'        => true,
        ]);
    }

    /** Очистка HTML из Trix: оставляем только безопасную разметку. */
    public function cleanBio(?string $html): ?string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return null;
        }

        $clean = \Mews\Purifier\Facades\Purifier::clean($html, 'default');

        return trim(strip_tags($clean)) === '' ? null : $clean;
    }

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
