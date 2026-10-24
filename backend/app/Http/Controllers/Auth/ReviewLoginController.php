<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewLoginController extends Controller
{
    public function show()
    {
        return view('auth.review-login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $request->session()->regenerate();
            $isApp = preg_match('/VolleyPlayApp|SVSVolleyApp/', (string) $request->userAgent()) === 1;
            return redirect($isApp ? '/' : '/events');
        }

        return back()->withErrors(['email' => __('auth.review_login_failed')]);
    }
}
