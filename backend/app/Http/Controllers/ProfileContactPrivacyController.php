<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileContactPrivacyController extends Controller
{
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'allow_user_contact' => ['nullable', 'boolean'],
        ]);

        // checkbox + hidden(0) -> придёт "0" или "1"
        $allow = (bool)($validated['allow_user_contact'] ?? false);

        // если поле есть в users и разрешено к массовому присвоению — ок
        $user->forceFill([
            'allow_user_contact' => $allow,
        ])->save();

        return back()->with('status', 'Настройки приватности сохранены ✅');
    }
}
