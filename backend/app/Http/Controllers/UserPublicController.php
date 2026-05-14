<?php

namespace App\Http\Controllers;

use App\Models\PlayerFollow;
use App\Models\User;
use App\Models\UserLevelVote;
use App\Models\UserPlayLike;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPublicController extends Controller
{
    public function show(Request $request, User $user)
    {
        abort_if(method_exists($user, 'trashed') && $user->trashed(), 404);

        $user->loadMissing(['city']);

        $isSelf = auth()->check() && (int)auth()->id() === (int)$user->id;

        // Только photos, event_photos не тянем
        $photos = $user->getMedia('photos')
            ->sortByDesc('created_at')
            ->values();

        $authId = auth()->id();

        // Оценки уровня
        $classicVotes = UserLevelVote::where('target_id', $user->id)
            ->where('direction', 'classic')
            ->get();
        $beachVotes = UserLevelVote::where('target_id', $user->id)
            ->where('direction', 'beach')
            ->get();

        $classicAvg = $classicVotes->count() ? round($classicVotes->avg('level'), 1) : null;
        $beachAvg   = $beachVotes->count() ? round($beachVotes->avg('level'), 1) : null;

        $myClassicVote = $authId ? $classicVotes->firstWhere('voter_id', $authId)?->level : null;
        $myBeachVote   = $authId ? $beachVotes->firstWhere('voter_id', $authId)?->level : null;

        // Лайки "нравится играть"
        $likes = UserPlayLike::where('target_id', $user->id)
            ->with('liker:id,first_name,last_name,avatar_media_id')
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        $iLiked = $authId
            ? UserPlayLike::where('liker_id', $authId)->where('target_id', $user->id)->exists()
            : false;

                // Записываем визит (для Premium-функции "Гости профиля")
        if (auth()->check() && !$isSelf) {
            $user->recordVisit((int) auth()->id());
        }

        // Кнопка "Следить" — только для премиум-пользователей у которых этот игрок в друзьях
        $canFollow  = false;
        $isFollowing = false;
        if (auth()->check() && !$isSelf && $authId) {
            $viewer = auth()->user();
            if ($viewer->isPremium() && $viewer->isFriendWith($user->id)) {
                $canFollow   = true;
                $isFollowing = PlayerFollow::where('follower_user_id', $authId)
                    ->where('followed_user_id', $user->id)
                    ->exists();
            }
        }

        return view('user.public', [
            'user'          => $user,
            'isSelf'        => $isSelf,
            'photos'        => $photos,
            'classicVotes'  => $classicVotes,
            'beachVotes'    => $beachVotes,
            'classicAvg'    => $classicAvg,
            'beachAvg'      => $beachAvg,
            'myClassicVote' => $myClassicVote,
            'myBeachVote'   => $myBeachVote,
            'likes'         => $likes,
            'iLiked'        => $iLiked,
            'canFollow'     => $canFollow,
            'isFollowing'   => $isFollowing,
        ]);
    }
}