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
            return back()->with('error', __('Функция доступна только для Premium-подписчиков.'));
        }
        if ((int)$follower->id === (int)$user->id) {
            return back()->with('error', __('Нельзя подписаться на себя.'));
        }
        if (!$follower->isFriendWith($user->id)) {
            return back()->with('error', __('Подписаться можно только на друзей.'));
        }

        $this->followService->follow($follower, $user);

        return back()->with('status', __('Вы подписались на записи игрока «:name».', ['name' => $user->name]));
    }

    public function destroy(Request $request, User $user): \Illuminate\Http\RedirectResponse
    {
        $this->followService->unfollow($request->user(), $user);
        return back()->with('status', __('Подписка на «:name» отменена.', ['name' => $user->name]));
    }
}
