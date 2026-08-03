<?php

namespace App\Console\Commands;

use App\Models\PlayerCareerStats;
use App\Services\TournamentEloService;
use Illuminate\Console\Command;

class RecalculateElo extends Command
{
    protected $signature   = 'tournament:recalculate-elo';
    protected $description = 'Пересчитать Elo (elo_rating) по всей истории турнирных матчей — ручной fallback (основной путь автоматический)';

    public function handle(TournamentEloService $service): int
    {
        $this->info('Пересчёт Elo по историческим матчам...');
        $service->rebuildAll();

        $this->newLine();
        $this->info('✅ Топ-15 по Elo:');
        $this->showCareerTop();

        return self::SUCCESS;
    }

    private function showCareerTop(): void
    {
        $rows = PlayerCareerStats::where('total_matches', '>', 0)
            ->with('user:id,first_name,last_name')
            ->orderByDesc('elo_rating')
            ->limit(15)
            ->get();

        $headers = ['#', 'Игрок', 'Elo'];
        $data = $rows->map(function ($s, $i) {
            $name = trim(($s->user->last_name ?? '') . ' ' . ($s->user->first_name ?? '')) ?: '#'.$s->user_id;
            return [$i + 1, $name . ' (' . $s->direction . ')', $s->elo_rating];
        });

        $this->table($headers, $data);
    }
}
