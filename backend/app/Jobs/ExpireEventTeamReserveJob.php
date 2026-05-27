<?php

namespace App\Jobs;

use App\Models\EventTeam;
use App\Services\TournamentTeamService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpireEventTeamReserveJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $teamId,
        public readonly string $token,
    ) {}

    public function handle(TournamentTeamService $service): void
    {
        $team = EventTeam::find($this->teamId);

        if (!$team || $team->confirmation_token !== $this->token) {
            return; // Уже подтверждена или отозвана
        }

        if ($team->confirmation_expires_at?->isFuture()) {
            return; // Ещё не истекло
        }

        $service->expireEventReserveOffer($team);
    }
}
