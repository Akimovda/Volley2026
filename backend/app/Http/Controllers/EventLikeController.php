<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventLikeController extends Controller
{
    public function toggle(Request $request, Event $event)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => __('events.card_like_login_required')], 401);
        }

        $deleted = EventLike::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->delete();

        if ($deleted > 0) {
            $liked = false;
        } else {
            DB::table('event_likes')->insertOrIgnore([
                'event_id'   => $event->id,
                'user_id'    => $user->id,
                'created_at' => now(),
            ]);
            $liked = true;
        }

        $count = EventLike::where('event_id', $event->id)->count();

        return response()->json(['liked' => $liked, 'count' => $count]);
    }
}
