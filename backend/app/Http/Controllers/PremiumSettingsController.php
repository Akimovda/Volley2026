<?php

namespace App\Http\Controllers;

use App\Models\PremiumSubscription;
use App\Services\PremiumService;
use Illuminate\Http\Request;

class PremiumSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $user = $request->user();
        $sub  = app(PremiumService::class)->getActive($user);

        if (!$sub) {
            return redirect()->route('premium.index')
                ->with('error', 'У вас нет активной Premium-подписки.');
        }

        return view('premium.settings', compact('sub'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $sub  = app(PremiumService::class)->getActive($user);

        if (!$sub) {
            return redirect()->route('premium.index')
                ->with('error', 'У вас нет активной Premium-подписки.');
        }

        $request->validate([
            'weekly_digest'    => 'boolean',
            'notify_level_min' => 'nullable|integer|min:1|max:10',
            'notify_level_max' => 'nullable|integer|min:1|max:10',
        ]);

        $sub->update([
            'weekly_digest'    => $request->boolean('weekly_digest'),
            'notify_level_min' => $request->input('notify_level_min') ?: null,
            'notify_level_max' => $request->input('notify_level_max') ?: null,
        ]);

        return back()->with('status', '✅ Настройки уведомлений сохранены.');
    }
}
