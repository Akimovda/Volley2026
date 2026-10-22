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

    public const MUSIC_MODES = ['splash', 'set_summary', 'match_summary'];
    public const DEFAULT_MUSIC_PATH = 'assets/broadcast/volleyclub_calm_loop.mp3';

    /** Музыка оверлея (theme.music) с дефолтами и ограничениями. */
    public function musicConfig(): array
    {
        $m = (array) (((array) $this->theme)['music'] ?? []);
        $modes = array_key_exists('modes', $m) && is_array($m['modes'])
            ? array_values(array_intersect(self::MUSIC_MODES, $m['modes']))
            : self::MUSIC_MODES;

        return [
            'enabled' => (bool) ($m['enabled'] ?? true),
            'track'   => ($m['track'] ?? 'default') === 'custom' ? 'custom' : 'default',
            'modes'   => $modes,
            'volume'  => max(0, min(100, (int) ($m['volume'] ?? 60))),
            'fade_ms' => max(0, min(10000, (int) ($m['fade_ms'] ?? 2000))),
        ];
    }

    /** Адрес трека: свой (если выбран и загружен), иначе стандартный. */
    public function musicUrl(): string
    {
        if ($this->musicConfig()['track'] === 'custom' && $this->exists && ($m = $this->getFirstMedia('stream_music'))) {
            return $m->getUrl() . '?v=' . $m->id;
        }

        return asset(self::DEFAULT_MUSIC_PATH);
    }

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

        $this->addMediaCollection('stream_music')
            ->singleFile()
            ->useDisk('public')
            ->acceptsMimeTypes(['audio/mpeg', 'audio/mp3', 'audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-aac']);
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
