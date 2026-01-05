<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileCompletionController extends Controller
{
    public function show(Request $request)
    {
        $requiredRaw = (string) $request->query('required', '');
        $section = (string) $request->query('section', '');
        $eventId = $request->query('event_id');

        // сохраняем мероприятие, к которому пользователь хотел записаться
        if (!empty($eventId)) {
            $request->session()->put('pending_event_join', (int) $eventId);
        }

        $required = collect(explode(',', $requiredRaw))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        // поддержка старого ?section=
        if ($required->isEmpty() && $section !== '') {
            $map = [
                'personal' => ['full_name', 'phone', 'email'],
                'classic'  => ['classic_level'],
                'beach'    => ['beach_level'],
            ];

            $required = collect($map[$section] ?? []);
        }

        $requiredKeys = $required->unique()->values()->all();

        // сохраняем список требуемых полей для подсветки на /user/profile
        $request->session()->put('pending_profile_required', $requiredKeys);

        return view('profile.complete', [
            'requiredKeys' => $requiredKeys,
            'eventId' => $eventId,
            'section' => $section,
        ]);
    }
}
