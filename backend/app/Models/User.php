<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;


class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',

        // профиль
        'first_name',
        'last_name',
        'patronymic',
        'phone',
        'birth_date',
        'city_id',
        'gender',
        'height_cm',
        'classic_level',
        'beach_level',
        'beach_universal',

        // роли/интеграции
        'role',
        'telegram_id',
        'telegram_username',
        'vk_id',
        'vk_email',
        'yandex_id',
        'yandex_email',
        'yandex_phone',
        'yandex_avatar',

    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = [
        'profile_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'birth_date' => 'date',
            'password' => 'hashed',
            'beach_universal' => 'boolean',
        ];
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function classicPositions(): HasMany
    {
        return $this->hasMany(UserClassicPosition::class);
    }

    public function beachZones(): HasMany
    {
        return $this->hasMany(UserBeachZone::class);
    }

    public function displayName(): string
    {
        $last = trim((string) $this->last_name);
        $first = trim((string) $this->first_name);

        if ($last !== '' || $first !== '') {
            return trim($last . ' ' . $first);
        }

        $tg = trim((string) $this->telegram_username);
        if ($tg !== '') {
            return '@' . ltrim($tg, '@');
        }

        return 'User #' . $this->id;
    }

    public function ageYears(): ?int
    {
        if (empty($this->birth_date)) {
            return null;
        }

        return Carbon::parse($this->birth_date)->age;
    }
}
