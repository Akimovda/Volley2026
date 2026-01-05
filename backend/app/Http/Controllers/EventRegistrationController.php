<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventRegistrationRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EventRegistrationController extends Controller
{
    public function store(Request $request, Event $event, EventRegistrationRequirements $requirements)
    {
        $user = Auth::user();

        // 1) Missing profile fields -> redirect to profile completion
        $missing = $requirements->missing($user, $event);
        if (!empty($missing)) {
            // remember what the user tried to join
            $request->session()->put('pending_event_join', $event->id);
            $request->session()->put('pending_profile_required', $missing);

            return redirect()
                ->to('/profile/complete?required=' . implode(',', $missing) . '&event_id=' . $event->id)
                ->withErrors(['profile' => 'Для записи нужно заполнить профиль.']);
        }

        // 2) Hard eligibility checks (levels lower than required)
        $requirements->ensureEligible($user, $event);

        // 3) Register (idempotent)
        DB::table('event_registrations')->updateOrInsert(
            ['event_id' => $event->id, 'user_id' => $user->id],
            ['created_at' => now(), 'updated_at' => now()]
        );

        // clear pending state
        $request->session()->forget('pending_event_join');
        $request->session()->forget('pending_profile_required');

        return redirect()->to('/events')->with('status', 'Вы записаны на мероприятие!');
    }

    public function destroy(Request $request, Event $event)
    {
        $user = Auth::user();

        DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->delete();

        // If pending join was for this event, clear it
        if ((int) $request->session()->get('pending_event_join') === (int) $event->id) {
            $request->session()->forget('pending_event_join');
            $request->session()->forget('pending_profile_required');
        }

        return redirect()->to('/events')->with('status', 'Запись на мероприятие отменена.');
    }
}
