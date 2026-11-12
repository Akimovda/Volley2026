<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $friends = $user->friends()->with(['media'])->get();

        return view('friends.index', compact('friends'));
    }

    public function store(Request $request, User $user)
    {
        $me = $request->user();

        if ($me->id === $user->id) {
            return back()->with('error', __('Нельзя добавить себя в друзья.'));
        }

        Friendship::firstOrCreate([
            'user_id'   => $me->id,
            'friend_id' => $user->id,
        ]);

        return back()->with('status', __('«:name» добавлен в друзья ✅', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user)
    {
        $me = $request->user();

        Friendship::where('user_id', $me->id)
            ->where('friend_id', $user->id)
            ->delete();

        return back()->with('status', __('«:name» удалён из друзей.', ['name' => $user->name]));
    }
}
