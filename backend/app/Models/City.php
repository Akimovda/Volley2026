<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

   protected $table = 'cities';


    protected $fillable = [
        'name',
        'region',
        'timezone',   // ✅ если есть в таблице
    ];
        // app/Models/City.php
        public function locations()
    {
        return $this->hasMany(\App\Models\Location::class, 'city_id');
    }
       public function users(): HasMany
    {
        return $this->hasMany(User::class, 'city_id');
    }

    /**
     * Регион для отображения рядом с городом. Для городов федерального значения
     * (Москва, Санкт-Петербург, Севастополь) region совпадает с name — показывать
     * его отдельно значит дублировать название города.
     */
    public static function displayRegion(?string $name, ?string $region): ?string
    {
        if (empty($region)) {
            return null;
        }

        if (mb_strtolower(trim($region)) === mb_strtolower(trim((string) $name))) {
            return null;
        }

        return $region;
    }

    public function getRegionDisplayAttribute(): ?string
    {
        return self::displayRegion($this->name, $this->region);
    }
}
