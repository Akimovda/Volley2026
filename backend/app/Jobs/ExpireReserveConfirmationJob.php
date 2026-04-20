<?php

namespace App\Jobs;

use App\Models\TournamentLeagueTeam;
use App\Services\TournamentLeagueService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireReserveConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $leagueTeamId
    ) {}

    public function handle(TournamentLeagueService $service): void
    {
        $leagueTeam = TournamentLeagueTeam::find($this->leagueTeamId);
        if (!$leagueTeam) return;

        $service->expireReserveOffer($leagueTeam);
    }
}
