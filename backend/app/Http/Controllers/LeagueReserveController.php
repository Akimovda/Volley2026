<?php

namespace App\Http\Controllers;

use App\Models\TournamentLeagueTeam;
use App\Services\TournamentLeagueService;
use Illuminate\Http\Request;
use InvalidArgumentException;

class LeagueReserveController extends Controller
{
    public function confirm(Request $request, string $token, TournamentLeagueService $service)
    {
        $leagueTeam = TournamentLeagueTeam::where('confirmation_token', $token)->firstOrFail();

        $user = $request->user();
        $team = $leagueTeam->team;

        // Только капитан может подтвердить
        if (!$user || !$team || (int) $team->captain_user_id !== (int) $user->id) {
            abort(403, 'Только капитан команды может подтвердить участие.');
        }

        try {
            $service->confirmReserveSpot($leagueTeam);

            $league = $leagueTeam->league;
            return redirect()
                ->route('events.index')
                ->with('success', "Участие команды «{$team->name}» в лиге «{$league->name}» подтверждено ✅");
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('events.index')
                ->with('error', $e->getMessage());
        }
    }
}
