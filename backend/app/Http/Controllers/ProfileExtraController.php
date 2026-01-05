<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Services\EventRegistrationRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProfileExtraController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $data = $request->validate([
            'first_name'    => ['nullable', 'string', 'max:255'],
            'last_name'     => ['nullable', 'string', 'max:255'],
            'phone'         => ['nullable', 'string', 'max:50'],
            'classic_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'beach_level'   => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $user->forceFill($data)->save();

        // Попробовать автозаписать после сохранения анкеты
        $eventId = session('pending_event_join');
        if (!empty($eventId)) {
            $event = Event::find((int) $eventId);

            if ($event) {
                /** @var EventRegistrationRequirements $requirements */
                $requirements = app(EventRegistrationRequirements::class);

                $missing = $requirements->missing($user, $event);

                if (empty($missing)) {
                    $requirements->ensureEligible($user, $event);

                    DB::table('event_registrations')->updateOrInsert(
                        ['event_id' => $event->id, 'user_id' => $user->id],
                        ['created_at' => now(), 'updated_at' => now()]
                    );

                    session()->forget('pending_event_join');
                    session()->forget('pending_profile_required');

                    session()->flash(
                        'status',
                        'Вы успешно записаны на мероприятие, не забудьте взять с собой хорошее настроение.'
                    );
                } else {
                    session()->put('pending_profile_required', $missing);
                }
            }
        }

        return back()->with('status', 'Профиль обновлён.');
    }
}
