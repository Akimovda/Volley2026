<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Storage;
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

        // провайдеры
        'telegram_id',
        'telegram_username',
        'vk_id',
        'vk_email',
        'yandex_id',
        'yandex_email',
        'yandex_phone',

        // ВАЖНО: здесь лежит ТОЛЬКО "av-{id}"
        'profile_photo_path',
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
            'birth_date' => 'date',
            'beach_universal' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * URL превью аватара
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        $key = (string) ($this->profile_photo_path ?? '');

        if ($key === '') {
            return $this->defaultProfilePhotoUrl();
        }

        // ВСЕГДА thumb jpg
        $path = "avatars/thumbs/{$this->id}/{$key}.jpg";

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return $this->defaultProfilePhotoUrl();
    }

    protected function defaultProfilePhotoUrl(): string
    {
        $name = trim(collect(explode(' ', $this->name ?? 'User'))
            ->map(fn ($s) => mb_substr($s, 0, 1))
            ->join(' ')
        );

        return 'https://ui-avatars.com/api/?name='
            . urlencode($name)
            . '&color=7F9CF5&background=EBF4FF';
    }

    // ---------------- relations ----------------

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

    // ---------------- helpers ----------------

    public function displayName(): string
    {
        $last = trim((string) $this->last_name);
        $first = trim((string) $this->first_name);

        if ($last !== '' || $first !== '') {
            return trim($last . ' ' . $first);
        }

        if ($this->telegram_username) {
            return '@' . ltrim($this->telegram_username, '@');
        }

        return 'User #' . $this->id;
    }

    public function ageYears(): ?int
    {
        return $this->birth_date
            ? Carbon::parse($this->birth_date)->age
            : null;
    }
}
