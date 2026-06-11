<?php

namespace App\Http\Controllers;

use App\Models\PlayerCareerStats;
use App\Models\PlayerFollow;
use App\Models\PlayerOpponentStats;
use App\Models\PlayerPairStats;
use App\Models\PlayerRatingHistory;
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

        // --- OpenSkill / рейтинг ---
        $ratingHistory = PlayerRatingHistory::where('player_rating_history.user_id', $user->id)
            ->leftJoin('tournament_matches as tm', 'tm.id', '=', 'player_rating_history.match_id')
            ->select('player_rating_history.*', 'tm.scored_at as match_scored_at', 'tm.scheduled_at as match_scheduled_at')
            ->with('event:id,title,direction')
            ->orderBy('tm.scored_at')
            ->orderBy('player_rating_history.recorded_at')
            ->get();

        $ratingPartners = [];
        foreach (['beach', 'classic'] as $dir) {
            $ratingPartners[$dir] = PlayerPairStats::where('direction', $dir)
                ->where(fn($q) => $q->where('player1_id', $user->id)->orWhere('player2_id', $user->id))
                ->orderByDesc('matches_together')
                ->limit(10)
                ->get()
                ->map(function ($pair) use ($user) {
                    $pid = (int)$pair->player1_id === (int)$user->id
                        ? $pair->player2_id
                        : $pair->player1_id;
                    $pair->partner = User::select('id', 'first_name', 'last_name')->find($pid);
                    return $pair;
                })
                ->filter(fn($p) => $p->partner !== null)
                ->values();
        }

        $ratingOpponents = PlayerOpponentStats::where('user_id', $user->id)
            ->join('users as opp', 'opp.id', '=', 'player_opponent_stats.opponent_id')
            ->orderByDesc('matches_against')
            ->limit(10)
            ->select('player_opponent_stats.*', 'opp.first_name', 'opp.last_name')
            ->get();

        // Позиция в рейтинге по каждому направлению
        $ratingPositions = [];
        foreach (['beach', 'classic'] as $dir) {
            $stats = PlayerCareerStats::where('user_id', $user->id)->where('direction', $dir)->first();
            if ($stats && $stats->total_matches > 0) {
                $cr = max(0, $stats->mu - 3 * $stats->sigma);
                $ratingPositions[$dir] = [
                    'pos'   => PlayerCareerStats::where('direction', $dir)
                        ->where('total_matches', '>', 0)
                        ->whereRaw('(mu - 3 * sigma) > ?', [$cr])
                        ->count() + 1,
                    'total' => PlayerCareerStats::where('direction', $dir)
                        ->where('total_matches', '>', 0)
                        ->count(),
                ];
            }
        }

        return view('user.public', [
            'user'             => $user,
            'isSelf'           => $isSelf,
            'photos'           => $photos,
            'classicVotes'     => $classicVotes,
            'beachVotes'       => $beachVotes,
            'classicAvg'       => $classicAvg,
            'beachAvg'         => $beachAvg,
            'myClassicVote'    => $myClassicVote,
            'myBeachVote'      => $myBeachVote,
            'likes'            => $likes,
            'iLiked'           => $iLiked,
            'canFollow'        => $canFollow,
            'isFollowing'      => $isFollowing,
            'ratingHistory'    => $ratingHistory,
            'ratingPartners'   => $ratingPartners,
            'ratingOpponents'  => $ratingOpponents,
            'ratingPositions'  => $ratingPositions,
        ]);
    }
}