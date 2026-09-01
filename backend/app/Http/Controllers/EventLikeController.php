<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventLike;
use App\Models\EventOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventLikeController extends Controller
{
    /**
     * Лайк ставится на конкретный тур (occurrence), а не на всю серию —
     * иначе лайк на одной дате повторяющегося мероприятия показывался бы
     * на всех остальных его датах.
     */
    public function toggle(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('events.card_like_login_required')], 401);
        }

        $data = $request->validate([
            'occurrence_id' => ['required', 'integer', 'exists:event_occurrences,id'],
        ]);

        $occurrence = EventOccurrence::where('id', $data['occurrence_id'])
            ->where('event_id', $event->id)
            ->first();

        if (!$occurrence) {
            abort(404);
        }

        $deleted = EventLike::where('occurrence_id', $occurrence->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $liked = false;
        } else {
            DB::table('event_likes')->insertOrIgnore([
                'event_id'      => $event->id,
                'occurrence_id' => $occurrence->id,
                'user_id'       => $user->id,
                'created_at'    => now(),
            ]);
            $liked = true;
        }

        $count = EventLike::where('occurrence_id', $occurrence->id)->count();

        return response()->json(['liked' => $liked, 'count' => $count]);
    }
}
