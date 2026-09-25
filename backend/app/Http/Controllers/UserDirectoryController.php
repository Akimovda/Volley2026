<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $viewer = $request->user();
        $isTrusted = Gate::forUser($viewer)->allows('is-trusted');

        // Гость не может фильтровать/искать — форма ему не показывается,
        // но параметры в query-строке всё равно должны игнорироваться сервером.
        $filtersAllowed = (bool) $viewer;

        $q = $filtersAllowed ? trim((string) $request->query('q', '')) : '';
        $cityId = $filtersAllowed ? $request->query('city_id') : null;
        $gender = $filtersAllowed ? $request->query('gender') : null; // m|f
        $classic = $filtersAllowed ? $request->query('classic_level') : null;
        $beach = $filtersAllowed ? $request->query('beach_level') : null;
        $ageMin = $filtersAllowed ? $request->query('age_min') : null;
        $ageMax = $filtersAllowed ? $request->query('age_max') : null;

        $users = User::query()
            ->with('city')
            ->whereNotNull('profile_completed_at')
            ->where(function ($q2) {
                $q2->whereNull('is_hidden')->orWhere('is_hidden', false);
            })
            ->when($q !== '', function ($query) use ($q, $isTrusted) {
                $query->where(function ($qq) use ($q, $isTrusted) {
                    $qq->whereRaw("CONCAT(last_name, ' ', first_name) ILIKE ?", ["%{$q}%"])
                       ->orWhereRaw("CONCAT(first_name, ' ', last_name) ILIKE ?", ["%{$q}%"])
                       ->orWhere('first_name', 'ilike', "%{$q}%")
                       ->orWhere('last_name', 'ilike', "%{$q}%");

                    if ($isTrusted) {
                        $qq->orWhere('telegram_username', 'ilike', "%{$q}%");
                    }
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
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(24)
            ->withQueryString();

        $cities = $filtersAllowed
            ? City::query()
                ->whereIn('id', \App\Models\User::whereNotNull('city_id')->pluck('city_id')->unique())
                ->orderBy('name')
                ->limit(300)
                ->get()
            : collect();

        return view('users.index', [
            'users' => $users,
            'cities' => $cities,
            'canFilter' => $filtersAllowed,

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
