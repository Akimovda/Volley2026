<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AccountDeleteRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        $hasPendingOrganizerRequest = DB::table('organizer_requests')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();

        $hasPendingDeleteRequest = AccountDeleteRequest::query()
            ->where('user_id', $user->id)
            ->where('status', 'new')
            ->exists();

        return view('profile.show', [
            'request'                       => $request,
            'user'                          => $user,
            'hasPendingOrganizerRequest'    => $hasPendingOrganizerRequest,
            'hasPendingDeleteRequest'       => $hasPendingDeleteRequest,
            'notifyPlayerRegistrations'     => (bool) ($user->notify_player_registrations ?? false),
        ]);
    }
}
