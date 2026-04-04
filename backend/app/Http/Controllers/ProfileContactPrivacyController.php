<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProfileContactPrivacyController extends Controller
{
    public function update(Request $request)
    {
        // -------------------------------------------------
        // 1. Actor (только self)
        // -------------------------------------------------
        $actor = $request->user();
        abort_unless($actor, 403);

        // -------------------------------------------------
        // 2. Allowlist входных полей
        // -------------------------------------------------
        $allowed = ['allow_user_contact'];

        // Жёстко выбрасываем всё лишнее
        $request->replace($request->only($allowed));

        // -------------------------------------------------
        // 3. Validate
        // -------------------------------------------------
        $data = $request->validate([
            'allow_user_contact' => ['nullable', 'boolean'],
        ]);

        // -------------------------------------------------
        // 4. Normalize checkbox (hidden + checkbox)
        // -------------------------------------------------
        $allow = array_key_exists('allow_user_contact', $data)
            ? (bool) $data['allow_user_contact']
            : false;

        // -------------------------------------------------
        // 5. Save
        // -------------------------------------------------
        $actor->forceFill([
            'allow_user_contact' => $allow,
        ])->save();

        return back()->with('status', 'Настройки приватности сохранены ✅');
    }
}