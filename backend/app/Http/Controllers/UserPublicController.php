<?php

namespace App\Http\Controllers;

use App\Models\User;

class UserPublicController extends Controller
{
    public function show(User $user)
    {
        $user->load('city');

        return view('users.show', [
            'u' => $user,
        ]);
    }
}
