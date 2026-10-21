<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Куда площадка отправляет видео (RTMP-сервер + ключ). Не путать с BroadcastCourtOverride::stream_urls (ссылки для зрителей). */
class BroadcastDestination extends Model
{
    public const PLATFORMS = ['vk', 'rutube', 'youtube', 'telegram', 'custom'];
    public const YOUTUBE_SERVER = 'rtmp://a.rtmp.youtube.com/live2';

    protected $fillable = ['court_id', 'platform', 'label', 'server_url', 'stream_key', 'is_default'];

    protected $hidden = ['stream_key'];

    protected $casts = ['stream_key' => 'encrypted', 'is_default' => 'boolean'];

    public function court(): BelongsTo
    {
        return $this->belongsTo(EventCourt::class, 'court_id');
    }

    public static function validServer(string $url): bool
    {
        return (bool) preg_match('#^rtmps?://[^\s/]+(:\d+)?(/\S*)?$#i', $url) && mb_strlen($url) <= 300;
    }
}
