<?php

namespace App\Http\Controllers;

use App\Models\EventOccurrence;
use App\Services\WaitlistService;
use App\Services\EventRegistrationGuard;
use Illuminate\Http\Request;

class OccurrenceWaitlistController extends Controller
{
    public function store(Request $request, EventOccurrence $occurrence): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        // Проверяем что мероприятие не началось
        if ($occurrence->starts_at && now('UTC')->gte($occurrence->starts_at)) {
            return back()->with('error', 'Мероприятие уже началось.');
        }

        // Турниры не поддерживают резерв
        $regMode = (string)($occurrence->event->registration_mode ?? 'single');
        if (in_array($regMode, ['team_classic', 'team_beach'], true)) {
            return back()->with('error', 'Резерв недоступен для турниров.');
        }

        // Проверяем лимит резерва (не больше max_players)
        $maxPlayers = (int)($occurrence->event->gameSettings->max_players ?? 0);
        if ($maxPlayers > 0) {
            $waitlistCount = \App\Models\OccurrenceWaitlist::query()
                ->where('occurrence_id', $occurrence->id)
                ->count();
            if ($waitlistCount >= $maxPlayers) {
                return back()->with('error', 'Резерв заполнен.');
            }
        }

        $positions = $request->input('positions', []);
        if (!is_array($positions)) $positions = [];

        app(WaitlistService::class)->join($occurrence, $user, $positions);

        return back()->with('status', 'Вы записаны в резерв! Мы уведомим вас когда освободится место.');
    }

    public function destroy(Request $request, EventOccurrence $occurrence): \Illuminate\Http\RedirectResponse
    {
        $user = $request->user();

        app(WaitlistService::class)->leave($occurrence, $user);

        return back()->with('status', 'Вы покинули резерв.');
    }
}
