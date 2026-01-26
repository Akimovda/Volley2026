<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UserPublicController extends Controller
{
    public function show(Request $request, User $user)
    {
        // SoftDeletes: удалённые пользователи не попадут сюда через биндинг.
        // На всякий случай:
        abort_if(method_exists($user, 'trashed') && $user->trashed(), 404);

        $user->loadMissing(['city']);

        $isSelf = auth()->check() && (int)auth()->id() === (int)$user->id;

        return view('user.public', [
            'user' => $user,
            'isSelf' => $isSelf,
        ]);
    }
}
