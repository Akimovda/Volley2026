<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\PlayerFollowService;
use Illuminate\Http\Request;

class PlayerFollowController extends Controller
{
    public function __construct(private readonly PlayerFollowService $followService) {}

    public function store(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $follower = $request->user();

        if (!$follower->isPremium()) {
            return back()->with('error', 'Функция доступна только для Premium-подписчиков.');
        }
        if ((int)$follower->id === (int)$user->id) {
            return back()->with('error', 'Нельзя подписаться на себя.');
        }
        if (!$follower->isFriendWith($user->id)) {
            return back()->with('error', 'Подписаться можно только на друзей.');
        }

        $this->followService->follow($follower, $user);

        return back()->with('status', "Вы подписались на записи игрока «{$user->name}».");
    }

    public function destroy(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $this->followService->unfollow($request->user(), $user);
        return back()->with('status', "Подписка на «{$user->name}» отменена.");
    }
}
