<?php

namespace App\Http\Controllers;

use App\Models\PlayerFollow;
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

        // Список игроков, за которыми следит пользователь
        $follows = PlayerFollow::where('follower_user_id', $user->id)
            ->with('followed:id,first_name,last_name,name,avatar_media_id')
            ->get();

        // Друзья для добавления новой подписки (исключая уже отслеживаемых)
        $followedIds = $follows->pluck('followed_user_id')->toArray();
        $friends = $user->friends()
            ->whereNotIn('users.id', $followedIds)
            ->select('users.id', 'users.first_name', 'users.last_name', 'users.name')
            ->get();

        return view('premium.settings', compact('sub', 'follows', 'friends'));
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
            'weekly_digest'       => 'boolean',
            'notify_level_min'    => 'nullable|integer|min:1|max:10',
            'notify_level_max'    => 'nullable|integer|min:1|max:10',
            'hide_from_followers' => 'boolean',
        ]);

        $sub->update([
            'weekly_digest'       => $request->boolean('weekly_digest'),
            'notify_level_min'    => $request->input('notify_level_min') ?: null,
            'notify_level_max'    => $request->input('notify_level_max') ?: null,
            'hide_from_followers' => $request->boolean('hide_from_followers'),
        ]);

        return back()->with('status', '✅ Настройки уведомлений сохранены.');
    }
}
