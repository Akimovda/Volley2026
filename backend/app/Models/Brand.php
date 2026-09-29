<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class Brand extends Model
{
    protected $fillable = [
        'slug',
        'ua_suffix',
        'display_name',
        'site_title',
        'logo_day_path',
        'logo_night_path',
        'og_image_path',
        'app_icon_path',
        'theme',
        'menu',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'theme' => 'array',
        'menu' => 'array',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('brands.all'));
        static::deleted(fn () => Cache::forget('brands.all'));
    }

    /**
     * @return Collection<int, Brand>
     */
    public static function cached(): Collection
    {
        return Cache::rememberForever('brands.all', fn () => static::all());
    }

    public static function detectByUserAgent(?string $userAgent): self
    {
        $ua = (string) $userAgent;

        foreach (static::cached() as $brand) {
            if ($brand->ua_suffix !== '' && str_contains($ua, $brand->ua_suffix)) {
                return $brand;
            }
        }

        $default = static::cached()->firstWhere('is_default', true);

        if (!$default) {
            throw new \RuntimeException('No default brand configured — run BrandSeeder.');
        }

        return $default;
    }

    public function getLogoDayUrlAttribute(): ?string
    {
        return $this->logo_day_path ? asset($this->logo_day_path) : null;
    }

    public function getLogoNightUrlAttribute(): ?string
    {
        return $this->logo_night_path ? asset($this->logo_night_path) : null;
    }

    public function getAppIconUrlAttribute(): ?string
    {
        return $this->app_icon_path ? asset($this->app_icon_path) : null;
    }

    public function hasTheme(): bool
    {
        foreach (['day', 'night'] as $mode) {
            if (!empty(array_filter((array) ($this->theme[$mode] ?? [])))) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] пути скрытых пунктов меню */
    public function menuHidden(): array
    {
        return array_values(array_filter((array) ($this->menu['hidden'] ?? []), 'is_string'));
    }

    /** Свои ссылки меню для места ('site' — меню сайта, 'user' — меню пользователя). */
    public function menuLinks(string $place): array
    {
        return array_values(array_filter(
            (array) ($this->menu['links'] ?? []),
            fn ($l) => is_array($l) && ($l['place'] ?? 'site') === $place && !empty($l['url']) && !empty($l['title_ru'])
        ));
    }

    public function getOgImageUrlAttribute(): ?string
    {
        return $this->og_image_path ? asset($this->og_image_path) : null;
    }
}
