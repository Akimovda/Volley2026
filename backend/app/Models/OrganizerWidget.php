<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OrganizerWidget extends Model
{
    protected $fillable = [
        'user_id',
        'api_key',
        'allowed_domains',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'allowed_domains' => 'array',
        'settings'        => 'array',
        'is_active'       => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function generateKey(): string
    {
        do {
            $key = 'wgt_' . Str::random(32);
        } while (static::where('api_key', $key)->exists());

        return $key;
    }

    /** Проверить что домен разрешён */
    public function allowsDomain(string $domain): bool
    {
        $domains = $this->allowed_domains ?? [];
        if (empty($domains)) {
            return true; // если не задано — разрешаем всё (для тестирования)
        }
        foreach ($domains as $allowed) {
            $allowed = trim($allowed);
            if ($allowed === $domain) return true;
            // поддержка *.mysite.ru
            if (str_starts_with($allowed, '*.')) {
                $base = substr($allowed, 2);
                if (str_ends_with($domain, '.' . $base) || $domain === $base) return true;
            }
        }
        return false;
    }

    /** Настройки с дефолтами */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
