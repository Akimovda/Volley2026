<?php

namespace App\Models;

use Carbon\Carbon;
use App\Services\ProfileUpdateGuard;
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
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use SoftDeletes;
    use InteractsWithMedia;

    // --------------------------------------------------
    // Mass assignment
    // --------------------------------------------------

    protected $fillable = [
        'first_name',
        'last_name',
        'patronymic',
        'phone',
        'birth_date',
        'city_id',
        'timezone',
        'gender',
        'height_cm',
        'classic_level',
        'beach_level',
        'beach_universal',
        'allow_user_contact',
        'avatar_media_id',      // 👈 ДОБАВИТЬ
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    
    protected $casts = [
        'payload' => 'array',
        'read_at' => 'datetime',
        'avatar_media_id' => 'integer',  // 👈 ДОБАВИТЬ
    ];

    protected function casts(): array
    {
        return [
            'birth_date'         => 'date',
            'beach_universal'    => 'boolean',
            'allow_user_contact' => 'boolean',
            'password'           => 'hashed',
        ];
    }

    public function notificationsInbox()
    {
        return $this->hasMany(\App\Models\UserNotification::class, 'user_id');
    }
    // --------------------------------------------------
    // Media Library
    // --------------------------------------------------

    public function registerMediaCollections(): void
    {
        // Коллекция photos - храним оригиналы
        $this->addMediaCollection('photos')
            ->useDisk('public');
        
        // Коллекция avatar - пока оставляем, но можем убрать если не нужна
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->useDisk('public');
    }

public function registerMediaConversions(Media $media = null): void
{
    // THUMB — объявляем путь, но перезаписываем вручную
    $this->addMediaConversion('thumb')
        ->format($media->mime_type === 'image/webp' ? 'webp' : 'jpg')
        ->fit(Fit::Crop, 360, 360)
        ->nonQueued()
        ->performOnCollections('photos');
}

    // --------------------------------------------------
    // Avatar URL (Jetstream compatible)
    // --------------------------------------------------

// app/Models/User.php

public function getProfilePhotoUrlAttribute(): string
{
    // 1. Аватар из галереи (через avatar_media_id)
    if ($this->avatar_media_id) {
        // Ищем медиа по ID в коллекции photos
        $media = $this->getMedia('photos')->firstWhere('id', $this->avatar_media_id);
        if ($media && $media->hasGeneratedConversion('thumb')) {
            return $media->getUrl('thumb');
        }
    }
    

    
    // 4. Дефолтный
    return $this->defaultProfilePhotoUrl();
}

    protected function defaultProfilePhotoUrl(): string
    {
        $initials = collect(explode(' ', $this->name ?? 'User'))
            ->map(fn ($s) => mb_substr($s, 0, 1))
            ->join(' ');

        return 'https://ui-avatars.com/api/?name='
            . urlencode($initials)
            . '&color=7F9CF5&background=EBF4FF';
    }

    // --------------------------------------------------
    // Relations
    // --------------------------------------------------

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

    // --------------------------------------------------
    // Roles
    // --------------------------------------------------

    public function isAdmin(): bool
    {
        return ProfileUpdateGuard::isAdmin($this);
    }
    
    public function isOrganizer(): bool
    {
        return ProfileUpdateGuard::isOrganizer($this);
    }
    
    public function isStaff(): bool
    {
        return ProfileUpdateGuard::isStaff($this);
    }

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------

    public function getNameAttribute($value): string
    {
        $first = trim((string) $this->first_name);
        $last  = trim((string) $this->last_name);

        if ($first !== '' || $last !== '') {
            return trim($first . ' ' . $last);
        }

        return 'Пользователь #' . $this->id;
    }

    public function displayName(): string
    {
        return $this->name;
    }

    public function ageYears(): ?int
    {
        return $this->birth_date
            ? Carbon::parse($this->birth_date)->age
            : null;
    }
    
    public function captainedEventTeams()
    {
        return $this->hasMany(\App\Models\EventTeam::class, 'captain_user_id');
    }
    
    public function eventTeamMembers()
    {
        return $this->hasMany(\App\Models\EventTeamMember::class);
    }
    
    public function submittedEventTeamApplications()
    {
        return $this->hasMany(\App\Models\EventTeamApplication::class, 'submitted_by_user_id');
    }
    
    public function reviewedEventTeamApplications()
    {
        return $this->hasMany(\App\Models\EventTeamApplication::class, 'reviewed_by_user_id');
    }
}