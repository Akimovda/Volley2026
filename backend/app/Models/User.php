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
	use App\Traits\HasPremium;
	
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
		use HasPremium;
		
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
			// Коллекция photos - храним оригиналы (как было)
			$this->addMediaCollection('photos')
			->useDisk('public');
			
			// НОВАЯ коллекция для фото мероприятий
			$this->addMediaCollection('event_photos')
			->useDisk('public');
			
			// Коллекция avatar - как было
			$this->addMediaCollection('avatar')
			->singleFile()
			->useDisk('public');
		// Логотип школы (1:1)
		$this->addMediaCollection('school_logo')
		->useDisk('public');

		// Обложка школы (16:9)
		$this->addMediaCollection('school_cover')
		->useDisk('public');
		}
		
		public function registerMediaConversions(Media $media = null): void
		{
			
			// THUMB — как было, для коллекции photos
			$this->addMediaConversion('thumb')
			->format($media && $media->mime_type === 'image/webp' ? 'webp' : 'jpg')
			->fit(Fit::Crop, 360, 360)
			->nonQueued()
			->performOnCollections('photos');
			
			// НОВАЯ конверсия для фото мероприятий
			$this->addMediaConversion('event_thumb')
			->format($media && $media->mime_type === 'image/webp' ? 'webp' : 'jpg')
			->fit(Fit::Crop, 640, 360)  // 👈 было '>fit', исправил на '->fit'
			->nonQueued()
			->performOnCollections('event_photos');

			// Логотип школы (1:1)
			$this->addMediaConversion('school_logo_thumb')
			->format($media && $media->mime_type === 'image/webp' ? 'webp' : 'jpg')
			->fit(Fit::Crop, 360, 360)
			->nonQueued()
			->performOnCollections('school_logo');

			// Обложка школы (16:9)
			$this->addMediaConversion('school_cover_thumb')
			->format($media && $media->mime_type === 'image/webp' ? 'webp' : 'jpg')
			->fit(Fit::Crop, 640, 360)
			->nonQueued()
			->performOnCollections('school_cover');
			
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

		public function staffAssignment(): \Illuminate\Database\Eloquent\Relations\HasOne
		{
			return $this->hasOne(\App\Models\StaffAssignment::class, 'staff_user_id');
		}

		public function getOrganizerIdForStaff(): ?int
		{
			if ($this->isStaff()) {
				return $this->staffAssignment?->organizer_id;
			}
			return ($this->isOrganizer() || $this->isAdmin()) ? $this->id : null;
		}

		public function staffMembers(): \Illuminate\Database\Eloquent\Relations\HasMany
		{
			return $this->hasMany(\App\Models\StaffAssignment::class, 'organizer_id');
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
			

if (!empty($value)) {
return $value;
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
		
		// ──────────────────────────────────────────────────────
    // Premium
    // ──────────────────────────────────────────────────────

    public function activePremium(): ?\App\Models\PremiumSubscription
    {
        return \App\Models\PremiumSubscription::where('user_id', $this->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();
    }

    public function isPremium(): bool
    {
        return \App\Models\PremiumSubscription::where('user_id', $this->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    // ──────────────────────────────────────────────────────
    // Друзья (для всех)
    // ──────────────────────────────────────────────────────

    public function friends()
    {
        return $this->belongsToMany(
            User::class, 'friendships', 'user_id', 'friend_id'
        );
    }

    public function isFriendWith(int $userId): bool
    {
        return \App\Models\Friendship::where('user_id', $this->id)
            ->where('friend_id', $userId)
            ->exists();
    }

    // ──────────────────────────────────────────────────────
    // Гости профиля (просмотр — только Premium)
    // ──────────────────────────────────────────────────────

    /** Записать визит (всегда, в фоне) */
    public function recordVisit(int $visitorUserId): void
    {
        if ($visitorUserId === $this->id) return;

        \App\Models\ProfileVisit::updateOrCreate(
            ['profile_user_id' => $this->id, 'visitor_user_id' => $visitorUserId],
            ['visited_at' => now()]
        );
    }

    /** Получить гостей за N дней (только для Premium) */
    public function recentVisitors(int $days = 7)
    {
        return \App\Models\ProfileVisit::where('profile_user_id', $this->id)
            ->where('visited_at', '>=', now()->subDays($days))
            ->orderByDesc('visited_at')
            ->with('visitor')
            ->get()
            ->unique('visitor_user_id');
    }

    // ──────────────────────────────────────────────────────
    // Organizer Pro
    // ──────────────────────────────────────────────────────

    public function isOrganizerPro(): bool
    {
        return \App\Models\OrganizerSubscription::where('user_id', $this->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function getFormattedPhoneAttribute(): string
		{
			if (!$this->phone) return '';
			
			$phone = preg_replace('/[^0-9]/', '', $this->phone);
			
			if (strlen($phone) === 11) {
				return '+' . substr($phone, 0, 1) . ' (' . substr($phone, 1, 3) . ') ' . 
				substr($phone, 4, 3) . '-' . substr($phone, 7, 2) . '-' . substr($phone, 9, 2);
			}
			
			if (strlen($phone) === 10) {
				return '+7 (' . substr($phone, 0, 3) . ') ' . 
				substr($phone, 3, 3) . '-' . substr($phone, 6, 2) . '-' . substr($phone, 8, 2);
			}
			
			return $this->phone;
		}	
		
	}	