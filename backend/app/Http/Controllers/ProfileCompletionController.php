<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileCompletionController extends Controller
{
    public function __construct()
    {
        // Это страница профиля, логично требовать авторизацию
        $this->middleware('auth');
    }

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
                'personal' => ['full_name', 'patronymic', 'phone', 'city', 'birth_date'],
                'classic'  => ['classic_level'],
                'beach'    => ['beach_level'],
            ];

            $required = collect($map[$section] ?? []);
        }

        $requiredKeys = $required->unique()->values()->all();

        // сохраняем список требуемых полей для подсветки на /profile/complete
        $request->session()->put('pending_profile_required', $requiredKeys);

        // Города (пока простым запросом, без модели)
        $cities = DB::table('cities')
            ->orderBy('name')
            ->limit(300)
            ->get();

        $user = $request->user();

        // Есть ли уже pending-заявка на organizer
        $hasPendingOrganizerRequest = DB::table('organizer_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        return view('profile.complete', [
            'user' => $user,
            'canEditProtected' => $user->can('edit-protected-profile-fields'),
            'requiredKeys' => $requiredKeys,
            'eventId' => $eventId,
            'section' => $section,
            'cities' => $cities,
            'hasPendingOrganizerRequest' => $hasPendingOrganizerRequest,
        ]);
    }
}
