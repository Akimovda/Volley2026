<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

// Spatie Media Library
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens;
    use HasFactory;

    /**
     * Jetstream compatibility:
     * Jetstream ожидает accessor profile_photo_url, и трейт HasProfilePhoto это поддерживает.
     * Мы переопределяем getProfilePhotoUrlAttribute() ниже, чтобы брать аватар из MediaLibrary.
     */
    use HasProfilePhoto;

    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;

    // Spatie Media Library trait
    use InteractsWithMedia;

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
        'telegram_phone',
        'vk_id',
        'vk_email',
        'vk_phone',
        'yandex_id',
        'yandex_email',
        'yandex_phone',

        /**
         * Legacy: "av-{id}"
         * Оставляем как fallback на старую систему хранения (avatars/thumbs/...),
         * пока не мигрировали полностью на MediaLibrary.
         */
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

    // ------------------------------------------------------------------
    // Spatie Media Library
    // ------------------------------------------------------------------

    /**
     * Коллекции:
     * - photos: галерея пользователя (много файлов)
     * - avatar: текущий активный аватар (singleFile)
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk('public');

        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk('public');
    }

    /**
     * Конверсии:
     * ВАЖНО: в spatie/image v3+ fit() принимает enum Fit, а не строку.
     * Поэтому Fit::Crop вместо 'crop' — это и фиксит твою ошибку TypeError.
     */
    public function registerMediaConversions(Media $media = null): void
    {
        // превью для галереи и для отображения аватара в списках
        $this->addMediaConversion('thumb')
            ->fit(Fit::Crop, 200, 200)
            ->performOnCollections('photos', 'avatar');

        // основной размер аватара (512x512)
        $this->addMediaConversion('avatar')
            ->fit(Fit::Crop, 512, 512)
            ->performOnCollections('avatar');
    }

    // ------------------------------------------------------------------
    // Avatar URL (единая точка правды для UI/Jetstream)
    // ------------------------------------------------------------------

    /**
     * Приоритет:
     * 1) MediaLibrary: avatar (thumb -> avatar -> original)
     * 2) Legacy: avatars/thumbs/{id}/{profile_photo_path}.jpg
     * 3) Default ui-avatars
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        // 1) MediaLibrary (активный аватар)
        try {
            $m = $this->getFirstMedia('avatar');

            if ($m) {
                // Если конверсия уже сгенерена — отдадим её
                if (method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('thumb')) {
                    return $m->getUrl('thumb');
                }

                if (method_exists($m, 'hasGeneratedConversion') && $m->hasGeneratedConversion('avatar')) {
                    return $m->getUrl('avatar');
                }

                // fallback на оригинал
                return $m->getUrl();
            }
        } catch (\Throwable $e) {
            // не валим профиль: тихо идём на legacy fallback
        }

        // 2) Legacy fallback (твоя текущая логика)
        $key = (string) ($this->profile_photo_path ?? '');
        if ($key !== '') {
            $path = "avatars/thumbs/{$this->id}/{$key}.jpg";
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->url($path);
            }
        }

        // 3) Default
        return $this->defaultProfilePhotoUrl();
    }

    protected function defaultProfilePhotoUrl(): string
    {
        $name = trim(
            collect(explode(' ', $this->name ?? 'User'))
                ->map(fn ($s) => mb_substr($s, 0, 1))
                ->join(' ')
        );

        return 'https://ui-avatars.com/api/?name='
            . urlencode($name)
            . '&color=7F9CF5&background=EBF4FF';
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

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

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

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
