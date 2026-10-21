<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BroadcastSetting extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const SUMMARY_EVERY_SET = 'every_set';
    public const SUMMARY_MATCH_END = 'match_end';

    public const DEFAULT_SET_SECONDS   = 15;
    public const DEFAULT_MATCH_SECONDS = 60;

    protected $fillable = [
        'event_id', 'occurrence_id', 'theme', 'splash', 'summary_mode',
        'set_summary_seconds', 'match_summary_seconds', 'aspect',
    ];

    protected $casts = [
        'theme' => 'array',
        'splash' => 'array',
        'set_summary_seconds' => 'integer',
        'match_summary_seconds' => 'integer',
    ];

    /** Настройки тура, иначе события; нет записи — дефолты (без сохранения). */
    public static function resolve(int $eventId, ?int $occurrenceId): self
    {
        $q = static::where('event_id', $eventId);
        $row = $occurrenceId !== null
            ? ((clone $q)->where('occurrence_id', $occurrenceId)->first() ?? (clone $q)->whereNull('occurrence_id')->first())
            : $q->whereNull('occurrence_id')->first();

        return $row ?? new static([
            'event_id' => $eventId,
            'occurrence_id' => $occurrenceId,
            'summary_mode' => self::SUMMARY_EVERY_SET,
            'set_summary_seconds' => self::DEFAULT_SET_SECONDS,
            'match_summary_seconds' => self::DEFAULT_MATCH_SECONDS,
            'aspect' => '16:9',
        ]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('stream_bg')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('hd')
            ->format('webp')
            ->fit(Fit::Crop, 1920, 1080)
            ->nonQueued()
            ->performOnCollections('stream_bg');
    }
}
