<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileCompletionController extends Controller
{
    public function show(Request $request)
    {
        $requiredRaw = (string) $request->query('required', '');
        $section = (string) $request->query('section', '');
        $eventId = $request->query('event_id');

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

        // Города (пока простым запросом, без модели)
        $cities = DB::table('cities')->orderBy('name')->limit(300)->get();

        return view('profile.complete', [
            'requiredKeys' => $requiredKeys,
            'eventId' => $eventId,
            'section' => $section,
            'cities' => $cities,
        ]);
    }
}
