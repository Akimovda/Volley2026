<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        $deletionDelay = (int) getAppSetting('account_deletion_delay_seconds', 30);

        return view('profile.show', [
            'request'                       => $request,
            'user'                          => $user,
            'hasPendingOrganizerRequest'    => $hasPendingOrganizerRequest,
            'deletionDelay'                 => $deletionDelay,
            'notifyPlayerRegistrations'     => (bool) ($user->notify_player_registrations ?? false),
        ]);
    }
}
