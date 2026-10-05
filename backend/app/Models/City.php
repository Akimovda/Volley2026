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

    /** Города федерального значения ↔ их области (в cities.region у города стоит его же название). */
    private const FEDERAL_REGION_PAIRS = [
        'Москва'                => 'Московская область',
        'Московская область'    => 'Москва',
        'Санкт-Петербург'       => 'Ленинградская область',
        'Ленинградская область' => 'Санкт-Петербург',
    ];

    /**
     * Регионы (значения cities.region), мероприятия которых показываем жителю города $cityId
     * в ленте /events: регион самого города + парный (Москва ↔ Московская область,
     * Санкт-Петербург ↔ Ленинградская область). Для остальных городов — весь их регион.
     * Возвращает [country_code, [regions]] или null, если город не найден/регион не указан.
     */
    public static function feedRegionScope(int $cityId): ?array
    {
        $city = \DB::table('cities')->where('id', $cityId)->first(['region', 'country_code']);
        $region = trim((string) ($city->region ?? ''));
        if ($region === '') {
            return null;
        }

        $regions = [$region];
        if (isset(self::FEDERAL_REGION_PAIRS[$region])) {
            $regions[] = self::FEDERAL_REGION_PAIRS[$region];
        }

        return [(string) ($city->country_code ?? ''), $regions];
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
