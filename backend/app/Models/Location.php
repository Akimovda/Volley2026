<?php
// location.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\City;
use Illuminate\Support\Str;


class Location extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'organizer_id',
        'name',
        'address',
        'city_id',     // ✅ вместо city
        'note',        // ✅ новое
        'short_text',
        'long_text',
        'long_text2',  // ✅ второе поле (для “полного”)
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function city()
    {
        return $this->belongsTo(\App\Models\City::class, 'city_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->useDisk(config('media-library.disk_name', 'public'))
            ->onlyKeepLatest(50); // ✅ лучше не 5, иначе “фото локации” будут постоянно обрезаться
    }
    public function getPublicSlugAttribute(): string
        {
            $s = Str::slug((string) $this->name, '-');
            return $s !== '' ? $s : 'location';
        }
        
        public function getPublicUrlAttribute(): string
        {
            return route('locations.show', [
                'location' => $this->id,
                'slug' => $this->public_slug,
            ]);
        }
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(480)
            ->height(320)
            ->sharpen(10)
            ->nonQueued();
    }
}