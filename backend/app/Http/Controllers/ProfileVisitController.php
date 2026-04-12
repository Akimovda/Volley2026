<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileVisitController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user->isPremium()) {
            return redirect()->route('premium.index')
                ->with('error', '👑 Раздел «Мои гости» доступен только Premium-пользователям.');
        }

        $visitors = $user->recentVisitors(7);

        return view('profile.visitors', compact('visitors'));
    }
}
