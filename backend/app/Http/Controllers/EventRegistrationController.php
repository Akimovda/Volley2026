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

        $missing = $requirements->missing($user, $event);
        if (!empty($missing)) {
            session([
                'pending_event_join' => $event->id,
                'pending_profile_required' => $missing,
            ]);

            return redirect("/profile/complete?required=" . implode(',', $missing) . "&event_id={$event->id}");
        }

        $requirements->ensureEligible($user, $event);

        $exists = DB::table('event_registrations')
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        if (!$exists) {
            DB::table('event_registrations')->insert([
                'event_id' => $event->id,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return redirect('/events')->with('status', 'Вы записаны на мероприятие!');
    }
}
