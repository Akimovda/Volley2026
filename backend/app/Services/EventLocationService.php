<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Validation\ValidationException;

class EventLocationService
{
    /**
     * Проверяет и возвращает локацию события
     */
    public function resolveAndAssertLocation(array $data, string $role): Location
    {
        $locationId = (int)($data['location_id'] ?? 0);

        if ($locationId <= 0) {
            throw ValidationException::withMessages([
                'location_id' => ['Не указана локация.']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | LOAD LOCATION
        |--------------------------------------------------------------------------
        */

        $location = Location::query()->find($locationId);

        if (!$location) {
            throw ValidationException::withMessages([
                'location_id' => ['Локация не найдена.']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | CITY VALIDATION
        |--------------------------------------------------------------------------
        */

        $cityId = (int)($data['city_id'] ?? 0);

        if ($cityId > 0 && (int)$location->city_id !== $cityId) {
            throw ValidationException::withMessages([
                'location_id' => ['Локация не принадлежит выбранному городу.']
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | ORGANIZER RESTRICTIONS
        |--------------------------------------------------------------------------
        */

        if ($role !== 'admin' && $location->organizer_id !== null) {
            throw ValidationException::withMessages([
                'location_id' => [
                    'Организатор может выбирать только локации, созданные админом.'
                ]
            ]);
        }

        return $location;
    }
}