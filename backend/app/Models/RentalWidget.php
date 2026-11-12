<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Str;

/**
 * Виджет аренды кортов и площадок: те же ключ/домены/настройки, что у виджета мероприятий,
 * но отдельная таблица, отдельный ключ и отдельные адреса.
 */
class RentalWidget extends OrganizerWidget
{
    protected $table = 'rental_widgets';

    public const KIND_CARDS = 'cards';
    public const KIND_TIMELINE = 'timeline';

    protected $fillable = ['user_id', 'kind', 'api_key', 'allowed_domains', 'settings', 'is_active'];

    public static function generateKey(): string
    {
        do {
            $key = 'rwg_' . Str::random(32);
        } while (static::where('api_key', $key)->exists());

        return $key;
    }
}
