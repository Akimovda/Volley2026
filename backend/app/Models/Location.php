<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Location extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'organizer_id',
        'name',
        'address',
        'city',
        'timezone',
        'note',
        'short_text',
        'long_text',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk(config('media-library.disk_name', 'public'))
            ->onlyKeepLatest(5);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Миниатюра для админки/превью
        $this->addMediaConversion('thumb')
            ->width(480)
            ->height(320)
            ->sharpen(10)
            ->nonQueued();
    }
}
