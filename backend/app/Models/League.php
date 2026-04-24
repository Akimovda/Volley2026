<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class League extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'organizer_id',
        'name',
        'slug',
        'direction',
        'description',
        'vk',
        'telegram',
        'max_messenger',
        'website',
        'phone',
        'status',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_ARCHIVED = 'archived';

    public const DIRECTION_CLASSIC = 'classic';
    public const DIRECTION_BEACH   = 'beach';

    /* ---------- relations ---------- */

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(TournamentSeason::class, 'league_id')->orderByDesc('starts_at');
    }

    public function activeSeason(): HasOne
    {
        return $this->hasOne(TournamentSeason::class, 'league_id')
                    ->where('status', TournamentSeason::STATUS_ACTIVE)
                    ->latestOfMany('starts_at');
    }

    /* ---------- media ---------- */

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->registerMediaConversions(function (\Spatie\MediaLibrary\MediaCollections\Models\Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(200)->height(200)->sharpen(5);
            });
    }

    public function getLogoUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('logo');
        if (!$media) return null;
        return $media->hasGeneratedConversion('thumb')
            ? $media->getUrl('thumb')
            : $media->getUrl();
    }

    /* ---------- helpers ---------- */

    public function cfg(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function seasonsCount(): int
    {
        return $this->seasons()->count();
    }
}
