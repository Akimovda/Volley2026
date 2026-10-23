<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Постоянный канал трансляции организатора (RTMP-сервер + ключ). Площадки ссылаются на него через BroadcastDestination::channel_id. */
class BroadcastChannel extends Model
{
    protected $fillable = ['organizer_id', 'platform', 'label', 'server_url', 'stream_key', 'is_default'];

    protected $hidden = ['stream_key'];

    protected $casts = ['stream_key' => 'encrypted', 'is_default' => 'boolean'];

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    public function destinations(): HasMany
    {
        return $this->hasMany(BroadcastDestination::class, 'channel_id');
    }

    /** Без адреса сервера и ключа — то, что видит помощник. */
    public function publicRow(): array
    {
        return ['id' => (int) $this->id, 'platform' => $this->platform, 'label' => $this->label, 'is_default' => (bool) $this->is_default];
    }
}
