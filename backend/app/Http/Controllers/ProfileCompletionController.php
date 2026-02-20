<?php

namespace App\Http\Controllers;

use App\Models\City;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileCompletionController extends Controller
{
    public function show(Request $request)
    {
        $requiredRaw = (string) $request->query('required', '');
        $section     = (string) $request->query('section', '');
        $eventId     = $request->query('event_id');

        if (!empty($eventId)) {
            $request->session()->put('pending_event_join', (int) $eventId);
        }

        $required = collect(explode(',', $requiredRaw))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        if ($required->isEmpty() && $section !== '') {
            $map = [
                'personal' => ['full_name', 'patronymic', 'phone', 'city', 'birth_date'],
                'classic'  => ['classic_level'],
                'beach'    => ['beach_level'],
            ];
            $required = collect($map[$section] ?? []);
        }

        $requiredKeys = $required->unique()->values()->all();
        $request->session()->put('pending_profile_required', $requiredKeys);

        // Города (RU/KZ/UZ) — полный список из базы
        $cities = City::query()
            ->whereIn('country_code', ['RU', 'KZ', 'UZ'])
            ->orderBy('country_code')
            ->orderByRaw('region nulls last')
            ->orderBy('name')
            ->get(['id', 'name', 'region', 'country_code']);

        // Есть ли pending-заявка на организатора
        $user = $request->user();
        $hasPendingRequest = false;

        if ($user) {
            $hasPendingRequest = DB::table('organizer_requests')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();
        }

        return view('profile.complete', [
            'requiredKeys'      => $requiredKeys,
            'eventId'           => $eventId,
            'section'           => $section,
            'cities'            => $cities,
            'hasPendingRequest' => $hasPendingRequest,
        ]);
    }
}
