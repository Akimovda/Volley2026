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
        'owner_id',
        'name',
        'address',
        'city_id',     // ✅ вместо city
        'note',        // ✅ новое
        'short_text',
        'long_text',
        'long_text2',  // ✅ второе поле (для “полного”)
        'lat',
        'lng',
        'booking_cancel_hours',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'booking_cancel_hours' => 'integer',
    ];

    public function city()
    {
        return $this->belongsTo(\App\Models\City::class, 'city_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function directions()
    {
        return $this->hasMany(LocationDirection::class);
    }

    /**
     * locations.timezone колонки нет — берём таймзону из связанного города
     * (cities.timezone), иначе дефолт проекта.
     */
    public function effectiveTimezone(): string
    {
        return $this->city?->timezone ?: 'Europe/Moscow';
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