<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/** Оформление команды на трансляции: цвет + логотип (Media team_logo). Логотип по умолчанию — аватар организатора. */
class BroadcastTeamStyle extends Model implements HasMedia
{
    use InteractsWithMedia;

    /** Контрастные цвета для автоназначения командам (без повторов внутри события). */
    public const PALETTE = [
        '#E53935', '#1E88E5', '#43A047', '#FB8C00', '#8E24AA',
        '#00ACC1', '#FDD835', '#D81B60', '#6D4C41', '#546E7A',
    ];

    protected $fillable = ['event_team_id', 'color', 'short_name'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(EventTeam::class, 'event_team_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('team_logo')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('logo')
            ->format('webp')
            ->fit(Fit::Contain, 256, 256)
            ->nonQueued()
            ->performOnCollections('team_logo');
    }
}
