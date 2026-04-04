<?php
// app/Http/Controllers/Api/OccurrenceParticipantsController.php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

class OccurrenceParticipantsController extends Controller
{
    public function index($occurrence)
    {
        $players = DB::table('event_registrations')
            ->join('users', 'users.id', '=', 'event_registrations.user_id')
            ->where('event_registrations.occurrence_id', $occurrence)
            ->where(function ($q) {
                $q->whereNull('event_registrations.is_cancelled')
                  ->orWhere('event_registrations.is_cancelled', false);
            })
            ->select(
                'users.id',
                'users.name',
                'users.first_name',
                'users.last_name',
                'users.classic_level',
                'users.profile_photo_path',
                'event_registrations.position'
            )
            ->orderBy('event_registrations.id')
            ->get()
            ->map(function ($u) {

                $displayName = $u->name;

                if ($u->first_name || $u->last_name) {
                    $displayName = trim(($u->last_name ?? '') . ' ' . ($u->first_name ?? ''));
                }

                $avatar = null;

                if ($u->profile_photo_path) {
                    $avatar = asset('storage/'.$u->profile_photo_path);
                }

                return [
                    'id' => $u->id,
                    'name' => $displayName,
                    'position' => $u->position,
                    'avatar' => $avatar,
                    'level' => $u->classic_level ?? 0
                ];
            })
            ->values();

        return response()->json($players);
    }
}