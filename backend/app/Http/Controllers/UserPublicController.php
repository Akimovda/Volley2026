<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserPublicController extends Controller
{
    public function show(Request $request, User $user)
    {
        abort_if(method_exists($user, 'trashed') && $user->trashed(), 404);

        $user->loadMissing(['city']);

        $isSelf = auth()->check() && (int)auth()->id() === (int)$user->id;

        // Только photos, event_photos не тянем
        $photos = $user->getMedia('photos')
            ->sortByDesc('created_at')
            ->values();

        return view('user.public', [
            'user' => $user,
            'isSelf' => $isSelf,
            'photos' => $photos,
        ]);
    }
}