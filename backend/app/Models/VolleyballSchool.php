<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class VolleyballSchool extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'organizer_id', 'slug', 'name', 'direction',
        'description', 'city', 'city_id', 'phone', 'email', 'website',
        'vk_url', 'tg_url', 'max_url',
        'logo_media_id', 'cover_media_id',
        'is_published',
    ];

    protected $casts = ['is_published' => 'boolean'];

    public function organizer()
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function cityModel()
    {
        return $this->belongsTo(\App\Models\City::class, 'city_id');
    }

    public function trainers()
    {
        return $this->hasMany(SchoolTrainer::class, 'school_id');
    }

    public function confirmedTrainers()
    {
        return $this->trainers()->where('status', SchoolTrainer::STATUS_CONFIRMED);
    }

    /**
     * volleyball_schools.timezone колонки нет — берём таймзону из связанного города,
     * иначе дефолт проекта (тот же паттерн, что Location::effectiveTimezone()).
     */
    public function effectiveTimezone(): string
    {
        return $this->cityModel?->timezone ?: 'Europe/Moscow';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')
            ->singleFile()
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(200)->height(200)->nonQueued();
            });

        $this->addMediaCollection('cover')
            ->singleFile()
            ->registerMediaConversions(function (Media $media) {
                $this->addMediaConversion('thumb')
                    ->width(800)->height(400)->nonQueued();
            });
    }

    public function getPublicUrlAttribute(): string
    {
        return route('volleyball_school.show', $this->slug);
    }
}
