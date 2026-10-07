<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Support\AdminAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * «Пустышки» — аккаунты без точек входа, созданные организатором/админом
 * для составов команд и игр. Позже сливаются с реальным игроком через UserMergeService.
 */
final class PlaceholderUserService
{
    public function __construct(private UserMergeService $mergeService) {}

    public function create(User $actor, array $data): User
    {
        $first = trim((string) $data['first_name']);
        $last  = trim((string) $data['last_name']);

        $user = new User();
        $user->forceFill([
            'name'               => trim($first . ' ' . $last),
            'first_name'         => $first,
            'last_name'          => $last,
            'gender'             => $data['gender'] ?? null,
            'phone'              => $data['phone'] ?? null,
            // email NOT NULL + unique; войти нельзя — нет провайдеров, пароль случайный
            'email'              => 'placeholder-' . Str::uuid() . '@placeholder.local',
            'password'           => Hash::make(Str::random(40)),
            'role'               => 'user',
            'is_placeholder'     => true,
            'created_by_user_id' => $actor->id,
            'allow_user_contact' => false,
            'locale'             => 'ru',
        ])->save();

        $this->audit($actor, 'user.placeholder.create', $user->id, ['name' => $user->name]);

        return $user;
    }

    /** Пустышки, видимые actor'у в личном разделе: свои (админ — все). */
    public function listFor(User $actor)
    {
        return User::query()
            ->where('is_placeholder', true)
            ->when(!$actor->isAdmin(), fn ($q) => $q->where('created_by_user_id', $actor->id))
            ->orderByDesc('id')
            ->get();
    }

    /** Есть ли у пустышки история, мешающая простому удалению. */
    public function hasHistory(User $placeholder): bool
    {
        return DB::table('event_registrations')->where('user_id', $placeholder->id)->exists()
            || DB::table('event_team_members')->where('user_id', $placeholder->id)->exists()
            || DB::table('occurrence_waitlist')->where('user_id', $placeholder->id)->exists();
    }

    public function delete(User $actor, User $placeholder): void
    {
        $placeholder->delete(); // SoftDeletes
        $this->audit($actor, 'user.placeholder.delete', $placeholder->id, ['name' => $placeholder->name]);
    }

    /**
     * Реальные пользователи, похожие на пустышку: тот же телефон,
     * то же ФИО или перепутанные имя/фамилия.
     */
    public function suggestMatches(User $placeholder)
    {
        $first = mb_strtolower(trim((string) $placeholder->first_name));
        $last  = mb_strtolower(trim((string) $placeholder->last_name));

        return $this->realUsers()
            ->where(function ($q) use ($placeholder, $first, $last) {
                if (!empty($placeholder->phone)) {
                    $q->orWhere('phone', $placeholder->phone);
                }
                if ($first !== '' && $last !== '') {
                    $q->orWhereRaw('(LOWER(TRIM(first_name)) = ? AND LOWER(TRIM(last_name)) = ?)', [$first, $last])
                      ->orWhereRaw('(LOWER(TRIM(first_name)) = ? AND LOWER(TRIM(last_name)) = ?)', [$last, $first]);
                }
                // заведомо ложное условие, если все ветки пустые
                $q->orWhereRaw('1 = 0');
            })
            ->orderBy('id')
            ->limit(20)
            ->get();
    }

    public function searchRealUsers(string $q)
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return collect();
        }
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $q) . '%';

        return $this->realUsers()
            ->where(function ($w) use ($like, $q) {
                $w->whereRaw("(coalesce(last_name,'') || ' ' || coalesce(first_name,'')) ILIKE ?", [$like])
                  ->orWhereRaw("(coalesce(first_name,'') || ' ' || coalesce(last_name,'')) ILIKE ?", [$like])
                  ->orWhere('name', 'ILIKE', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('id', (int) $q)->orWhere('phone', 'ILIKE', '%' . $q . '%');
                }
            })
            ->orderBy('last_name')->orderBy('first_name')
            ->limit(15)
            ->get();
    }

    /** Слить пустышку в реального игрока (primary = реальный). */
    public function mergeInto(User $actor, User $placeholder, User $real): array
    {
        if (!$placeholder->isPlaceholderManagedBy($actor)) {
            throw new \DomainException('Нет прав на этот аккаунт-пустышку.');
        }
        if ($real->is_placeholder || $real->is_bot || $real->deleted_at !== null || $real->merged_into_user_id !== null) {
            throw new \DomainException('Объединять можно только с реальным активным игроком.');
        }

        $result = $this->mergeService->merge($real, $placeholder);
        $this->audit($actor, 'user.placeholder.merge', $real->id, [
            'placeholder_id' => $placeholder->id,
            'transferred'    => $result['transferred'] ?? 0,
        ]);

        return $result;
    }

    private const FILL_FIELDS = ['patronymic', 'phone', 'birth_date', 'city_id', 'gender', 'height_cm', 'classic_level', 'beach_level'];

    /** Что пустышка принесёт игроку: счётчики данных. */
    public function summary(User $u): array
    {
        $stats = $this->mergeService->statsFor($u->id);

        return [
            'registrations' => $stats['registrations'],
            'upcoming'      => $stats['upcoming'],
            'teams'         => DB::table('event_team_members')->where('user_id', $u->id)->count(),
            'matches'       => DB::table('match_player_stats')->where('user_id', $u->id)->count(),
        ];
    }

    /**
     * Поля профиля, которые игрок получит от пустышки: переносятся ТОЛЬКО в пустые поля игрока,
     * заполненные данные игрока никогда не перезаписываются.
     *
     * @return string[] ключи полей (profile.* не используем — подписи в placeholders.field_*)
     */
    public function fieldsToFill(User $placeholder, User $real): array
    {
        $out = [];
        foreach (self::FILL_FIELDS as $f) {
            $ph = $placeholder->$f;
            $rl = $real->$f;
            if (($ph !== null && $ph !== '') && ($rl === null || $rl === '')) {
                $out[] = $f;
            }
        }
        return $out;
    }

    public function conflicts(User $placeholder, User $real): int
    {
        return $this->mergeService->upcomingConflicts($real->id, $placeholder->id);
    }

    private function realUsers()
    {
        return User::query()
            ->where('is_placeholder', false)
            ->where('is_bot', false)
            ->whereNull('merged_into_user_id');
    }

    private function audit(User $actor, string $action, int $entityId, array $meta): void
    {
        try {
            AdminAuditLogger::log($action, 'user', $entityId, $meta);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
