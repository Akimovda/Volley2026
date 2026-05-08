<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $available = (array) config('app.available_locales', ['ru', 'en']);

        if (!in_array($locale, $available, true)) {
            abort(404);
        }

        $request->session()->put('locale', $locale);

        $user = $request->user();
        if ($user) {
            $user->locale = $locale;
            $user->save();
        }

        return back();
    }
}
