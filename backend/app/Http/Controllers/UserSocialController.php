<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserLevelVote;
use App\Models\UserPlayLike;
use Illuminate\Http\Request;

class UserSocialController extends Controller
{
    // Голосование за уровень
    public function vote(Request $request, User $user)
    {
        $request->validate([
            'direction' => ['required', 'in:classic,beach'],
            'level'     => ['required', 'integer', 'min:1', 'max:7'],
        ]);

        $voter = $request->user();

        if ($voter->id === $user->id) {
            return back()->with('error', 'Нельзя голосовать за себя.');
        }

        UserLevelVote::updateOrCreate(
            [
                'voter_id'  => $voter->id,
                'target_id' => $user->id,
                'direction' => $request->direction,
            ],
            ['level' => $request->level]
        );

        return back()->with('status', 'Оценка сохранена!');
    }

    // Лайк "нравится играть"
    public function like(Request $request, User $user)
    {
        $liker = $request->user();

        if ($liker->id === $user->id) {
            return back()->with('error', 'Нельзя лайкать себя.');
        }

        $existing = UserPlayLike::where('liker_id', $liker->id)
            ->where('target_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            return back()->with('status', 'Лайк убран.');
        }

        UserPlayLike::create([
            'liker_id'  => $liker->id,
            'target_id' => $user->id,
        ]);

        return back()->with('status', 'Лайк добавлен!');
    }
}
