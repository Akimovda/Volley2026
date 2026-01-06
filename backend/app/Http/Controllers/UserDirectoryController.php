<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use Illuminate\Http\Request;

class UserDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $cityId = $request->query('city_id');
        $gender = $request->query('gender'); // m|f
        $classic = $request->query('classic_level');
        $beach = $request->query('beach_level');
        $ageMin = $request->query('age_min');
        $ageMax = $request->query('age_max');

        $users = User::query()
            ->with('city')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('first_name', 'ilike', "%{$q}%")
                       ->orWhere('last_name', 'ilike', "%{$q}%")
                       ->orWhere('telegram_username', 'ilike', "%{$q}%");
                });
            })
            ->when($cityId, fn ($query) => $query->where('city_id', $cityId))
            ->when(in_array($gender, ['m', 'f'], true), fn ($query) => $query->where('gender', $gender))
            ->when($classic !== null && $classic !== '', fn ($query) => $query->where('classic_level', (int)$classic))
            ->when($beach !== null && $beach !== '', fn ($query) => $query->where('beach_level', (int)$beach))
            ->when($ageMin !== null && $ageMin !== '', function ($query) use ($ageMin) {
                // age >= X  => birth_date <= today - X years
                $query->whereNotNull('birth_date')
                    ->where('birth_date', '<=', now()->subYears((int)$ageMin)->toDateString());
            })
            ->when($ageMax !== null && $ageMax !== '', function ($query) use ($ageMax) {
                // age <= X  => birth_date >= today - X years
                $query->whereNotNull('birth_date')
                    ->where('birth_date', '>=', now()->subYears((int)$ageMax)->toDateString());
            })
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString();

        $cities = City::query()
            ->orderBy('name')
            ->limit(300)
            ->get();

        return view('users.index', [
            'users' => $users,
            'cities' => $cities,

            // фильтры в форму
            'filters' => [
                'q' => $q,
                'city_id' => $cityId,
                'gender' => $gender,
                'classic_level' => $classic,
                'beach_level' => $beach,
                'age_min' => $ageMin,
                'age_max' => $ageMax,
            ],
        ]);
    }
}
